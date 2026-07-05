<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Configs;

/**
 * Interface for similarity calculation configuration.
 *
 * Defines all configurable parameters for the similarity algorithm:
 * - N-gram generation (min/max size)
 * - Vector dimension for hashing
 * - Weight distribution between textual and phonetic similarity
 * - Bonus multipliers for common letters and bigrams
 * - Letter and gram weighting
 * - Performance limits (max words, max pairs, timeout)
 * - Levenshtein bonus thresholds and values
 *
 * @author Andy Defer
 */
interface SimilarityConfigInterface
{
    /**
     * Returns the minimum word length to be considered valid.
     * Words below this length are merged with neighbors.
     *
     * @return int Minimum word length (default: 2)
     */
    public function getMinWordLength(): int;

    /**
     * Returns the textual (lexical) weight for similarity calculation.
     * Based on word n-grams.
     * Value between 0 and 1.
     *
     * @return float Textual weight (default: 0.6)
     */
    public function getTextualWeight(): float;

    /**
     * Returns the phonetic weight for similarity calculation.
     * Based on metaphone representation.
     * Value between 0 and 1.
     *
     * Note: The sum of textual + phonetic weights should ideally equal 1.
     *
     * @return float Phonetic weight (default: 0.4)
     */
    public function getPhoneticWeight(): float;

    /**
     * Returns the weight of an n-gram based on its length.
     * Longer n-grams are more specific and should have higher weight.
     *
     * @param  int  $gramLength  N-gram length (e.g., 2 for bigram, 3 for trigram)
     * @return float Weight for this n-gram length
     */
    public function getGramWeight(int $gramLength): float;

    /**
     * Returns the weight of a letter for inverse weighting.
     * More frequent letters have higher weights.
     *
     * Formula used: inverseWeight = 1 / (weight + 1)
     *
     * @param  string  $letter  The letter (single character)
     * @return float Letter weight (default: based on frequency)
     */
    public function getLetterWeight(string $letter): float;

    /**
     * Returns the minimum n-gram size to generate.
     *
     * @return int Minimum n-gram size (default: 2)
     */
    public function getGramMinSize(): int;

    /**
     * Returns the maximum n-gram size to generate.
     *
     * @return int Maximum n-gram size (default: 4)
     */
    public function getGramMaxSize(): int;

    /**
     * Returns the vector dimension for embeddings.
     *
     * @return int Vector dimension (default: 128)
     */
    public function getVectorDimension(): int;

    /**
     * Returns the bonus for each common unique letter between two words.
     *
     * @return float Bonus per common unique letter (default: 0.05)
     */
    public function getLetterBonus(): float;

    /**
     * Returns the bonus for each common bigram between two words.
     *
     * @return float Bonus per common bigram (default: 0.03)
     */
    public function getBigramBonus(): float;

    /**
     * Returns the maximum number of words to keep per text.
     * Words beyond this limit are sampled.
     *
     * @return int Maximum words per text (default: 50)
     */
    public function getMaxWords(): int;

    /**
     * Returns the maximum number of word pairs to process.
     * If exceeded, sampling is triggered.
     *
     * @return int Maximum word pairs (default: 2500)
     */
    public function getMaxPairs(): int;

    /**
     * Returns the timeout in seconds for similarity calculation.
     * After this time, the calculation stops and returns partial results.
     *
     * @return float Timeout in seconds (default: 0.5)
     */
    public function getTimeoutSeconds(): float;

    /**
     * Returns the maximum Levenshtein distance for metaphone bonus.
     * If metaphone distance is less than this value, bonus is applied.
     *
     * @return int Maximum metaphone distance for bonus (default: 3)
     */
    public function getMetaphoneBonusThreshold(): int;

    /**
     * Returns the bonus value for metaphone proximity.
     *
     * @return float Metaphone bonus value (default: 0.175)
     */
    public function getMetaphoneBonusValue(): float;

    /**
     * Returns the maximum Levenshtein distance for lexical bonus.
     * If lexical distance is less than this value, bonus is applied.
     *
     * @return int Maximum lexical distance for bonus (default: 3)
     */
    public function getLexicalBonusThreshold(): int;

    /**
     * Returns the bonus value for lexical proximity (medium).
     * Applied when distance is between 2 and threshold.
     *
     * @return float Lexical bonus value (medium) (default: 0.225)
     */
    public function getLexicalBonusMedium(): float;

    /**
     * Returns the bonus value for lexical proximity (high).
     * Applied when distance is less than 2.
     *
     * @return float Lexical bonus value (high) (default: 0.275)
     */
    public function getLexicalBonusHigh(): float;

    /**
     * Returns the maximum total Levenshtein bonus allowed.
     *
     * @return float Maximum Levenshtein bonus (default: 0.45)
     */
    public function getMaxLevenshteinBonus(): float;
}
