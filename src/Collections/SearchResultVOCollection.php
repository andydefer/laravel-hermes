<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\DataCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\ValueObjects\SearchResultVO;
use Illuminate\Database\Eloquent\Collection;

/**
 * Specialized collection for SearchResultVO objects.
 *
 * Provides type-safe operations for managing search result value objects,
 * including data extraction, filtering by similarity and namespace,
 * and grouping capabilities.
 *
 * @method SearchResultVO|null first()
 * @method SearchResultVO|null last()
 * @method SearchResultVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 */
final class SearchResultVOCollection extends AbstractTypedCollection
{
    /**
     * The delimiter used to separate namespace and ID in fingerprints.
     */
    private const FINGERPRINT_DELIMITER = '|';

    /**
     * Initializes an empty collection of SearchResultVO items.
     */
    public function __construct()
    {
        parent::__construct(SearchResultVO::class);
    }

    /**
     * Extracts all data objects from the collection.
     *
     * @return DataCollection Collection of data objects
     */
    public function getDatas(): DataCollection
    {
        $dataClasses = [];

        foreach ($this->items as $vo) {
            $data = $vo->getValue()['data'];
            $dataClasses[] = get_class($data);
        }

        $uniqueClasses = array_unique($dataClasses);

        $collection = new DataCollection(...$uniqueClasses);

        foreach ($this->items as $vo) {
            $collection->add($vo->getValue()['data']);
        }

        return $collection;
    }

    /**
     * Extracts all fingerprints from the collection.
     *
     * @return StringTypedCollection Collection of fingerprint strings
     */
    public function getFingerprints(): StringTypedCollection
    {
        $collection = new StringTypedCollection;

        foreach ($this->items as $vo) {
            $collection->add($vo->getValue()['fingerprint']);
        }

        return $collection;
    }

    /**
     * Extracts all match records from the collection.
     *
     * @return MatchRecordCollection Collection of match records
     */
    public function getMatches(): MatchRecordCollection
    {
        $collection = new Sequential;

        foreach ($this->items as $vo) {
            foreach ($vo->getValue()['matches'] as $match) {
                $collection = $collection->add($match);
            }
        }

        return MatchRecordCollection::from($collection);
    }

    /**
     * Filters the collection to results meeting the minimum similarity score.
     *
     * @param  float  $minSimilarity  The minimum similarity threshold (0.0 to 1.0)
     * @return self A new collection containing only results above the threshold
     */
    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(
            fn (SearchResultVO $vo): bool => $vo->getValue()['similarity'] >= $minSimilarity
        );
    }

    /**
     * Filters the collection to results belonging to the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by
     * @return self A new collection containing only matching results
     */
    public function filterByNamespace(string $namespace): self
    {
        $prefix = $namespace.self::FINGERPRINT_DELIMITER;

        return $this->filter(
            fn (SearchResultVO $vo): bool => str_starts_with($vo->getValue()['fingerprint'], $prefix)
        );
    }

    /**
     * Filters the collection to results belonging to any of the given namespaces.
     *
     * @param  string[]  $namespaces  Array of namespace strings
     * @return self A new collection containing only matching results
     */
    public function filterByNamespaces(array $namespaces): self
    {
        $prefixes = array_map(
            fn (string $namespace): string => $namespace.self::FINGERPRINT_DELIMITER,
            $namespaces
        );

        return $this->filter(
            function (SearchResultVO $vo) use ($prefixes): bool {
                $fingerprint = $vo->getValue()['fingerprint'];

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($fingerprint, $prefix)) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Groups results by their namespace.
     *
     * @return StrictAssociative<string, self> Associative array of namespace to collection
     */
    public function groupByNamespace(): StrictAssociative
    {
        $groups = [];

        foreach ($this->items as $vo) {
            $fingerprint = $vo->getValue()['fingerprint'];
            $parts = explode(self::FINGERPRINT_DELIMITER, $fingerprint);

            if (count($parts) === 2) {
                $namespace = $parts[0];

                if (! isset($groups[$namespace])) {
                    $groups[$namespace] = new self;
                }

                $groups[$namespace]->add($vo);
            }
        }

        return StrictAssociative::from($groups);
    }

    /**
     * Extracts all similarity scores from the collection.
     *
     * @return float[] Array of similarity scores
     */
    public function getSimilarities(): array
    {
        return array_map(
            fn (SearchResultVO $vo): float => $vo->getValue()['similarity'],
            $this->items
        );
    }

    /**
     * Returns the result with the highest similarity score.
     *
     * @return SearchResultVO|null The best match, or null if collection is empty
     */
    public function getBestMatch(): ?SearchResultVO
    {
        if ($this->isEmpty()) {
            return null;
        }

        $best = $this->items[0];

        foreach ($this->items as $vo) {
            if ($vo->getValue()['similarity'] > $best->getValue()['similarity']) {
                $best = $vo;
            }
        }

        return $best;
    }

    /**
     * Extracts all data arrays from the collection.
     *
     * @return array<array<string, mixed>> Array of data arrays
     */
    public function getDataArrays(): array
    {
        return array_map(
            fn (SearchResultVO $vo): array => $vo->getValue()['data']->toArray(),
            $this->items
        );
    }

    /**
     * Extracts all namespaces from the collection.
     *
     * @return string[] Array of namespace strings
     */
    public function getNamespaces(): array
    {
        $namespaces = [];

        foreach ($this->items as $vo) {
            $fingerprint = $vo->getValue()['fingerprint'];
            $parts = explode(self::FINGERPRINT_DELIMITER, $fingerprint);

            if (count($parts) === 2) {
                $namespace = $parts[0];

                if (! in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                }
            }
        }

        return $namespaces;
    }
}
