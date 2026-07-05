<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use Illuminate\Support\Collection;

interface HermesRepositoryInterface
{
    /**
     * Recherche les tokens correspondant aux n-grammes avec filtres.
     *
     * @param  array<string>  $ngrams  Les n-grammes à rechercher
     * @param  ContextFilterVOCollection|null  $contexts  Filtres de contexte
     * @param  StringTypedCollection|null  $fields  Filtres de champs
     * @param  int  $limit  Limite de résultats
     * @param  bool  $withDocument  Charger la relation document
     * @return Collection Les tokens trouvés
     */
    public function findTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        int $limit = 100,
        bool $withDocument = false
    ): Collection;

    /**
     * Récupère tous les tokens distincts pour un ensemble de n-grammes.
     *
     * @param  array<string>  $ngrams
     */
    public function getAllTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): Collection;

    /**
     * Récupère les tokens groupés par document.
     *
     * @param  array<string>  $ngrams
     * @return array<string, array> Tableau groupé par document_id
     */
    public function getTokensGroupedByDocument(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        float $minSimilarity = 0.0
    ): array;

    /**
     * Compte le nombre de tokens correspondants.
     *
     * @param  array<string>  $ngrams
     */
    public function countTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): int;
}
