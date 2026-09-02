<?php

// src/Datas/CompletionResultData.php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Data representing a completion result.
 *
 * Contains the matched token, its associated document, field information,
 * and the similarity score for the completion.
 */
final class CompletionResultData extends AbstractData
{
    public function __construct(
        public readonly ?string $tokenId,
        public readonly string $documentId,
        public readonly string $token,
        public readonly string $originalText,
        public readonly string $field,
        public readonly float $similarity,
    ) {}
}
