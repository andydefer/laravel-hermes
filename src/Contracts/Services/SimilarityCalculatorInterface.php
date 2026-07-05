<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Services;

/**
 * Interface for text similarity calculation.
 *
 * Defines the contract for computing similarity between two text strings
 * using a combination of lexical and phonetic algorithms.
 */
interface SimilarityCalculatorInterface
{
    /**
     * Calculates the similarity between two text strings.
     *
     * Returns a value between 0.0 (completely different) and 1.0 (identical).
     * The calculation combines:
     * - Lexical similarity (n-grams, 60% default weight)
     * - Phonetic similarity (Metaphone, 40% default weight)
     * - Bonuses for common letters, bigrams, and Levenshtein proximity
     * - Length correction to penalize texts with poor coverage
     *
     * @param  string  $text1  First text to compare
     * @param  string  $text2  Second text to compare
     * @return float Similarity score between 0.0 and 1.0
     *
     * @example
     * $score = $calculator->calculateSimilarity('John Doe', 'Jon Doe');
     * // Returns ~0.85
     */
    public function calculateSimilarity(string $text1, string $text2): float;
}
