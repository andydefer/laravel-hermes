<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Contracts\Repositories\HermesRepositoryInterface;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HermesRepository implements HermesRepositoryInterface
{
    public function __construct(
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly IndexedDocumentRepository $documentRepository,
    ) {}

    public function findTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        int $limit = 100,
        bool $withDocument = false
    ): Collection {
        $query = $this->tokenRepository->getModel()->newQuery()
            ->whereIn('token', $ngrams)
            ->select('id', 'document_id', 'token', 'original_text', 'field')
            ->distinct();

        if ($withDocument) {
            $query->with('document');
        }

        $this->applyFilters($query, $contexts, $fields);
        $query->limit($limit);

        return $query->get();
    }

    public function getAllTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): Collection {
        $query = $this->tokenRepository->getModel()->newQuery()
            ->whereIn('token', $ngrams)
            ->select('id', 'document_id', 'token', 'original_text', 'field')
            ->distinct();

        $this->applyFilters($query, $contexts, $fields);

        return $query->get();
    }

    public function getTokensGroupedByDocument(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null,
        float $minSimilarity = 0.0
    ): array {
        $query = $this->tokenRepository->getModel()->newQuery()
            ->with('document')
            ->whereIn('token', $ngrams)
            ->select('id', 'document_id', 'token', 'original_text', 'field')
            ->distinct();

        $this->applyFilters($query, $contexts, $fields);

        $tokens = $query->get();
        $grouped = [];

        foreach ($tokens as $token) {
            $documentId = $token->document_id;

            if (! isset($grouped[$documentId])) {
                $grouped[$documentId] = [
                    'document_id' => $documentId,
                    'fingerprint' => $token->document->fingerprint,
                    'data' => $token->document->data,
                    'tokens' => [],
                ];
            }

            $grouped[$documentId]['tokens'][] = [
                'id' => $token->id,
                'token' => $token->token,
                'original_text' => $token->original_text,
                'field' => $token->field,
            ];
        }

        return $grouped;
    }

    public function countTokensByNgrams(
        array $ngrams,
        ?ContextFilterVOCollection $contexts = null,
        ?StringTypedCollection $fields = null
    ): int {
        $query = $this->tokenRepository->getModel()->newQuery()
            ->whereIn('token', $ngrams)
            ->distinct();

        $this->applyFilters($query, $contexts, $fields);

        return $query->count('id');
    }

    public function findDocumentsByIds(array $documentIds): Collection
    {
        if (empty($documentIds)) {
            return collect();
        }

        return $this->documentRepository->findByIds($documentIds);
    }

    private function applyFilters(Builder $query, ?ContextFilterVOCollection $contexts, ?StringTypedCollection $fields): void
    {
        if ($fields !== null && ! $fields->isEmpty()) {
            $fieldsArray = $fields->toArray();
            $query->whereIn('field', $fieldsArray);
        }

        if ($contexts === null || $contexts->isEmpty()) {
            return;
        }

        $driverName = DB::connection()->getDriverName();

        if ($driverName === 'sqlite') {
            $this->applySqliteFilters($query, $contexts);
        } else {
            $this->applyMySqlFilters($query, $contexts);
        }
    }

    /**
     * Filtres pour SQLite
     */
    private function applySqliteFilters(Builder $query, ContextFilterVOCollection $contexts): void
    {
        $query->where(function ($subQuery) use ($contexts) {
            foreach ($contexts as $context) {
                $subQuery->orWhere(function ($filterQuery) use ($context) {
                    if ($context->hasNamespace()) {
                        $filterQuery->whereHas('document', function ($documentQuery) use ($context) {
                            $documentQuery->where('fingerprint', 'LIKE', $context->namespace.'|%');
                        });
                    }

                    if ($context->hasClusters()) {
                        $originalQuery = $context->getClusterQuery();

                        $filterQuery->whereHas('document', function ($documentQuery) use ($context, $originalQuery) {
                            $documentQuery->whereCluster($context->getClusterColumn(), $originalQuery);
                        });
                    }
                });
            }
        });
    }

    /**
     * Filtres pour MySQL
     */
    private function applyMySqlFilters(Builder $query, ContextFilterVOCollection $contexts): void
    {
        $query->where(function ($subQuery) use ($contexts) {
            foreach ($contexts as $context) {
                $subQuery->orWhere(function ($filterQuery) use ($context) {
                    if ($context->hasNamespace()) {
                        $filterQuery->whereHas('document', function ($documentQuery) use ($context) {
                            $namespace = addslashes($context->namespace);
                            $documentQuery->where('fingerprint', 'LIKE', $namespace.'|%');
                        });
                    }

                    if ($context->hasClusters()) {
                        $originalQuery = $context->getClusterQuery();

                        $filterQuery->whereHas('document', function ($documentQuery) use ($context, $originalQuery) {
                            $documentQuery->whereCluster($context->getClusterColumn(), $originalQuery);
                        });
                    }
                });
            }
        });
    }
}
