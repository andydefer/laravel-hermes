<?php

// src/Datas/SuggestionResultData.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Data representing a text suggestion result.
 *
 * Contains the suggested token, its associated document, field information,
 * and the similarity score for the suggestion.
 */
final class SuggestionResultData extends AbstractData
{
    public function __construct(
        public readonly string $tokenId,
        public readonly string $documentId,
        public readonly string $token,
        public readonly string $originalText,
        public readonly string $field,
        public readonly float $similarity,
    ) {}
}
