<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

/**
 * Request record for full-text search operations.
 *
 * Contains the search query, result limit, optional context filters,
 * minimum similarity threshold, and phonetic search toggle.
 */
final class SearchRequestRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly int $limit = 20,
        public readonly ?ContextFilterVOCollection $contexts = null,
        public readonly float $min_similarity = 0.3,
        public readonly bool $use_phonetic = true,
    ) {}
}
