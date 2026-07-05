<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Services;

use AndyDefer\DomainStructures\Collections\Utility\FloatTypedCollection;
use AndyDefer\LaravelHermes\Contracts\Configs\SimilarityConfigInterface;
use AndyDefer\LaravelHermes\Contracts\Services\SimilarityCalculatorInterface;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\Services\WordVectorGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Enums\NormalizationMode;

/**
 * Service for calculating similarity between two text strings.
 *
 * Combines lexical n-gram similarity and phonetic (metaphone) similarity
 * with configurable weights. Applies bonuses for common letters and bigrams,
 * Levenshtein distance bonus, and corrects the final score based on text length differences.
 *
 * @example
 * $service = new SimilarityCalculatorService(...);
 * $score = $service->calculateSimilarity('John Doe', 'Jon Doe');
 * // Returns ~0.85
 */
final class SimilarityCalculatorService implements SimilarityCalculatorInterface
{
    /** Maximum number of words before sampling is triggered. */
    private const DEFAULT_MAX_WORDS = 50;

    /** Maximum number of word pairs before sampling is triggered. */
    private const DEFAULT_MAX_PAIRS = 2500;

    /** Default timeout in seconds for similarity matrix calculation. */
    private const DEFAULT_TIMEOUT_SECONDS = 0.5;

    /** Percentage threshold for coverage penalty (70%). */
    private const COVERAGE_PENALTY_THRESHOLD = 0.7;

    /** Penalty factor applied when coverage is below threshold. */
    private const COVERAGE_PENALTY_FACTOR = 0.3;

    /** Percentage of words taken from the beginning during sampling (50%). */
    private const SAMPLE_BEGINNING_RATIO = 0.5;

    /** Percentage of remaining words taken from the middle during sampling (50%). */
    private const SAMPLE_MIDDLE_RATIO = 0.5;

    /** Starting position for middle sampling (30% into the list). */
    private const SAMPLE_MIDDLE_OFFSET = 0.3;

    /** Lexical distance threshold for high bonus (distance < 2). */
    private const LEXICAL_BONUS_HIGH_THRESHOLD = 2;

    /** Minimum word length when merging short words. */
    private const MIN_MERGE_WORD_LENGTH = 2;

    /**
     * @var array<string, FloatTypedCollection>
     */
    private array $vectorCache = [];

