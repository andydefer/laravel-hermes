<?php

// src/Collections/SearchResultDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Datas\SearchResultData;

/**
 * Collection of SearchResultData objects.
 *
 * Provides type-safe collection operations for managing and querying
 * search results, including filtering by similarity threshold
 * and extracting specific record properties.
 *
 * @method SearchResultData|null first()
 * @method SearchResultData|null last()
 * @method SearchResultData|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class SearchResultDataCollection extends AbstractTypedCollection
{
    /**
     * Initializes an empty collection of search result data.
     */
    public function __construct()
    {
        parent::__construct(SearchResultData::class);
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
            fn (SearchResultData $record): bool => $record->similarity >= $minSimilarity
        );
    }

    /**
     * Extracts all document identifiers from the collection.
     *
     * @return string[] Array of document IDs
     */
    public function getDocumentIds(): array
    {
        return array_map(
            fn (SearchResultData $record): string => $record->documentId,
            $this->items
        );
    }

    /**
     * Extracts all fingerprints from the collection.
     *
     * @return string[] Array of fingerprints
     */
    public function getFingerprints(): array
    {
        return array_map(
            fn (SearchResultData $record): string => $record->fingerprint,
            $this->items
        );
    }

    /**
     * Extracts all similarity scores from the collection.
     *
     * @return float[] Array of similarity scores
     */
    public function getSimilarities(): array
    {
        return array_map(
            fn (SearchResultData $record): float => $record->similarity,
            $this->items
        );
    }

    /**
     * Returns the record with the highest similarity score.
     *
     * @return SearchResultData|null The best match, or null if collection is empty
     */
    public function getBestMatch(): ?SearchResultData
    {
        if ($this->isEmpty()) {
            return null;
        }

        $best = $this->items[0];

        foreach ($this->items as $record) {
            if ($record->similarity > $best->similarity) {
                $best = $record;
            }
        }

        return $best;
    }

    /**
     * Groups records by their fingerprint.
     *
     * @return array<string, self> Associative array of fingerprint to collection
     */
    public function groupByFingerprint(): array
    {
        $groups = [];

        foreach ($this->items as $record) {
            $fingerprint = $record->fingerprint;

            if (! isset($groups[$fingerprint])) {
                $groups[$fingerprint] = new self;
            }

            $groups[$fingerprint]->add($record);
        }

        return $groups;
    }

    /**
     * Plucks a specific field from the data of each record.
     *
     * @param  string  $field  The field name to pluck (supports dot notation)
     * @return array Array of field values
     */
    public function pluckDataField(string $field): array
    {
        return array_map(
            fn (SearchResultData $record): mixed => data_get($record->data->toArray(), $field),
            $this->items
        );
    }
}
