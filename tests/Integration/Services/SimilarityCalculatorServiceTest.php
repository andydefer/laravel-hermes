<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Services;

use AndyDefer\LaravelHermes\Contracts\Services\SimilarityCalculatorInterface;
use AndyDefer\LaravelHermes\Services\SimilarityCalculatorService;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;

/**
 * Integration tests for SimilarityCalculatorService.
 *
 * Tests the full similarity calculation pipeline with real dependencies:
 * - Text normalization
 * - N-gram generation
 * - Vector generation
 * - Similarity calculation
 */
final class SimilarityCalculatorServiceTest extends IntegrationTestCase
{
    private SimilarityCalculatorInterface $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = $this->app->make(SimilarityCalculatorInterface::class);
    }

    public function test_calculate_similarity_returns_one_for_identical_texts(): void
    {
        $text = 'John Doe';

        $score = $this->calculator->calculateSimilarity($text, $text);

        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_returns_high_score_for_similar_texts(): void
    {
        $text1 = 'Jane Dae';
        $text2 = 'Jan Dae';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.8, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function test_calculate_similarity_handles_case_insensitivity(): void
    {
        $text1 = 'john doe';
        $text2 = 'John Doe';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_accents(): void
    {
        $text1 = 'Café';
        $text2 = 'Cafe';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_phonetic_similarity(): void
    {
        $text1 = 'John Doe';
        $text2 = 'Jon Doe';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_returns_zero_for_empty_texts(): void
    {
        $score = $this->calculator->calculateSimilarity('', '');

        $this->assertSame(0.0, $score);
    }

    public function test_calculate_similarity_returns_zero_when_one_text_is_empty(): void
    {
        $score = $this->calculator->calculateSimilarity('John Doe', '');

        $this->assertSame(0.0, $score);
    }

    public function test_calculate_similarity_handles_multiple_words(): void
    {
        $text1 = 'The quick brown fox jumps over the lazy dog';
        $text2 = 'The quick brown fox jumps over the lazy dog';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_partial_match(): void
    {
        $text1 = 'The quick brown fox';
        $text2 = 'The quick brown fox jumps over the lazy dog';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.5, $score);
        $this->assertLessThan(1.0, $score);
    }

    public function test_calculate_similarity_handles_shuffled_words(): void
    {
        $text1 = 'brown fox quick the';
        $text2 = 'The quick brown fox';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.7, $score);
    }

    public function test_calculate_similarity_handles_short_words(): void
    {
        $text1 = 'a b c';
        $text2 = 'a b c';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_calculate_similarity_handles_special_characters(): void
    {
        $text1 = 'Hello, World!';
        $text2 = 'Hello World';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_numbers(): void
    {
        $text1 = 'Version 2.0.1';
        $text2 = 'Version 2.0.2';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_is_commutative(): void
    {
        $text1 = 'John Doe';
        $text2 = 'Jon Doe';

        $score1 = $this->calculator->calculateSimilarity($text1, $text2);
        $score2 = $this->calculator->calculateSimilarity($text2, $text1);

        $this->assertSame($score1, $score2);
    }

    public function test_calculate_similarity_handles_very_long_texts(): void
    {
        $text1 = str_repeat('Lorem ipsum dolor sit amet ', 100);
        $text2 = str_repeat('Lorem ipsum dolor sit amet ', 100);

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_single_characters(): void
    {
        $text1 = 'a';
        $text2 = 'b';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertLessThan(0.5, $score);
    }

    public function test_calculate_similarity_handles_typos(): void
    {
        $text1 = 'Laravel Framework';
        $text2 = 'Laravle Framewrok';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_handles_different_languages(): void
    {
        $text1 = 'Bonjour le monde';
        $text2 = 'Bonjour le monde';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_french_accents(): void
    {
        $text1 = 'Éléphant';
        $text2 = 'Elephant';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_punctuation(): void
    {
        $text1 = 'Hello, how are you?';
        $text2 = 'Hello how are you';

        $score = $this->calculator->calculateSimilarity($text1, $text2);

        $this->assertGreaterThan(0.9, $score);
    }
}
