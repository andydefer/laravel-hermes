<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use Illuminate\Support\Collection;

/**
 * Repository interface for querying indexed tokens.
 *
 * Defines methods for finding tokens by n-grams with context filtering,
 * grouping by document, and counting matches.
 */
interface HermesRepositoryInterface
{
    /**
     * Finds tokens matching the given n-grams with optional filters.
     *
     * @param  array<string>  $ngrams  The n-grams to search for
     * @param  ContextFilterVOCollection|null  $contexts  Context filters (OR logic between contexts)
     * @param  StringTypedCollection|null  $fields  Field names to restrict the search
     * @param  int  $limit  Maximum number of results
     * @param  bool  $withDocument  Whether to eager load the related document
     * @return Collection Collection of token models
     */
    public function findTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        int $limit = 100,
        bool $withDocument = false
    ): Collection;

    /**
     * Retrieves all distinct tokens matching the given n-grams.
     *
     * @param  array<string>  $ngrams  The n-grams to search for
     * @param  ContextFilterVOCollection|null  $contexts  Context filters (OR logic between contexts)
     * @param  StringTypedCollection|null  $fields  Field names to restrict the search
     * @return Collection Collection of distinct token models
     */
    public function getAllTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): Collection;

    /**
     * Retrieves tokens grouped by their associated document.
     *
     * @param  array<string>  $ngrams  The n-grams to search for
     * @param  ContextFilterVOCollection|null  $contexts  Context filters (OR logic between contexts)
     * @param  StringTypedCollection|null  $fields  Field names to restrict the search
     * @param  float  $minSimilarity  Minimum similarity threshold (reserved for future use)
     * @return array<string, array> Array grouped by document_id with document metadata and tokens
     */
    public function getTokensGroupedByDocument(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        float $minSimilarity = 0.0
    ): array;

    /**
     * Counts distinct tokens matching the given n-grams.
     *
     * @param  array<string>  $ngrams  The n-grams to search for
     * @param  ContextFilterVOCollection|null  $contexts  Context filters (OR logic between contexts)
     * @param  StringTypedCollection|null  $fields  Field names to restrict the search
     * @return int The number of distinct matching tokens
     */
    public function countTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): int;

    /**
     * Finds documents by their IDs.
     *
     * @param  array<string>  $documentIds  Array of document IDs
     * @return Collection Collection of document models
     */
    public function findDocumentsByIds(array $documentIds): Collection;
}
