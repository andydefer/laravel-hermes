<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Contracts\Repositories\HermesRepositoryInterface;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class HermesRepository implements HermesRepositoryInterface
{
    public function __construct(
        private readonly IndexedTokenRepository $tokenRepository,
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
            $docId = $token->document_id;

            if (! isset($grouped[$docId])) {
                $grouped[$docId] = [
                    'document_id' => $docId,
                    'fingerprint' => $token->document->fingerprint,
                    'data' => $token->document->data,
                    'tokens' => [],
                ];
            }

            $grouped[$docId]['tokens'][] = [
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

    private function applyFilters(Builder $query, ?ContextFilterVOCollection $contexts, ?StringTypedCollection $fields): void
    {
        if ($fields !== null && ! $fields->isEmpty()) {
            $query->whereIn('field', $fields->toArray());
        }

        if ($contexts === null || $contexts->isEmpty()) {
            return;
        }

        $query->where(function ($subQuery) use ($contexts) {
            foreach ($contexts as $context) {
                $subQuery->orWhere(function ($q) use ($context) {
                    if ($context->hasNamespace()) {
                        $q->whereHas('document', function ($doc) use ($context) {
                            $doc->where('fingerprint', 'LIKE', $context->namespace.'|%');
                        });
                    }

                    if ($context->hasCluster()) {
                        $q->whereHas('document', function ($doc) use ($context) {
                            $doc->where('cluster', 'LIKE', '%'.$context->cluster.'%');
                        });
                    }
                });
            }
        });
    }
}
