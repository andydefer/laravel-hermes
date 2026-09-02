<?php

// src/Datas/MatchData.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Data representing a single match within a search result.
 *
 * Contains the field where the match was found, the original text value,
 * and the similarity score for this specific match.
 */
final class MatchData extends AbstractData
{
    public function __construct(
        public readonly string $field,
        public readonly string $originalText,
        public readonly float $similarity,
    ) {}
}