    public function __construct(
        private readonly TextNormalizerInterface $normalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly WordVectorGeneratorInterface $vectorGenerator,
        private readonly SimilarityConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function calculateSimilarity(string $text1, string $text2): float
    {
        $normalized1 = $this->normalizer->normalize($text1);
        $normalized2 = $this->normalizer->normalize($text2);

        $normalized1 = $this->normalizeNumbers($normalized1);
        $normalized2 = $this->normalizeNumbers($normalized2);

        $maxWords = $this->config->getMaxWords() ?? self::DEFAULT_MAX_WORDS;
        $words1 = $this->extractAndMergeWords($normalized1, $maxWords);
        $words2 = $this->extractAndMergeWords($normalized2, $maxWords);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $totalPairs = count($words1) * count($words2);
        $maxPairs = $this->config->getMaxPairs() ?? self::DEFAULT_MAX_PAIRS;

        if ($totalPairs > $maxPairs) {
            $words1 = $this->sampleWords($words1, $maxWords);
            $words2 = $this->sampleWords($words2, $maxWords);
        }

        $timeout = $this->config->getTimeoutSeconds() ?? self::DEFAULT_TIMEOUT_SECONDS;
        $similarityMatrix = $this->buildSimilarityMatrixWithTimeout($words1, $words2, $timeout);

        $bestMatches = $this->selectBestOneToOneMatches($similarityMatrix, count($words1), count($words2));

        if (empty($bestMatches)) {
            return 0.0;
        }

        $baseScore = array_sum($bestMatches) / count($bestMatches);

        return $this->applyLengthCorrection($baseScore, $normalized1, $normalized2);
    }

    /**
     * Builds a similarity matrix with timeout protection.
     *
     * Each cell contains the similarity between a word from text1 and a word from text2.
     * The matrix is populated row by row, with a timeout to prevent excessive computation.
     *
     * @param  array<string>  $words1  Words from the first text
     * @param  array<string>  $words2  Words from the second text
     * @param  float  $timeout  Maximum execution time in seconds
     * @return array<array<float>> Matrix of similarity scores
     */
    private function buildSimilarityMatrixWithTimeout(array $words1, array $words2, float $timeout): array
    {
        $matrix = [];
        $startTime = microtime(true);
        $rowCount = count($words1);
        $colCount = count($words2);

        for ($row = 0; $row < $rowCount; $row++) {
            $matrix[$row] = [];

            for ($col = 0; $col < $colCount; $col++) {
                $matrix[$row][$col] = $this->calculateWordSimilarity($words1[$row], $words2[$col]);

                if (microtime(true) - $startTime > $timeout) {
                    $this->fillRemainingCells($matrix, $row, $col, $rowCount, $colCount);

                    return $matrix;
                }
            }
        }

        return $matrix;
    }

    /**
     * Fills remaining cells of the matrix with zero values after timeout.
     *
     * @param  array<array<float>>  $matrix  The matrix being built
     * @param  int  $currentRow  The current row index
     * @param  int  $currentCol  The current column index
     * @param  int  $rowCount  Total number of rows
     * @param  int  $colCount  Total number of columns
     */
    private function fillRemainingCells(array &$matrix, int $currentRow, int $currentCol, int $rowCount, int $colCount): void
    {
        for ($row = $currentRow; $row < $rowCount; $row++) {
            for ($col = ($row === $currentRow ? $currentCol + 1 : 0); $col < $colCount; $col++) {
                if (! isset($matrix[$row][$col])) {
                    $matrix[$row][$col] = 0.0;
                }
            }
        }
    }

    /**
     * Samples words from a list to reduce matrix size.
     *
     * Takes a balanced sample from the beginning, middle, and end of the list.
     *
     * @param  array<string>  $words  Original word list
     * @param  int  $maxWords  Maximum number of words to keep
     * @return array<string> Sampled word list
     */
    private function sampleWords(array $words, int $maxWords): array
    {
        $wordCount = count($words);

        if ($wordCount <= $maxWords) {
            return $words;
        }

        $sampled = [];

        $takeFirst = (int) ($maxWords * self::SAMPLE_BEGINNING_RATIO);
        $sampled = array_merge($sampled, array_slice($words, 0, $takeFirst));

        $remaining = $maxWords - count($sampled);

        if ($remaining > 0) {
            $takeMiddle = (int) ($remaining * self::SAMPLE_MIDDLE_RATIO);
            $middleStart = (int) ($wordCount * self::SAMPLE_MIDDLE_OFFSET);
            $sampled = array_merge($sampled, array_slice($words, $middleStart, $takeMiddle));

            $takeEnd = $maxWords - count($sampled);
            $sampled = array_merge($sampled, array_slice($words, -$takeEnd));
        }

        return $sampled;
    }

    /**
     * Selects the best one-to-one matches from the similarity matrix.
     *
     * Uses a greedy algorithm to find the highest scoring pairs
     * without reusing rows or columns.
     *
     * @param  array<array<float>>  $matrix  Similarity matrix
     * @param  int  $rowCount  Number of rows (words from first text)
     * @param  int  $colCount  Number of columns (words from second text)
     * @return array<float> List of best match scores
     */
    private function selectBestOneToOneMatches(array $matrix, int $rowCount, int $colCount): array
    {
        $matchCount = min($rowCount, $colCount);
        $bestMatches = [];

        if ($matchCount === 1) {
            return [$this->findHighestScore($matrix, $rowCount, $colCount)];
        }

        $usedRows = [];
        $usedCols = [];

        for ($matchIndex = 0; $matchIndex < $matchCount; $matchIndex++) {
            $bestScore = -1.0;
            $bestRow = -1;
            $bestCol = -1;

            for ($row = 0; $row < $rowCount; $row++) {
                if (in_array($row, $usedRows, true)) {
                    continue;
                }

                for ($col = 0; $col < $colCount; $col++) {
                    if (in_array($col, $usedCols, true)) {
                        continue;
                    }

                    if ($matrix[$row][$col] > $bestScore) {
                        $bestScore = $matrix[$row][$col];
                        $bestRow = $row;
                        $bestCol = $col;
                    }
                }
            }

            if ($bestRow === -1 || $bestCol === -1) {
                break;
            }

            $bestMatches[] = $bestScore;
            $usedRows[] = $bestRow;
            $usedCols[] = $bestCol;
        }

        return $bestMatches;
    }

    /**
     * Finds the highest score in a matrix.
     *
     * @param  array<array<float>>  $matrix  Similarity matrix
     * @param  int  $rowCount  Number of rows
     * @param  int  $colCount  Number of columns
     * @return float Highest score found
     */
    private function findHighestScore(array $matrix, int $rowCount, int $colCount): float
    {
        $maxScore = 0.0;

        for ($row = 0; $row < $rowCount; $row++) {
            for ($col = 0; $col < $colCount; $col++) {
                if ($matrix[$row][$col] > $maxScore) {
                    $maxScore = $matrix[$row][$col];
                }
            }
        }

        return $maxScore;
    }

    /**
     * Applies a length correction factor to the similarity score.
     *
     * Penalizes texts where:
     * 1. One text is significantly shorter than the other (coverage penalty)
     * 2. The shorter text doesn't cover a proportional amount of unique letters
     *
     * @param  float  $score  Base similarity score
     * @param  string  $text1  First normalized text
     * @param  string  $text2  Second normalized text
     * @return float Corrected similarity score
     */
    private function applyLengthCorrection(float $score, string $text1, string $text2): float
    {
        $letters1 = array_unique(mb_str_split($this->normalizer->normalize($text1)));
        $letters2 = array_unique(mb_str_split($this->normalizer->normalize($text2)));

        $uniqueCount1 = count($letters1);
        $uniqueCount2 = count($letters2);

        if ($uniqueCount1 === 0 || $uniqueCount2 === 0) {
            return $score;
        }

        $longest = max($uniqueCount1, $uniqueCount2);
        $shortest = min($uniqueCount1, $uniqueCount2);

        $coverageRatio = $shortest / $longest;

        if ($coverageRatio < self::COVERAGE_PENALTY_THRESHOLD) {
            $coveragePenalty = 1 - (self::COVERAGE_PENALTY_FACTOR * (1 - $coverageRatio));
            $score *= $coveragePenalty;
        }

        $shortToLongPercentage = ($shortest / $longest) * 100;
        $commonLetters = array_intersect($letters1, $letters2);
        $commonCount = count($commonLetters);

        $coverageRatio2 = $commonCount / $longest;
        $expectedCoverage = $shortToLongPercentage / 100;

        if ($coverageRatio2 >= $expectedCoverage) {
            return max(0.0, min(1.0, $score));
        }

        $penalty = ($shortToLongPercentage / 100) / $longest * ($longest / $shortest);
        $correctedScore = $score * (1 - $penalty);

        return max(0.0, min(1.0, $correctedScore));
    }

    /**
     * Extracts words from text and merges short words with neighbors.
     *
     * Words shorter than the configured minimum length are merged
     * with the following word to form a single token.
     *
     * @param  string  $text  Normalized text
     * @param  int  $maxWords  Maximum number of words to keep
     * @return array<string> List of processed words
     */
    private function extractAndMergeWords(string $text, int $maxWords = 50): array
    {
        $words = preg_split('/[\s,;:!?.]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values($words);

        if (empty($words)) {
            return [];
        }

        $minLength = max(self::MIN_MERGE_WORD_LENGTH, $this->config->getMinWordLength());

        if ($this->allWordsAreLongEnough($words, $minLength)) {
            return count($words) > $maxWords ? array_slice($words, 0, $maxWords) : $words;
        }

        $merged = $this->mergeShortWords($words, $minLength);

        return count($merged) > $maxWords ? array_slice($merged, 0, $maxWords) : $merged;
    }

    /**
     * Normalizes numbers in text.
     *
     * "2.0.1" → "2 0 1"
     * "2.0.2" → "2 0 2"
     * "v2.0.1" → "v 2 0 1"
     *
     * @param  string  $text  Text to normalize
     * @return string Normalized text
     */
    private function normalizeNumbers(string $text): string
    {
        $text = preg_replace('/(\d+)\.(\d+)\.(\d+)/', '$1 $2 $3', $text);

        return preg_replace('/([a-zA-Z])(\d+)\.(\d+)\.(\d+)/', '$1 $2 $3 $4', $text);
    }

    /**
     * Checks if all words meet the minimum length requirement.
     *
     * @param  array<string>  $words  List of words
     * @param  int  $minLength  Minimum length required
     * @return bool True if all words are long enough
     */
    private function allWordsAreLongEnough(array $words, int $minLength): bool
    {
        foreach ($words as $word) {
            if (strlen($word) < $minLength) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merges short words with their following word.
     *
     * @param  array<string>  $words  Original word list
     * @param  int  $minLength  Minimum length required
     * @return array<string> Processed word list
     */
    private function mergeShortWords(array $words, int $minLength): array
    {
        $merged = [];
        $index = 0;
        $buffer = '';

        while ($index < count($words)) {
            $currentWord = $words[$index];

            if (strlen($currentWord) >= $minLength) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }

                $merged[] = $currentWord;
                $index++;

                continue;
            }

            if ($buffer === '') {
                $buffer = $currentWord;
            } else {
                $buffer .= $currentWord;
            }

            $index++;

            if ($index < count($words)) {
                $buffer .= $words[$index];
                $index++;
            }
        }

        if ($buffer !== '') {
            $merged[] = $buffer;
        }

        $merged = array_filter($merged, function ($word) use ($minLength) {
            return strlen($word) >= $minLength;
        });

        return array_values($merged);
    }

    /**
     * Calculates similarity between two individual words.
     *
     * Combines lexical n-gram similarity and phonetic similarity with configurable weights,
     * plus bonuses for common letters, bigrams, and Levenshtein proximity.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return float Similarity score between 0.0 and 1.0
     */
    private function calculateWordSimilarity(string $word1, string $word2): float
    {
        if ($word1 === $word2) {
            return 1.0;
        }

        if ($word1 === '' || $word2 === '') {
            return 0.0;
        }

        $dimension = $this->config->getVectorDimension();

        $lexicalVector1 = $this->getOrGenerateLexicalVector($word1, $dimension);
        $lexicalVector2 = $this->getOrGenerateLexicalVector($word2, $dimension);

        $phoneticVector1 = $this->getOrGeneratePhoneticVector($word1, $dimension);
        $phoneticVector2 = $this->getOrGeneratePhoneticVector($word2, $dimension);

        $lexicalSimilarity = $this->vectorGenerator->cosineSimilarity($lexicalVector1, $lexicalVector2);
        $phoneticSimilarity = $this->vectorGenerator->cosineSimilarity($phoneticVector1, $phoneticVector2);

        $bonus = $this->calculateBonus($word1, $word2);
        $levenshteinBonus = $this->calculateLevenshteinBonus($word1, $word2);

        $textualWeight = $this->config->getTextualWeight();
        $phoneticWeight = $this->config->getPhoneticWeight();

        $baseSimilarity = ($lexicalSimilarity * $textualWeight) + ($phoneticSimilarity * $phoneticWeight);

        return min(1.0, $baseSimilarity + $bonus + $levenshteinBonus);
    }

    /**
     * Calculates Levenshtein bonus based on lexical and metaphone distances.
     *
     * Rules configured via SimilarityConfigInterface:
     * - Metaphone distance < threshold → metaphone_bonus
     * - Lexical distance < 2 → lexical_bonus_high
     * - Lexical distance < threshold → lexical_bonus_medium
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return float Levenshtein bonus
     */
    private function calculateLevenshteinBonus(string $word1, string $word2): float
    {
        $normalized1 = $this->normalizer->normalize($word1);
        $normalized2 = $this->normalizer->normalize($word2);

        $levenshteinLexical = levenshtein($normalized1, $normalized2);

        $metaphone1 = metaphone($normalized1);
        $metaphone2 = metaphone($normalized2);
        $levenshteinMetaphone = levenshtein($metaphone1, $metaphone2);

        $bonus = 0.0;

        if ($levenshteinMetaphone < $this->config->getMetaphoneBonusThreshold()) {
            $bonus += $this->config->getMetaphoneBonusValue();
        }

        if ($levenshteinLexical < self::LEXICAL_BONUS_HIGH_THRESHOLD) {
            $bonus += $this->config->getLexicalBonusHigh();
        } elseif ($levenshteinLexical < $this->config->getLexicalBonusThreshold()) {
            $bonus += $this->config->getLexicalBonusMedium();
        }

        return min($this->config->getMaxLevenshteinBonus(), $bonus);
    }

    /**
     * Gets a cached lexical vector or generates it.
     *
     * @param  string  $word  The word to process
     * @param  int  $dimension  Vector dimension
     * @return FloatTypedCollection Normalized vector
     */
    private function getOrGenerateLexicalVector(string $word, int $dimension): FloatTypedCollection
    {
        $cacheKey = 'lexical_'.$word.'_'.$dimension;

        if (isset($this->vectorCache[$cacheKey])) {
            return $this->vectorCache[$cacheKey];
        }

        $vector = $this->generateLexicalVector($word, $dimension);
        $this->vectorCache[$cacheKey] = $vector;

        return $vector;
    }

    /**
     * Gets a cached phonetic vector or generates it.
     *
     * @param  string  $word  The word to process
     * @param  int  $dimension  Vector dimension
     * @return FloatTypedCollection Normalized vector
     */
    private function getOrGeneratePhoneticVector(string $word, int $dimension): FloatTypedCollection
    {
        $cacheKey = 'phonetic_'.$word.'_'.$dimension;

        if (isset($this->vectorCache[$cacheKey])) {
            return $this->vectorCache[$cacheKey];
        }

        $vector = $this->generatePhoneticVector($word, $dimension);
        $this->vectorCache[$cacheKey] = $vector;

        return $vector;
    }

    /**
     * Calculates bonus points for common letters and bigrams.
     *
     * Bonus amounts are configured via SimilarityConfigInterface.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return float Bonus value to add to similarity score
     */
    private function calculateBonus(string $word1, string $word2): float
    {
        $normalized1 = $this->normalizer->normalize($word1);
        $normalized2 = $this->normalizer->normalize($word2);

        $commonLettersCount = $this->countCommonLetters($normalized1, $normalized2);
        $commonBigramsCount = $this->countCommonBigrams($normalized1, $normalized2);

        $averageInverseWeight = $this->calculateAverageInverseWeight($normalized1, $normalized2);

        $letterBonus = $commonLettersCount * $this->config->getLetterBonus() * $averageInverseWeight;
        $bigramBonus = $commonBigramsCount * $this->config->getBigramBonus() * $averageInverseWeight;

        return $letterBonus + $bigramBonus;
    }

    /**
     * Counts common letters between two words.
     *
     * @param  string  $word1  First normalized word
     * @param  string  $word2  Second normalized word
     * @return int Number of common unique letters
     */
    private function countCommonLetters(string $word1, string $word2): int
    {
        $letters1 = array_unique(mb_str_split($word1));
        $letters2 = array_unique(mb_str_split($word2));

        return count(array_intersect($letters1, $letters2));
    }

    /**
     * Counts common bigrams between two words.
     *
     * @param  string  $word1  First normalized word
     * @param  string  $word2  Second normalized word
     * @return int Number of common bigrams
     */
    private function countCommonBigrams(string $word1, string $word2): int
    {
        $bigrams1 = $this->extractBigrams($word1);
        $bigrams2 = $this->extractBigrams($word2);

        return count(array_intersect($bigrams1, $bigrams2));
    }

    /**
     * Calculates the average inverse letter weight for two words.
     *
     * @param  string  $word1  First normalized word
     * @param  string  $word2  Second normalized word
     * @return float Average inverse weight
     */
    private function calculateAverageInverseWeight(string $word1, string $word2): float
    {
        $inverseWeight1 = $this->calculateInverseLetterWeight($word1);
        $inverseWeight2 = $this->calculateInverseLetterWeight($word2);

        return ($inverseWeight1 + $inverseWeight2) / 2;
    }

    /**
     * Extracts all bigrams (2-character sequences) from a word.
     *
     * @param  string  $word  The word to process
     * @return array<string> List of bigrams
     */
    private function extractBigrams(string $word): array
    {
        $length = strlen($word);

        if ($length < 2) {
            return [];
        }

        $bigrams = [];

        for ($position = 0; $position < $length - 1; $position++) {
            $bigrams[] = substr($word, $position, 2);
        }

        return $bigrams;
    }

    /**
     * Generates a weighted lexical vector for a word.
     *
     * Uses n-grams weighted by gram size and inverse letter frequency.
     *
     * @param  string  $word  The word to process
     * @param  int  $dimension  Vector dimension
     * @return FloatTypedCollection Normalized vector
     */
    private function generateLexicalVector(string $word, int $dimension): FloatTypedCollection
    {
        $normalizedWord = $this->normalizer->normalize($word);

        $ngrams = $this->ngramGenerator->generate(
            $normalizedWord,
            $this->config->getGramMinSize(),
            $this->config->getGramMaxSize(),
            NormalizationMode::WITH_NORMALIZATION
        )->toArray();

        $tokens = array_unique(array_merge([$normalizedWord], $ngrams));

        $vector = array_fill(0, $dimension, 0.0);

        foreach ($tokens as $token) {
            $weight = $this->calculateTokenWeight($token);
            $hashIndex = abs(crc32($token)) % $dimension;
            $vector[$hashIndex] += $weight;
        }

        $collection = FloatTypedCollection::from($vector);

        return $this->vectorGenerator->normalizeVector($collection);
    }

    /**
     * Generates a weighted phonetic vector for a word.
     *
     * Uses metaphone encoding followed by n-gram generation.
     *
     * @param  string  $word  The word to process
     * @param  int  $dimension  Vector dimension
     * @return FloatTypedCollection Normalized vector
     */
    private function generatePhoneticVector(string $word, int $dimension): FloatTypedCollection
    {
        $normalizedWord = $this->normalizer->normalize($word);
        $metaphone = metaphone($normalizedWord);

        if ($metaphone === '') {
            return FloatTypedCollection::from(array_fill(0, $dimension, 0.0));
        }

        $ngrams = $this->ngramGenerator->generate(
            $metaphone,
            $this->config->getGramMinSize(),
            $this->config->getGramMaxSize(),
            NormalizationMode::WITH_NORMALIZATION
        )->toArray();

        $tokens = array_unique(array_merge([$metaphone], $ngrams));

        $vector = array_fill(0, $dimension, 0.0);

        foreach ($tokens as $token) {
            $weight = $this->calculateTokenWeight($token);
            $hashIndex = abs(crc32($token)) % $dimension;
            $vector[$hashIndex] += $weight;
        }

        $collection = FloatTypedCollection::from($vector);

        return $this->vectorGenerator->normalizeVector($collection);
    }

    /**
     * Calculates the weight for a token based on gram size and inverse letter weight.
     *
     * @param  string  $token  The token to weight
     * @return float Calculated weight
     */
    private function calculateTokenWeight(string $token): float
    {
        $gramWeight = $this->config->getGramWeight(strlen($token));
        $letterWeight = $this->calculateInverseLetterWeight($token);

        return $gramWeight * $letterWeight;
    }

    /**
     * Calculates the inverse letter weight for a token.
     *
     * More frequent letters have lower influence.
     * Formula: 1 / (letterWeight + 1)
     *
     * @param  string  $token  The token to process
     * @return float Average inverse letter weight
     */
    private function calculateInverseLetterWeight(string $token): float
    {
        $letters = mb_str_split($token);

        if (empty($letters)) {
            return 0.5;
        }

        $totalInverseWeight = 0.0;

        foreach ($letters as $letter) {
            $weight = $this->config->getLetterWeight($letter);
            $totalInverseWeight += 1 / ($weight + 1);
        }

        return $totalInverseWeight / count($letters);
    }
}
