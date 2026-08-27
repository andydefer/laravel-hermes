<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;

/**
 * Value Object representing an enriched search result.
 *
 * Wraps a SearchResultRecord with additional computed data including
 * the best matches per field and a rounded similarity score.
 *
 * @example
 * $vo = new SearchResultVO(
 *     record: $searchResultRecord,
 *     data: $doctorData
 * );
 * $vo->getValue(); // ['data' => [...], 'similarity' => 0.95, 'matches' => [...], 'fingerprint' => 'App\\Models\\User|1']
 */
final class SearchResultVO extends AbstractValueObject
{
    private float $similarity;

    private array $bestMatches = [];

    private AbstractData $data;

    private string $fingerprint;

    private string $documentId;

    /**
     * Initializes the value object with a record and its data representation.
     *
     * @param  SearchResultRecord  $record  The search result record
     * @param  AbstractData  $data  The hydrated data object
     */
    public function __construct(
        private readonly SearchResultRecord $record,
        AbstractData $data,
    ) {
        $this->similarity = round($record->similarity, 2);
        $this->fingerprint = $record->fingerprint;
        $this->documentId = $record->document_id;
        $this->data = $data;
        $this->bestMatches = $this->extractBestMatches();
    }

    /**
     * Extracts the best match per field from the record's matches.
     *
     * For each field, only the match with the highest similarity is kept.
     *
     * @return array<int, array{field: string, original_text: string, similarity: float}>
     */
    private function extractBestMatches(): array
    {
        $bestMatches = [];

        foreach ($this->record->matches as $match) {
            $field = $match->field;

            if (! isset($bestMatches[$field]) || $match->similarity > $bestMatches[$field]['similarity']) {
                $bestMatches[$field] = [
                    'field' => $field,
                    'original_text' => $match->original_text,
                    'similarity' => round($match->similarity, 2),
                ];
            }
        }

        return array_values($bestMatches);
    }

    /**
     * Returns the fingerprint of the search result.
     *
     * @return string The fingerprint (e.g., 'App\\Models\\User|1')
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Returns the document ID of the search result.
     *
     * @return string The document ID
     */
    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /**
     * Returns the value object as a StrictAssociative array.
     *
     * @return StrictAssociative<string, mixed> The structured value
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from([
            'document_id' => $this->documentId,
            'data' => $this->data,
            'similarity' => $this->similarity,
            'matches' => $this->bestMatches,
            'fingerprint' => $this->fingerprint,
        ]);
    }

    /**
     * Converts the value object to a plain array.
     *
     * @return array<string, mixed> The array representation
     */
    public function toArray(): array
    {
        return $this->getValue()->toArray();
    }

    /**
     * Returns the similarity score.
     *
     * @return float The similarity score rounded to 2 decimals
     */
    public function getSimilarity(): float
    {
        return $this->similarity;
    }

    /**
     * Returns the best matches per field.
     *
     * @return array<int, array{field: string, original_text: string, similarity: float}>
     */
    public function getBestMatches(): array
    {
        return $this->bestMatches;
    }

    /**
     * Returns the underlying data object.
     *
     * @return AbstractData The data object
     */
    public function getData(): AbstractData
    {
        return $this->data;
    }
}
