<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;

/**
 * Result record for full-text search operations.
 *
 * Contains the complete document information including all fields,
 * matches with their similarity scores, and an overall document similarity score.
 */
final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $document_id,
        public readonly string $fingerprint,
        public readonly StrictAssociative $data,
        public readonly MatchRecordCollection $matches,
        public readonly float $similarity,
    ) {}
}
