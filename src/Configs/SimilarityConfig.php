<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Configs;

use AndyDefer\LaravelHermes\Contracts\Configs\SimilarityConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration for similarity calculation service.
 *
 * Provides all configurable parameters for the similarity algorithm:
 * - N-gram generation (min/max size)
 * - Vector dimension for hashing
 * - Weight distribution between textual and phonetic similarity
 * - Bonus multipliers for common letters and bigrams
 * - Letter and gram weighting
 * - Performance limits (max words, max pairs, timeout)
 * - Levenshtein bonus thresholds and values
 *
 * All values can be overridden via config/completion.php or environment variables.
 */
final class SimilarityConfig implements SimilarityConfigInterface
{
    // ============================================================================
    // Default values
    // ============================================================================

    private const DEFAULT_GRAM_MIN_SIZE = 2;

    private const DEFAULT_GRAM_MAX_SIZE = 4;

    private const DEFAULT_VECTOR_DIMENSION = 128;

    private const DEFAULT_TEXTUAL_WEIGHT = 0.6;

    private const DEFAULT_PHONETIC_WEIGHT = 0.4;

    private const DEFAULT_LETTER_BONUS = 0.05;

    private const DEFAULT_BIGRAM_BONUS = 0.03;

    private const DEFAULT_MIN_WORD_LENGTH = 2;

    private const DEFAULT_MAX_WORDS = 50;

    private const DEFAULT_MAX_PAIRS = 2500;

    private const DEFAULT_TIMEOUT_SECONDS = 0.5;

    // Levenshtein bonus defaults
    private const DEFAULT_METAPHONE_BONUS_THRESHOLD = 3;

    private const DEFAULT_METAPHONE_BONUS_VALUE = 0.175;

    private const DEFAULT_LEXICAL_BONUS_THRESHOLD = 3;

    private const DEFAULT_LEXICAL_BONUS_MEDIUM = 0.225;

    private const DEFAULT_LEXICAL_BONUS_HIGH = 0.275;

    private const DEFAULT_MAX_LEVENSHTEIN_BONUS = 0.45;

    private const DEFAULT_GRAM_WEIGHTS = [
        2 => 0.3,
        3 => 0.5,
        4 => 0.7,
        'default' => 1.0,
    ];

    private const DEFAULT_LETTER_WEIGHTS = [
        'e' => 15.0, 'a' => 7.5, 's' => 7.5, 'i' => 7.0,
        'n' => 7.0, 't' => 7.0, 'r' => 6.5, 'u' => 6.0,
        'l' => 5.0, 'o' => 5.0, 'd' => 3.5, 'c' => 3.5,
        'p' => 3.0, 'm' => 3.0, 'v' => 2.0,
        'q' => 1.0, 'g' => 1.0, 'b' => 1.0, 'f' => 1.0,
        'h' => 0.75, 'j' => 0.75,
        'z' => 0.25, 'w' => 0.25, 'k' => 0.25, 'y' => 0.25, 'x' => 0.5,
        'é' => 4.0, 'è' => 3.0, 'ê' => 2.0, 'à' => 1.5,
        'ù' => 1.0, 'ç' => 1.5, 'â' => 1.5, 'î' => 1.0,
        'ô' => 1.0, 'û' => 0.5, 'ë' => 0.5, 'ï' => 0.5, 'ü' => 0.5,
    ];

    // ============================================================================
    // Clamping bounds
    // ============================================================================

    private const CLAMP_GRAM_MIN_SIZE = [1, 10];

    private const CLAMP_GRAM_MAX_SIZE = [1, 10];

    private const CLAMP_VECTOR_DIMENSION = [16, 4096];

    private const CLAMP_TEXTUAL_WEIGHT = [0.0, 1.0];

    private const CLAMP_PHONETIC_WEIGHT = [0.0, 1.0];

    private const CLAMP_LETTER_BONUS = [0.0, 0.5];

    private const CLAMP_BIGRAM_BONUS = [0.0, 0.5];

    private const CLAMP_MIN_WORD_LENGTH = [1, 10];

    private const CLAMP_MAX_WORDS = [1, 500];

    private const CLAMP_MAX_PAIRS = [10, 100000];

    private const CLAMP_TIMEOUT_SECONDS = [0.01, 10.0];

    private const CLAMP_METAPHONE_BONUS_THRESHOLD = [1, 10];

    private const CLAMP_METAPHONE_BONUS_VALUE = [0.0, 1.0];

    private const CLAMP_LEXICAL_BONUS_THRESHOLD = [1, 10];

    private const CLAMP_LEXICAL_BONUS_MEDIUM = [0.0, 1.0];

    private const CLAMP_LEXICAL_BONUS_HIGH = [0.0, 1.0];

