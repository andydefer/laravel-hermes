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
    private bool $debug = false;

    /** @var array<string, FloatTypedCollection> */
    private array $vectorCache = [];

    public function __construct(
        private readonly TextNormalizerInterface $normalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly WordVectorGeneratorInterface $vectorGenerator,
        private readonly SimilarityConfigInterface $config,
    ) {}

    /**
     * Enables or disables debug output.
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Calculates the similarity between two text strings.
     *
     * The algorithm:
     * 1. Normalizes both texts (lowercase, remove accents)
     * 2. Normalizes numbers (2.0.1 → 2 0 1)
     * 3. Extracts words and merges short ones with neighbors
     * 4. Builds a similarity matrix between all word pairs
     * 5. Selects the best one-to-one matches
     * 6. Averages the match scores
     * 7. Applies a length correction factor
     *
     * @param  string  $text1  First text to compare
     * @param  string  $text2  Second text to compare
     * @return float Similarity score between 0.0 and 1.0
     */
    public function calculateSimilarity(string $text1, string $text2): float
    {
        $totalStart = microtime(true);

        // ---- Étape 1: Normalisation ----
        $start = microtime(true);
        $normalized1 = $this->normalizer->normalize($text1);
        $normalized2 = $this->normalizer->normalize($text2);
        $this->logTime('Normalisation', $start);

        // ---- Étape 2: Normalisation des nombres ----
        $normalized1 = $this->normalizeNumbers($normalized1);
        $normalized2 = $this->normalizeNumbers($normalized2);

        // ---- Étape 3: Extraction et fusion des mots ----
        $start = microtime(true);
        $maxWords = $this->config->getMaxWords() ?? 50;
        $words1 = $this->extractAndMergeWords($normalized1, $maxWords);
        $words2 = $this->extractAndMergeWords($normalized2, $maxWords);
        $this->logTime('Extraction des mots', $start);
        $this->logCount('Mots texte 1', count($words1));
        $this->logCount('Mots texte 2', count($words2));

        if (empty($words1) || empty($words2)) {
            $this->logTime('Total', $totalStart);

            return 0.0;
        }

        // ---- Étape 4: Sampling si trop de mots ----
        $totalPairs = count($words1) * count($words2);
        $maxPairs = $this->config->getMaxPairs() ?? 2500;

        if ($totalPairs > $maxPairs) {
            $this->logCount('⚠️  Sampling déclenché (paires: '.$totalPairs.' > '.$maxPairs.')', 1);
            $words1 = $this->sampleWords($words1, $maxWords);
            $words2 = $this->sampleWords($words2, $maxWords);
            $this->logCount('Mots après sampling 1', count($words1));
            $this->logCount('Mots après sampling 2', count($words2));
        }

        // ---- Étape 5: Matrice de similarité avec timeout ----
        $start = microtime(true);
        $timeout = $this->config->getTimeoutSeconds() ?? 0.5;
        $similarityMatrix = $this->buildSimilarityMatrixWithTimeout($words1, $words2, $timeout);
        $this->logTime('Matrice de similarité', $start);
        $this->logCount('Taille matrice', count($similarityMatrix) * count($similarityMatrix[0] ?? []));

        // ---- Étape 6: Sélection des meilleurs matchs ----
        $start = microtime(true);
        $bestMatches = $this->selectBestOneToOneMatches($similarityMatrix, count($words1), count($words2));
        $this->logTime('Sélection des meilleurs matchs', $start);
        $this->logCount('Meilleurs matchs', count($bestMatches));

        if (empty($bestMatches)) {
            $this->logTime('Total', $totalStart);

            return 0.0;
        }

        // ---- Étape 7: Score moyen ----
        $start = microtime(true);
        $baseScore = array_sum($bestMatches) / count($bestMatches);
        $this->logTime('Score moyen', $start);
        $this->logValue('Score moyen', $baseScore);

        // ---- Étape 8: Correction de longueur ----
        $start = microtime(true);
        $finalScore = $this->applyLengthCorrection($baseScore, $normalized1, $normalized2);
        $this->logTime('Correction de longueur', $start);
        $this->logValue('Score final', $finalScore);

        $this->logTime('Total', $totalStart);
        $this->logSeparator();

        return $finalScore;
    }

    /**
     * Builds a similarity matrix with timeout protection.
     *
     * @param  array<string>  $words1  First list of words
     * @param  array<string>  $words2  Second list of words
     * @param  float  $timeout  Maximum time in seconds
     * @return array<array<float>> Matrix of similarity scores
     */
    private function buildSimilarityMatrixWithTimeout(array $words1, array $words2, float $timeout): array
    {
        $matrix = [];
        $startTime = microtime(true);
        $totalPairs = count($words1) * count($words2);
        $processed = 0;

        for ($i = 0; $i < count($words1); $i++) {
            $matrix[$i] = [];

            for ($j = 0; $j < count($words2); $j++) {
                $matrix[$i][$j] = $this->calculateWordSimilarity($words1[$i], $words2[$j]);
                $processed++;

                if (microtime(true) - $startTime > $timeout) {
                    $this->logCount('⏱️  Timeout atteint après '.$processed.'/'.$totalPairs.' paires', 1);

                    // Remplir le reste avec 0
                    for ($remainingI = $i; $remainingI < count($words1); $remainingI++) {
                        for ($remainingJ = ($remainingI === $i ? $j + 1 : 0); $remainingJ < count($words2); $remainingJ++) {
                            if (! isset($matrix[$remainingI][$remainingJ])) {
                                $matrix[$remainingI][$remainingJ] = 0.0;
                            }
                        }
                    }

                    return $matrix;
                }
            }
        }

        return $matrix;
    }

    /**
     * Samples words to reduce matrix size.
     *
     * Takes words from beginning, middle, and end of the list.
     *
     * @param  array<string>  $words  Original word list
     * @param  int  $maxWords  Maximum number of words to keep
     * @return array<string> Sampled word list
     */
    private function sampleWords(array $words, int $maxWords): array
    {
        $count = count($words);

        if ($count <= $maxWords) {
            return $words;
        }

        $sampled = [];

        // 50% du début
        $takeFirst = (int) ($maxWords * 0.5);
        $first = array_slice($words, 0, $takeFirst);
        $sampled = array_merge($sampled, $first);

        $remaining = $maxWords - count($sampled);

        if ($remaining > 0) {
            // 25% du milieu
            $takeMiddle = (int) ($remaining * 0.5);
            $middleStart = (int) ($count * 0.3);
            $middle = array_slice($words, $middleStart, $takeMiddle);
            $sampled = array_merge($sampled, $middle);

            // 25% de la fin
            $takeEnd = $maxWords - count($sampled);
            $end = array_slice($words, -$takeEnd);
            $sampled = array_merge($sampled, $end);
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

        // ---- Pénalité de couverture ----
        $coverageRatio = $shortest / $longest;

        // Si un texte est beaucoup plus court (< 70%), pénaliser
        if ($coverageRatio < 0.7) {
            $coveragePenalty = 1 - (0.3 * (1 - $coverageRatio));
            $score *= $coveragePenalty;
        }

        // ---- Pénalité de lettres communes ----
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

        $minLength = max(2, $this->config->getMinWordLength());

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
        // Séparer les nombres avec points (ex: 2.0.1 → 2 0 1)
        $text = preg_replace('/(\d+)\.(\d+)\.(\d+)/', '$1 $2 $3', $text);

        // Séparer les nombres avec points précédés d'une lettre (ex: v2.0.1 → v 2 0 1)
        $text = preg_replace('/([a-zA-Z])(\d+)\.(\d+)\.(\d+)/', '$1 $2 $3 $4', $text);

        return $text;
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

            // Mot trop court : on le garde dans le buffer
            if ($buffer === '') {
                $buffer = $currentWord;
            } else {
                $buffer .= $currentWord;
            }
            $index++;

            // Si on arrive à la fin ou si le prochain mot existe, on fusionne
            if ($index < count($words)) {
                $nextWord = $words[$index];
                $buffer .= $nextWord;
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
     * Combines lexical n-gram similarity (60% default) and
     * phonetic similarity (40% default) with bonus for
     * common letters and bigrams, and Levenshtein distance bonus.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return float Similarity score between 0.0 and 1.0
     */
    private function calculateWordSimilarity(string $word1, string $word2): float
    {
        // Early return si les mots sont identiques
        if ($word1 === $word2) {
            return 1.0;
        }

        // Early return si un mot est vide
        if ($word1 === '' || $word2 === '') {
            return 0.0;
        }

        $dimension = $this->config->getVectorDimension();

        // ---- Vecteurs lexicaux avec cache ----
        $start = microtime(true);
        $lexicalVector1 = $this->getOrGenerateLexicalVector($word1, $dimension);
        $lexicalVector2 = $this->getOrGenerateLexicalVector($word2, $dimension);
        $this->logTime('  → Vecteurs lexicaux', $start);

        // ---- Vecteurs phonétiques avec cache ----
        $start = microtime(true);
        $phoneticVector1 = $this->getOrGeneratePhoneticVector($word1, $dimension);
        $phoneticVector2 = $this->getOrGeneratePhoneticVector($word2, $dimension);
        $this->logTime('  → Vecteurs phonétiques', $start);

        // ---- Similarités ----
        $start = microtime(true);
        $lexicalSimilarity = $this->vectorGenerator->cosineSimilarity($lexicalVector1, $lexicalVector2);
        $phoneticSimilarity = $this->vectorGenerator->cosineSimilarity($phoneticVector1, $phoneticVector2);
        $this->logTime('  → Cosine similarity', $start);

        // ---- Bonus standard ----
        $start = microtime(true);
        $bonus = $this->calculateBonus($word1, $word2);
        $this->logTime('  → Calcul bonus', $start);

        // ---- Bonus Levenshtein amélioré ----
        $levenshteinBonus = $this->calculateLevenshteinBonus($word1, $word2);
        $this->logValue('  → Bonus Levenshtein', $levenshteinBonus);

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

        // Bonus métaphonique
        if ($levenshteinMetaphone < $this->config->getMetaphoneBonusThreshold()) {
            $bonus += $this->config->getMetaphoneBonusValue();
        }

        // Bonus lexical
        if ($levenshteinLexical < 2) {
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
        $commonLetters = array_intersect($letters1, $letters2);

        return count($commonLetters);
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
        $commonBigrams = array_intersect($bigrams1, $bigrams2);

        return count($commonBigrams);
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
            NormalizationMode::WITHOUT
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
            NormalizationMode::WITHOUT
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

    // ============================================================================
    // Debug helpers
    // ============================================================================

    private function logTime(string $label, float $start): void
    {
        if (! $this->debug) {
            return;
        }

        $duration = (microtime(true) - $start) * 1000;
        echo sprintf("  ⏱️  %s: %.4f ms\n", $label, $duration);
    }

    private function logCount(string $label, int $count): void
    {
        if (! $this->debug) {
            return;
        }

        echo sprintf("  📊 %s: %d\n", $label, $count);
    }

    private function logValue(string $label, float $value): void
    {
        if (! $this->debug) {
            return;
        }

        echo sprintf("  📈 %s: %.6f\n", $label, $value);
    }

    private function logSeparator(): void
    {
        if (! $this->debug) {
            return;
        }

        echo str_repeat('-', 50)."\n";
    }
}
