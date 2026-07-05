<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class MatchRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $field,
        public readonly string $original_text,
        public readonly float $similarity,
    ) {}
}
