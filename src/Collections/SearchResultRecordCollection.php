<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Collection of search result records returned from search operations.
 *
 * Provides type-safe collection operations for managing and querying
 * search results, including filtering by field, namespace, similarity,
 * and retrieving model instances.
 *
 * @method SearchResultRecord|null first()
 * @method SearchResultRecord|null last()
 * @method SearchResultRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class SearchResultRecordCollection extends AbstractTypedCollection
{
    /**
     * The delimiter used to separate namespace and ID in fingerprints.
     */
    private const FINGERPRINT_DELIMITER = '|';

    /**
     * Initializes an empty collection of search result records.
     */
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    /**
     * Extracts all document identifiers from the collection.
     *
     * @return string[] Array of document IDs
     */
    public function getDocumentIds(): array
    {
        return array_map(
            fn (SearchResultRecord $record): string => $record->document_id,
            $this->items
        );
    }

    /**
     * Extracts all fingerprints from the collection.
     *
     * @return string[] Array of fingerprint strings
     */
    public function getFingerprints(): array
    {
        return array_map(
            fn (SearchResultRecord $record): string => $record->fingerprint,
            $this->items
        );
    }

    /**
     * Extracts all unique namespaces from the collection.
     *
     * @return string[] Array of unique namespace strings
     */
    public function getNamespaces(): array
    {
        $namespaces = [];

        foreach ($this->items as $record) {
            $namespace = $this->extractNamespace($record->fingerprint);

            if ($namespace !== null && ! in_array($namespace, $namespaces, true)) {
                $namespaces[] = $namespace;
            }
        }

        return $namespaces;
    }

    /**
     * Extracts all entity IDs from the collection.
     *
     * @return string[] Array of entity ID strings
     */
    public function getEntityIds(): array
    {
        $ids = [];

        foreach ($this->items as $record) {
            $id = $this->extractId($record->fingerprint);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Filters the collection to records meeting the minimum similarity score.
     *
     * @param  float  $minSimilarity  The minimum similarity threshold (0.0 to 1.0)
     * @return self A new collection containing only records above the threshold
     */
    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(
            fn (SearchResultRecord $record): bool => $record->similarity >= $minSimilarity
        );
    }

    /**
     * Filters the collection to records that have matches in the given field.
     *
     * @param  string  $field  The field name to filter by
     * @return self A new collection containing only records with matches in the field
     */
    public function filterByField(string $field): self
    {
        return $this->filter(
            fn (SearchResultRecord $record): bool => $record->matches->filterByField($field)->isNotEmpty()
        );
    }

    /**
     * Filters the collection to records belonging to the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by (e.g., 'App\\Models\\User')
     * @return self A new collection containing only matching records
     */
    public function filterByNamespace(string $namespace): self
    {
        $prefix = $namespace.self::FINGERPRINT_DELIMITER;

        return $this->filter(
            fn (SearchResultRecord $record): bool => str_starts_with($record->fingerprint, $prefix)
        );
    }

    /**
     * Filters the collection to records belonging to any of the given namespaces.
     *
     * @param  string[]  $namespaces  Array of namespace strings
     * @return self A new collection containing only matching records
     */
    public function filterByNamespaces(array $namespaces): self
    {
        $prefixes = array_map(
            fn (string $namespace): string => $namespace.self::FINGERPRINT_DELIMITER,
            $namespaces
        );

        return $this->filter(
            function (SearchResultRecord $record) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($record->fingerprint, $prefix)) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Extracts all data arrays from the collection.
     *
     * @return array<array<string, mixed>> Array of data arrays
     */
    public function getData(): array
    {
        return array_map(
            fn (SearchResultRecord $record): array => $record->data->toArray(),
            $this->items
        );
    }

    /**
     * Extracts all match collections from the records.
     *
     * @return array<array<string, mixed>> Array of match arrays
     */
    public function getMatches(): array
    {
        return array_map(
            fn (SearchResultRecord $record): array => $record->matches->toArray(),
            $this->items
        );
    }

    /**
     * Retrieves model instances for all search results in a single query per model class.
     *
     * Missing models are silently ignored. Results are returned in the same order
     * as the collection.
     *
     * @param  string[]  $with  Relations to eager load (e.g., ['profile', 'profile.specialty'])
     * @return Collection<int, Model&Indexable> Collection of model instances
     *
     * @throws InvalidArgumentException If a requested relation does not exist on a model
     */
    public function getModelInstances(array $with = []): Collection
    {
        $groupedIds = $this->getGroupedIdsByClass();

        $models = $this->loadModels($groupedIds, $with);

        return $this->buildOrderedResult($models);
    }

    /**
     * Groups entity IDs by their fully qualified class name.
     *
     * @return array<string, array<int|string>> Associative array of class to IDs
     */
    public function getGroupedIdsByClass(): array
    {
        $grouped = [];

        foreach ($this->items as $record) {
            $parts = $this->splitFingerprint($record->fingerprint);

            if ($parts === null) {
                continue;
            }

            [$namespace, $id] = $parts;
            $class = $this->normalizeClass($namespace);

            if ($class === null) {
                continue;
            }

            if (! isset($grouped[$class])) {
                $grouped[$class] = [];
            }

            $grouped[$class][] = $id;
        }

        return $grouped;
    }

    /**
     * Checks if any record belongs to the given namespace.
     *
     * @param  string  $namespace  The namespace to check
     * @return bool True if at least one record belongs to the namespace
     */
    public function belongsToNamespace(string $namespace): bool
    {
        $prefix = $namespace.self::FINGERPRINT_DELIMITER;

        foreach ($this->items as $record) {
            if (str_starts_with($record->fingerprint, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if any record belongs to any of the given namespaces.
     *
     * @param  string[]  $namespaces  Array of namespace strings
     * @return bool True if at least one record belongs to any namespace
     */
    public function belongsToAnyNamespace(array $namespaces): bool
    {
        $prefixes = array_map(
            fn (string $namespace): string => $namespace.self::FINGERPRINT_DELIMITER,
            $namespaces
        );

        foreach ($this->items as $record) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($record->fingerprint, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Groups records by their namespace.
     *
     * @return array<string, self> Associative array of namespace to collection
     */
    public function groupByNamespace(): array
    {
        $groups = [];

        foreach ($this->items as $record) {
            $namespace = $this->extractNamespace($record->fingerprint);

            if ($namespace === null) {
                continue;
            }

            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }

            $groups[$namespace]->add($record);
        }

        return $groups;
    }

    /**
     * Extracts the namespace from a fingerprint string.
     *
     * @param  string  $fingerprint  The fingerprint string
     * @return string|null The namespace, or null if invalid format
     */
    private function extractNamespace(string $fingerprint): ?string
    {
        $parts = explode(self::FINGERPRINT_DELIMITER, $fingerprint);

        return count($parts) === 2 ? $parts[0] : null;
    }

    /**
     * Extracts the ID from a fingerprint string.
     *
     * @param  string  $fingerprint  The fingerprint string
     * @return string|null The ID, or null if invalid format
     */
    private function extractId(string $fingerprint): ?string
    {
        $parts = explode(self::FINGERPRINT_DELIMITER, $fingerprint);

        return count($parts) === 2 ? $parts[1] : null;
    }

    /**
     * Splits a fingerprint into its components.
     *
     * @param  string  $fingerprint  The fingerprint string
     * @return array{string, string}|null Array of [namespace, id], or null if invalid
     */
    private function splitFingerprint(string $fingerprint): ?array
    {
        $parts = explode(self::FINGERPRINT_DELIMITER, $fingerprint);

        if (count($parts) !== 2) {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Normalizes a namespace to a fully qualified class name.
     *
     * @param  string  $namespace  The namespace string
     * @return string|null The normalized class name, or null if class doesn't exist
     */
    private function normalizeClass(string $namespace): ?string
    {
        $class = str_replace('.', '\\', $namespace);

        if (! class_exists($class)) {
            return null;
        }

        return $class;
    }

    /**
     * Loads models grouped by class with eager loading.
     *
     * @param  array<string, array<int|string>>  $groupedIds  Class to IDs mapping
     * @param  string[]  $with  Relations to eager load
     * @return array<string, Model> Associative array of model key to model instance
     *
     * @throws InvalidArgumentException If a requested relation doesn't exist
     */
    private function loadModels(array $groupedIds, array $with): array
    {
        $models = [];

        foreach ($groupedIds as $class => $ids) {
            $query = $class::whereIn('id', $ids);

            if (! empty($with)) {
                $this->validateRelations($class, $with);
                $query->with($with);
            }

            foreach ($query->get() as $model) {
                $key = get_class($model).self::FINGERPRINT_DELIMITER.$model->getKey();
                $models[$key] = $model;
            }
        }

        return $models;
    }

    /**
     * Builds an ordered result collection matching the original order.
     *
     * @param  array<string, Model>  $models  Associative array of model key to model
     * @return Collection<int, Model> Ordered collection of models
     */
    private function buildOrderedResult(array $models): Collection
    {
        $result = [];

        foreach ($this->items as $record) {
            $parts = $this->splitFingerprint($record->fingerprint);

            if ($parts === null) {
                continue;
            }

            [$namespace, $id] = $parts;
            $class = $this->normalizeClass($namespace);

            if ($class === null) {
                continue;
            }

            $key = $class.self::FINGERPRINT_DELIMITER.$id;

            if (isset($models[$key])) {
                $result[] = $models[$key];
            }
        }

        return new Collection($result);
    }

    /**
     * Validates that all requested relations exist on the model class.
     *
     * @param  string  $class  The model class name
     * @param  string[]  $with  Relations to validate
     *
     * @throws InvalidArgumentException If a relation doesn't exist
     */
    private function validateRelations(string $class, array $with): void
    {
        $invalidRelations = [];

        foreach ($with as $relation) {
            $mainRelation = explode('.', $relation)[0];

            if (! method_exists($class, $mainRelation)) {
                $invalidRelations[] = $relation;
            }
        }

        if (! empty($invalidRelations)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Relations [%s] do not exist on model [%s]',
                    implode(', ', $invalidRelations),
                    $class
                )
            );
        }
    }
}
