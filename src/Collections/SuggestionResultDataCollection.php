<?php

// src/Collections/SuggestionResultDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Datas\SuggestionResultData;

/**
 * Collection of SuggestionResultData objects.
 *
 * Provides type-safe collection operations for managing and querying
 * suggestion results, including filtering by field, similarity threshold,
 * and extracting specific record properties.
 *
 * @method SuggestionResultData|null first()
 * @method SuggestionResultData|null last()
 * @method SuggestionResultData|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class SuggestionResultDataCollection extends AbstractTypedCollection
{
    /**
     * Initializes an empty collection of suggestion result data.
     */
    public function __construct()
    {
        parent::__construct(SuggestionResultData::class);
    }

    /**
     * Filters the collection to records matching the given field name.
     *
     * @param  string  $field  The field name to filter by (e.g., 'name', 'email')
     * @return self A new collection containing only matching records
     */
    public function filterByField(string $field): self
    {
        return $this->filter(
            fn (SuggestionResultData $record): bool => $record->field === $field
        );
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
            fn (SuggestionResultData $record): bool => $record->similarity >= $minSimilarity
        );
    }

    /**
     * Extracts all token values from the collection.
     *
     * @return string[] Array of token strings
     */
    public function getTokens(): array
    {
        return array_map(
            fn (SuggestionResultData $record): string => $record->token,
            $this->items
        );
    }

    /**
     * Extracts all original text values from the collection.
     *
     * @return string[] Array of original text strings
     */
    public function getOriginalTexts(): array
    {
        return array_map(
            fn (SuggestionResultData $record): string => $record->originalText,
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
            fn (SuggestionResultData $record): float => $record->similarity,
            $this->items
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
            fn (SuggestionResultData $record): string => $record->documentId,
            $this->items
        );
    }

    /**
     * Returns the record with the highest similarity score.
     *
     * @return SuggestionResultData|null The best match, or null if collection is empty
     */
    public function getBestMatch(): ?SuggestionResultData
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
     * Groups records by their document ID.
     *
     * @return array<string, self> Associative array of document ID to collection
     */
    public function groupByDocument(): array
    {
        $groups = [];

        foreach ($this->items as $record) {
            $documentId = $record->documentId;

            if (! isset($groups[$documentId])) {
                $groups[$documentId] = new self;
            }

            $groups[$documentId]->add($record);
        }

        return $groups;
    }

    /**
     * Groups records by their field name.
     *
     * @return array<string, self> Associative array of field name to collection
     */
    public function groupByField(): array
    {
        $groups = [];

        foreach ($this->items as $record) {
            $field = $record->field;

            if (! isset($groups[$field])) {
                $groups[$field] = new self;
            }

            $groups[$field]->add($record);
        }

        return $groups;
    }
}