    private const CLAMP_MAX_LEVENSHTEIN_BONUS = [0.0, 1.0];

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Clamps a value between minimum and maximum bounds.
     *
     * @param  int|float  $value  The value to clamp
     * @param  int|float  $min  The minimum allowed value
     * @param  int|float  $max  The maximum allowed value
     * @return int|float The clamped value
     */
    private function clamp(int|float $value, int|float $min, int|float $max): int|float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Merges configuration array with default values.
     *
     * If a key exists in the config, it is preserved.
     * If a key doesn't exist, the default value is used.
     *
     * @param  array  $configValue  The configuration array
     * @param  array  $defaultValue  The default values
     * @return array Merged array
     */
    private function mergeWithDefault(array $configValue, array $defaultValue): array
    {
        foreach ($defaultValue as $key => $value) {
            if (! array_key_exists($key, $configValue)) {
                $configValue[$key] = $value;
            }
        }

        return $configValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getGramMinSize(): int
    {
        $value = $this->config->get('completion.similarity.gram_min_size', self::DEFAULT_GRAM_MIN_SIZE);
        [$min, $max] = self::CLAMP_GRAM_MIN_SIZE;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getGramMaxSize(): int
    {
        $value = $this->config->get('completion.similarity.gram_max_size', self::DEFAULT_GRAM_MAX_SIZE);
        [$min, $max] = self::CLAMP_GRAM_MAX_SIZE;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getVectorDimension(): int
    {
        $value = $this->config->get('completion.similarity.vector_dimension', self::DEFAULT_VECTOR_DIMENSION);
        [$min, $max] = self::CLAMP_VECTOR_DIMENSION;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getTextualWeight(): float
    {
        $value = $this->config->get('completion.similarity.textual_weight', self::DEFAULT_TEXTUAL_WEIGHT);
        [$min, $max] = self::CLAMP_TEXTUAL_WEIGHT;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getPhoneticWeight(): float
    {
        $value = $this->config->get('completion.similarity.phonetic_weight', self::DEFAULT_PHONETIC_WEIGHT);
        [$min, $max] = self::CLAMP_PHONETIC_WEIGHT;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getLetterBonus(): float
    {
        $value = $this->config->get('completion.similarity.letter_bonus', self::DEFAULT_LETTER_BONUS);
        [$min, $max] = self::CLAMP_LETTER_BONUS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigramBonus(): float
    {
        $value = $this->config->get('completion.similarity.bigram_bonus', self::DEFAULT_BIGRAM_BONUS);
        [$min, $max] = self::CLAMP_BIGRAM_BONUS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMinWordLength(): int
    {
        $value = $this->config->get('completion.similarity.min_word_length', self::DEFAULT_MIN_WORD_LENGTH);
        [$min, $max] = self::CLAMP_MIN_WORD_LENGTH;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxWords(): int
    {
        $value = $this->config->get('completion.similarity.max_words', self::DEFAULT_MAX_WORDS);
        [$min, $max] = self::CLAMP_MAX_WORDS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxPairs(): int
    {
        $value = $this->config->get('completion.similarity.max_pairs', self::DEFAULT_MAX_PAIRS);
        [$min, $max] = self::CLAMP_MAX_PAIRS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeoutSeconds(): float
    {
        $value = $this->config->get('completion.similarity.timeout_seconds', self::DEFAULT_TIMEOUT_SECONDS);
        [$min, $max] = self::CLAMP_TIMEOUT_SECONDS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetaphoneBonusThreshold(): int
    {
        $value = $this->config->get('completion.similarity.levenshtein.metaphone_threshold', self::DEFAULT_METAPHONE_BONUS_THRESHOLD);
        [$min, $max] = self::CLAMP_METAPHONE_BONUS_THRESHOLD;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetaphoneBonusValue(): float
    {
        $value = $this->config->get('completion.similarity.levenshtein.metaphone_bonus', self::DEFAULT_METAPHONE_BONUS_VALUE);
        [$min, $max] = self::CLAMP_METAPHONE_BONUS_VALUE;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getLexicalBonusThreshold(): int
    {
        $value = $this->config->get('completion.similarity.levenshtein.lexical_threshold', self::DEFAULT_LEXICAL_BONUS_THRESHOLD);
        [$min, $max] = self::CLAMP_LEXICAL_BONUS_THRESHOLD;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getLexicalBonusMedium(): float
    {
        $value = $this->config->get('completion.similarity.levenshtein.lexical_bonus_medium', self::DEFAULT_LEXICAL_BONUS_MEDIUM);
        [$min, $max] = self::CLAMP_LEXICAL_BONUS_MEDIUM;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getLexicalBonusHigh(): float
    {
        $value = $this->config->get('completion.similarity.levenshtein.lexical_bonus_high', self::DEFAULT_LEXICAL_BONUS_HIGH);
        [$min, $max] = self::CLAMP_LEXICAL_BONUS_HIGH;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxLevenshteinBonus(): float
    {
        $value = $this->config->get('completion.similarity.levenshtein.max_bonus', self::DEFAULT_MAX_LEVENSHTEIN_BONUS);
        [$min, $max] = self::CLAMP_MAX_LEVENSHTEIN_BONUS;

        return $this->clamp($value, $min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function getLetterWeight(string $letter): float
    {
        $configValue = $this->config->get('completion.similarity.letter_weights', []);
        $weights = $this->mergeWithDefault($configValue, self::DEFAULT_LETTER_WEIGHTS);

        return $weights[$letter] ?? 0.5;
    }

    /**
     * {@inheritDoc}
     */
    public function getGramWeight(int $length): float
    {
        $weights = $this->config->get('completion.similarity.gram_weights', []);

        if (isset($weights[$length])) {
            return (float) $weights[$length];
        }

        if (isset($weights['default'])) {
            return (float) $weights['default'];
        }

        return self::DEFAULT_GRAM_WEIGHTS['default'];
    }
}
