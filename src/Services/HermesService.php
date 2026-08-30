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

/**
 * Main service implementation for Hermes operations.
 *
 * Orchestrates completion, suggestion, and search functionalities
 * using the Hermes repository and similarity calculator.
 */
final class HermesService implements HermesInterface
{
    /** Multiplier for the completion token limit to allow for better results. */
    private const TOKEN_LIMIT_MULTIPLIER = 2;

    /** Multiplier for the suggestion token limit to allow for better results. */
    private const SUGGESTION_LIMIT_MULTIPLIER = 10;

    public function __construct(
        private readonly HermesRepositoryInterface $hermesRepository,
        private readonly TextNormalizerInterface $normalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly SimilarityCalculatorService $similarityCalculator,
        private readonly SimilarityConfig $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection
    {
        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {

            return new CompletionResultRecordCollection;
        }

        $allResults = [];
        $tokenLimit = $request->limit * self::TOKEN_LIMIT_MULTIPLIER;

        foreach ($ngrams as $ngram) {
            $fields = $request->query->getFieldsForNgram($ngram);
            $fieldsCollection = $this->createStringCollection($fields);

            $termNgrams = $this->generateTermNgrams($ngram);

            if (empty($termNgrams)) {

                continue;
            }

            $tokens = $this->hermesRepository->findTokensByNgrams(
                $termNgrams,
                $request->contexts,
                $fieldsCollection,
                $tokenLimit
            );

            foreach ($tokens as $token) {
                $similarity = $this->calculateSimilarity($ngram, $token->original_text);
                $key = $token->document_id;

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

        $collection = $this->buildCompletionResultCollection($allResults);
        $sorted = $this->sortBySimilarityDescending($collection->toArray());

        return $this->sliceCollection($sorted, $request->limit, CompletionResultRecordCollection::class);
    }

    /**
     * {@inheritDoc}
     */
    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection
    {
        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {
            return new SuggestionResultRecordCollection;
        }

        $allResults = [];
        $candidateLimit = $request->limit * self::SUGGESTION_LIMIT_MULTIPLIER;

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
                $candidateLimit
            );

            foreach ($candidates as $token) {
                $score = $this->similarityCalculator->calculateSimilarity(
                    $ngram,
                    $token->original_text
                );

                if ($score < $request->min_similarity) {
                    continue;
                }

                $key = $token->document_id;

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

        $collection = $this->buildSuggestionResultCollection($allResults);
        $sorted = $this->sortBySimilarityDescending($collection->toArray());

        return $this->sliceCollection($sorted, $request->limit, SuggestionResultRecordCollection::class);
    }

    /**
     * {@inheritDoc}
     */
    public function search(SearchRequestRecord $request): SearchResultRecordCollection
    {
        $ngrams = $request->query->getNgrams();

        if (empty($ngrams)) {
            return new SearchResultRecordCollection;
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

            foreach ($grouped as $documentId => $data) {
                if (! isset($documents[$documentId])) {
                    $documents[$documentId] = [
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

                    $documents[$documentId]['matches'][] = [
                        'field' => $token['field'],
                        'original_text' => $token['original_text'],
                        'similarity' => $score,
                    ];

                    $documents[$documentId]['similarities'][] = $score;
                }
            }
        }

        $collection = $this->buildSearchResultCollection($documents);
        $sorted = $this->sortBySimilarityDescending($collection->toArray());

        return $this->sliceCollection($sorted, $request->limit, SearchResultRecordCollection::class);
    }

    /**
     * Generates lexical and metaphone n-grams from a term.
     *
     * @param  mixed  $term  The term to generate n-grams from
     * @return array<string> Generated n-grams
     *
     * @throws \InvalidArgumentException If the term is not a string
     */
    private function generateTermNgrams(mixed $term): array
    {
        // ✅ Si ce n'est pas une string → Exception
        if (! is_string($term)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot generate n-grams from non-string value. Got: %s. '
                .'Only string values should be indexed. Numeric values should be moved to getIndexableCluster().',
                get_debug_type($term)
            ));
        }

        $term = (string) $term;
        $normalizedTerm = $this->normalizer->normalize($term);

        $lexicalNgrams = $this->ngramGenerator->generate(
            $normalizedTerm,
            $this->config->getGramMinSize(),
            $this->config->getGramMaxSize(),
            NormalizationMode::WITH_NORMALIZATION
        )->toArray();

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
     * Creates a StringTypedCollection from an array of field names.
     *
     * @param  array<string>  $fields  Field names
     * @return StringTypedCollection|null Collection or null if empty
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

    /**
     * Calculates similarity between a query and a token.
     *
     * @param  string  $query  The query text
     * @param  string  $token  The token text
     * @return float Similarity score between 0.0 and 1.0
     */
    private function calculateSimilarity(string $query, string $token): float
    {
        $normalizedQuery = $this->normalizer->normalize($query);
        $normalizedToken = $this->normalizer->normalize($token);

        return $this->similarityCalculator->calculateSimilarity($normalizedQuery, $normalizedToken);
    }

    /**
     * Builds a CompletionResultRecordCollection from aggregated results.
     *
     * @param  array<string, array>  $allResults  Aggregated results
     */
    private function buildCompletionResultCollection(array $allResults): CompletionResultRecordCollection
    {
        $collection = new CompletionResultRecordCollection;

        foreach ($allResults as $data) {
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

        return $collection;
    }

    /**
     * Builds a SuggestionResultRecordCollection from aggregated results.
     *
     * @param  array<string, array>  $allResults  Aggregated results
     */
    private function buildSuggestionResultCollection(array $allResults): SuggestionResultRecordCollection
    {
        $collection = new SuggestionResultRecordCollection;

        foreach ($allResults as $data) {
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

        return $collection;
    }

    /**
     * Builds a SearchResultRecordCollection from aggregated documents.
     *
     * @param  array<string, array>  $documents  Aggregated documents
     */
    private function buildSearchResultCollection(array $documents): SearchResultRecordCollection
    {
        $collection = new SearchResultRecordCollection;

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

        return $collection;
    }

    /**
     * Sorts items by similarity in descending order.
     *
     * @param  array  $items  Items with a 'similarity' property
     * @return array Sorted items
     */
    private function sortBySimilarityDescending(array $items): array
    {
        usort($items, fn ($a, $b) => $b->similarity <=> $a->similarity);

        return $items;
    }

    /**
     * Slices a collection to the given limit and creates a new collection.
     *
     * @param  array  $items  Items to slice
     * @param  int  $limit  Maximum number of items
     * @param  string  $collectionClass  Target collection class
     * @return object The sliced collection
     */
    private function sliceCollection(array $items, int $limit, string $collectionClass): object
    {
        $collection = new $collectionClass;

        foreach (array_slice($items, 0, $limit) as $item) {
            $collection->add($item);
        }

        return $collection;
    }
}
