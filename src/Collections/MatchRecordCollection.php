<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;

/**
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
    public function __construct()
    {
        parent::__construct(MatchRecord::class);
    }

    public function getFields(): array
    {
        return array_map(fn (MatchRecord $match) => $match->field, $this->items);
    }

    public function getOriginalTexts(): array
    {
        return array_map(fn (MatchRecord $match) => $match->original_text, $this->items);
    }

    public function getSimilarities(): array
    {
        return array_map(fn (MatchRecord $match) => $match->similarity, $this->items);
    }

    public function filterByField(string $field): self
    {
        return $this->filter(fn (MatchRecord $match) => $match->field === $field);
    }

    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(fn (MatchRecord $match) => $match->similarity >= $minSimilarity);
    }

    public function getAverageSimilarity(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $total = array_sum($this->getSimilarities());

        return $total / $this->count();
    }
}
