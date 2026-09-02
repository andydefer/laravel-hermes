<?php

// src/Collections/MatchDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Datas\MatchData;

/**
 * Collection of MatchData objects.
 *
 * Provides type-safe collection operations for managing and querying
 * match data, including filtering by field or similarity threshold.
 *
 * @method MatchData|null first()
 * @method MatchData|null last()
 * @method MatchData|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class MatchDataCollection extends AbstractTypedCollection
{
    /**
     * Initializes an empty collection of match data.
     */
    public function __construct()
    {
        parent::__construct(MatchData::class);
    }

    /**
     * Filters matches by field name.
     *
     * @param  string  $field  The field name to filter by
     * @return self A new collection containing only matches with the given field
     */
    public function filterByField(string $field): self
    {
        return $this->filter(
            fn (MatchData $match): bool => $match->field === $field
        );
    }

    /**
     * Filters matches by minimum similarity threshold.
     *
     * @param  float  $minSimilarity  The minimum similarity threshold
     * @return self A new collection containing only matches above the threshold
     */
    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(
            fn (MatchData $match): bool => $match->similarity >= $minSimilarity
        );
    }

    /**
     * Returns the match with the highest similarity score.
     *
     * @return MatchData|null The best match, or null if collection is empty
     */
    public function getBestMatch(): ?MatchData
    {
        if ($this->isEmpty()) {
            return null;
        }

        $best = $this->items[0];

        foreach ($this->items as $match) {
            if ($match->similarity > $best->similarity) {
                $best = $match;
            }
        }

        return $best;
    }

    /**
     * Extracts all field names from the collection.
     *
     * @return string[] Array of field names
     */
    public function getFields(): array
    {
        return array_map(
            fn (MatchData $match): string => $match->field,
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
            fn (MatchData $match): float => $match->similarity,
            $this->items
        );
    }
}
