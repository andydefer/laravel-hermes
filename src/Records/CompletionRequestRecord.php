<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

/**
 * Request record for text completion operations.
 *
 * Contains the search query, result limit, and optional context filters
 * to restrict completion suggestions.
 */
final class CompletionRequestRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly int $limit = 10,
        public readonly ?ContextFilterVOCollection $contexts = null,
    ) {}
}
