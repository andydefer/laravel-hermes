<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

final class CompletionRequestRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly int $limit = 10,
        public readonly ?ContextFilterVOCollection $contexts = null,
    ) {}
}
