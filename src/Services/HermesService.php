<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SuggestionResultRecordCollection;
use AndyDefer\LaravelHermes\Configs\SimilarityConfig;
use AndyDefer\LaravelHermes\Contracts\Repositories\HermesRepositoryInterface;
use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\CompletionResultRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionResultRecord;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Enums\NormalizationMode;

final class HermesService implements HermesInterface
{
    public function __construct(
        private readonly HermesRepositoryInterface $hermesRepository,
        private readonly TextNormalizerInterface $normalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly SimilarityCalculatorService $similarityCalculator,
        private readonly SimilarityConfig $config,
    ) {}

    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection
    {
        $collection = new CompletionResultRecordCollection;
        $allResults = [];

        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {
            return $collection;
        }

        foreach ($ngrams as $ngram) {
            $fields = $request->query->getFieldsForNgram($ngram);
            $fieldsCollection = $this->createStringCollection($fields);

            // Générer les n-grammes du terme
            $termNgrams = $this->generateTermNgrams($ngram);

            if (empty($termNgrams)) {
                continue;
            }

            $tokens = $this->hermesRepository->findTokensByNgrams(
                $termNgrams,
                $request->contexts,
                $fieldsCollection,
                $request->limit * 2
            );

            foreach ($tokens as $token) {
                $similarity = $this->calculateSimilarity($ngram, $token->original_text);

                $key = $token->id.'|'.$token->document_id;
                if (! isset($allResults[$key])) {
                    $allResults[$key] = [
                        'token_id' => $token->id,
                        'document_id' => $token->document_id,
                        'token' => $token->token,
                        'original_text' => $token->original_text,
                        'field' => $token->field,
                        'similarities' => [],
                    ];
                }
                $allResults[$key]['similarities'][] = $similarity;
            }
        }

        foreach ($allResults as $key => $data) {
            $avgSimilarity = array_sum($data['similarities']) / count($data['similarities']);

            $collection->add(CompletionResultRecord::from([
                'token_id' => $data['token_id'],
                'document_id' => $data['document_id'],
                'token' => $data['token'],
                'original_text' => $data['original_text'],
                'field' => $data['field'],
                'similarity' => $avgSimilarity,
            ]));
        }

        $sortedItems = $collection->toArray();
        usort($sortedItems, fn ($a, $b) => $b->similarity <=> $a->similarity);

        $result = new CompletionResultRecordCollection;
        foreach (array_slice($sortedItems, 0, $request->limit) as $item) {
            $result->add($item);
        }

        return $result;
    }

    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection
    {

        $collection = new SuggestionResultRecordCollection;
        $allResults = [];

        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {

            return $collection;
        }

        foreach ($ngrams as $ngram) {

            $fields = $request->query->getFieldsForNgram($ngram);
            $fieldsCollection = $this->createStringCollection($fields);

            $termNgrams = $this->generateTermNgrams($ngram);

            if (empty($termNgrams)) {

                continue;
            }

            $candidates = $this->hermesRepository->findTokensByNgrams(
                $termNgrams,
                $request->contexts,
                $fieldsCollection,
                $request->limit * 10
            );

            foreach ($candidates as $token) {
                $score = $this->similarityCalculator->calculateSimilarity(
                    $ngram,
                    $token->original_text
                );

                if ($score < $request->min_similarity) {

                    continue;
                }

                $key = $token->id.'|'.$token->document_id;
                if (! isset($allResults[$key])) {
                    $allResults[$key] = [
                        'token_id' => $token->id,
                        'document_id' => $token->document_id,
                        'token' => $token->token,
                        'original_text' => $token->original_text,
                        'field' => $token->field,
                        'scores' => [],
                    ];
                }
                $allResults[$key]['scores'][] = $score;
            }
        }

        foreach ($allResults as $key => $data) {
            $avgSimilarity = array_sum($data['scores']) / count($data['scores']);

            $collection->add(SuggestionResultRecord::from([
                'token_id' => $data['token_id'],
                'document_id' => $data['document_id'],
                'token' => $data['token'],
                'original_text' => $data['original_text'],
                'field' => $data['field'],
                'similarity' => $avgSimilarity,
            ]));
        }

        $sortedItems = $collection->toArray();
        usort($sortedItems, fn ($a, $b) => $b->similarity <=> $a->similarity);

        $result = new SuggestionResultRecordCollection;
        foreach (array_slice($sortedItems, 0, $request->limit) as $item) {
            $result->add($item);
        }

        return $result;
    }

