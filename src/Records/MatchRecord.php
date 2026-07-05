<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record representing a single match within a search result.
 *
 * Contains the field where the match was found, the original text value,
 * and the similarity score for this specific match.
 */
final class MatchRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $field,
        public readonly string $original_text,
        public readonly float $similarity,
    ) {}
}
