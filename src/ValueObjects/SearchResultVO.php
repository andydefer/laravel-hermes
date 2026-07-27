<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;

/**
 * Value Object représentant un résultat de recherche enrichi.
 *
 * @example
 * $vo = new SearchResultVO(
 *     record: $searchResultRecord,
 *     data: $doctorData
 * );
 * $vo->getValue(); // ['data' => [...], 'similarity' => 0.95, 'matches' => [...], 'fingerprint' => 'App.Models.User|1']
 */
final class SearchResultVO extends AbstractValueObject
{
    private float $similarity;

    private array $bestMatches = [];

    private AbstractData $data;

    private string $fingerprint;

    public function __construct(
        private readonly SearchResultRecord $record,
        AbstractData $data,
    ) {
        $this->similarity = round($record->similarity, 2);
        $this->fingerprint = $record->fingerprint;
        $this->data = $data;
        $this->bestMatches = $this->extractBestMatches();
    }

    /**
     * Extrait le meilleur match par champ.
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
     * Retourne le fingerprint.
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Retourne les données sous forme de StrictAssociative.
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from([
            'data' => $this->data,
            'similarity' => $this->similarity,
            'matches' => $this->bestMatches,
            'fingerprint' => ($this->fingerprint),
        ]);
    }

    public function toArray(): array
    {
        return $this->getValue()->toArray();
    }
}
