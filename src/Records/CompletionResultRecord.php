<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Result record for text completion operations.
 *
 * Contains the matched token, its associated document, field information,
 * and the similarity score for the completion.
 */
final class CompletionResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $token_id,
        public readonly string $document_id,
        public readonly string $token,
        public readonly string $original_text,
        public readonly string $field,
        public readonly float $similarity,
    ) {}
}
