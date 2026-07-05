<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $document_id,
        public readonly string $fingerprint,
        public readonly StrictAssociative $data,
        public readonly array $matches,
        public readonly float $similarity,
    ) {}
}
