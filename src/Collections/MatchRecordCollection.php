<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;

/**
 * Collection of match records returned from search operations.
 *
 * Provides type-safe collection operations for managing and querying
 * match results, including filtering by field, similarity threshold,
 * and statistical analysis.
 *
 * @method MatchRecord|null first()
 * @method MatchRecord|null last()
 * @method MatchRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method self merge(AbstractTypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class MatchRecordCollection extends AbstractTypedCollection
{
    /**
     * Initializes an empty collection of match records.
     */
    public function __construct()
    {
        parent::__construct(MatchRecord::class);
    }

    /**
     * Extracts all field names from the collection.
     *
     * @return string[] Array of field names
     */
    public function getFields(): array
    {
        return array_map(
            fn (MatchRecord $record): string => $record->field,
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
            fn (MatchRecord $record): string => $record->original_text,
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
            fn (MatchRecord $record): float => $record->similarity,
            $this->items
        );
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
            fn (MatchRecord $record): bool => $record->field === $field
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
            fn (MatchRecord $record): bool => $record->similarity >= $minSimilarity
        );
    }

    /**
     * Calculates the average similarity score across all records.
     *
     * @return float The average similarity, or 0.0 if collection is empty
     */
    public function getAverageSimilarity(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $total = array_sum($this->getSimilarities());

        return $total / $this->count();
    }

    /**
     * Returns the record with the highest similarity score.
     *
     * @return MatchRecord|null The best match, or null if collection is empty
     */
    public function getBestMatch(): ?MatchRecord
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

    /**
     * Sorts records by similarity score in descending order (highest first).
     *
     * @return self A new collection sorted by similarity (descending)
     */
    public function sortBySimilarityDesc(): self
    {
        $items = $this->items;

        usort(
            $items,
            fn (MatchRecord $a, MatchRecord $b): int => $b->similarity <=> $a->similarity
        );

        $collection = new self;
        $collection->items = $items;

        return $collection;
    }
}
