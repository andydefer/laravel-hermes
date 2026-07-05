<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

/**
 * Request record for text suggestion operations.
 *
 * Contains the search query, result limit, optional context filters,
 * and minimum similarity threshold for suggestions.
 */
final class SuggestionRequestRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly int $limit = 10,
        public readonly ?ContextFilterVOCollection $contexts = null,
        public readonly float $min_similarity = 0.3,
    ) {}
}