    public function search(SearchRequestRecord $request): SearchResultRecordCollection
    {
        $collection = new SearchResultRecordCollection;

        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {
            return $collection;
        }

        $documents = [];

        foreach ($ngrams as $ngram) {
            $fields = $request->query->getFieldsForNgram($ngram);
            $fieldsCollection = $this->createStringCollection($fields);

            $termNgrams = $this->generateTermNgrams($ngram);

            if (empty($termNgrams)) {
                continue;
            }

            $grouped = $this->hermesRepository->getTokensGroupedByDocument(
                $termNgrams,
                $request->contexts,
                $fieldsCollection
            );

            foreach ($grouped as $docId => $data) {
                if (! isset($documents[$docId])) {
                    $documents[$docId] = [
                        'document_id' => $data['document_id'],
                        'fingerprint' => $data['fingerprint'],
                        'data' => $data['data'],
                        'matches' => [],
                        'similarities' => [],
                    ];
                }

                foreach ($data['tokens'] as $token) {
                    $score = $this->similarityCalculator->calculateSimilarity(
                        $ngram,
                        $token['original_text']
                    );

                    if ($score < $request->min_similarity) {
                        continue;
                    }

                    $documents[$docId]['matches'][] = [
                        'field' => $token['field'],
                        'original_text' => $token['original_text'],
                        'similarity' => $score,
                    ];

                    $documents[$docId]['similarities'][] = $score;
                }
            }
        }

        foreach ($documents as $doc) {
            if (empty($doc['similarities'])) {
                continue;
            }

            $avgSimilarity = array_sum($doc['similarities']) / count($doc['similarities']);

            $collection->add(SearchResultRecord::from([
                'document_id' => $doc['document_id'],
                'fingerprint' => $doc['fingerprint'],
                'data' => $doc['data'],
                'matches' => $doc['matches'],
                'similarity' => $avgSimilarity,
            ]));
        }

        $sortedItems = $collection->toArray();
        usort($sortedItems, fn ($a, $b) => $b->similarity <=> $a->similarity);

        $result = new SearchResultRecordCollection;
        foreach (array_slice($sortedItems, 0, $request->limit) as $item) {
            $result->add($item);
        }

        return $result;
    }

    /**
     * Génère les n-grammes lexicaux et métaphoniques d'un terme.
     *
     * @param  string  $term  Le terme à traiter
     * @return array<string> Les n-grammes générés
     */
    private function generateTermNgrams(string $term): array
    {
        $normalizedTerm = $this->normalizer->normalize($term);

        // N-grammes lexicaux
        $lexicalNgrams = $this->ngramGenerator->generate(
            $normalizedTerm,
            $this->config->getGramMinSize(),
            $this->config->getGramMaxSize(),
            NormalizationMode::WITH_NORMALIZATION
        )->toArray();

        // N-grammes métaphoniques
        $metaphone = metaphone($normalizedTerm);
        $phoneticNgrams = [];
        if ($metaphone !== '') {
            $phoneticNgrams = $this->ngramGenerator->generate(
                $metaphone,
                max(1, $this->config->getGramMinSize() - 1),
                $this->config->getGramMaxSize(),
                NormalizationMode::WITH_NORMALIZATION
            )->toArray();
        }

        return array_unique(array_merge($lexicalNgrams, $phoneticNgrams));
    }

    /**
     * Crée une StringTypedCollection à partir d'un tableau de strings.
     *
     * @param  array<string>  $fields
     */
    private function createStringCollection(array $fields): ?StringTypedCollection
    {
        if (empty($fields)) {
            return null;
        }

        $collection = new StringTypedCollection;
        foreach ($fields as $field) {
            $collection->add($field);
        }

        return $collection;
    }

    private function calculateSimilarity(string $query, string $token): float
    {
        $normalizedQuery = $this->normalizer->normalize($query);
        $normalizedToken = $this->normalizer->normalize($token);

        return $this->similarityCalculator->calculateSimilarity($normalizedQuery, $normalizedToken);
    }
}
