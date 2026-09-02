<?php

// src/Datas/SearchResultData.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Collections\MatchDataCollection;

/**
 * Data representing a full-text search result.
 *
 * Contains the complete document information including all fields,
 * matches with their similarity scores, and an overall document similarity score.
 */
final class SearchResultData extends AbstractData
{
    public function __construct(
        public readonly string $documentId,
        public readonly string $fingerprint,
        public readonly StrictAssociative $data,
        public readonly MatchDataCollection $matches,
        public readonly float $similarity,
    ) {}
}
