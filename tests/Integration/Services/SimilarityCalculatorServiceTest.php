<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Services;

use AndyDefer\LaravelHermes\Contracts\Services\SimilarityCalculatorInterface;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;

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
        // Arrange
        $text = 'John Doe';

        // Act
        $score = $this->calculator->calculateSimilarity($text, $text);

        // Assert
        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_returns_high_score_for_similar_texts(): void
    {
        // Arrange
        $text1 = 'Jane Dae';
        $text2 = 'Jan Dae';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.8, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function test_calculate_similarity_handles_case_insensitivity(): void
    {
        // Arrange
        $text1 = 'john doe';
        $text2 = 'John Doe';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_accents(): void
    {
        // Arrange
        $text1 = 'Café';
        $text2 = 'Cafe';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_phonetic_similarity(): void
    {
        // Arrange
        $text1 = 'John Doe';
        $text2 = 'Jon Doe';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_returns_zero_for_empty_texts(): void
    {
        // Arrange
        $text1 = '';
        $text2 = '';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(0.0, $score);
    }

    public function test_calculate_similarity_returns_zero_when_one_text_is_empty(): void
    {
        // Arrange
        $text1 = 'John Doe';
        $text2 = '';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(0.0, $score);
    }

    public function test_calculate_similarity_handles_multiple_words(): void
    {
        // Arrange
        $text1 = 'The quick brown fox jumps over the lazy dog';
        $text2 = 'The quick brown fox jumps over the lazy dog';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_partial_match(): void
    {
        // Arrange
        $text1 = 'The quick brown fox';
        $text2 = 'The quick brown fox jumps over the lazy dog';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.5, $score);
        $this->assertLessThan(1.0, $score);
    }

    public function test_calculate_similarity_handles_shuffled_words(): void
    {
        // Arrange
        $text1 = 'brown fox quick the';
        $text2 = 'The quick brown fox';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.7, $score);
    }

    public function test_calculate_similarity_handles_short_words(): void
    {
        // Arrange
        $text1 = 'a b c';
        $text2 = 'a b c';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.5, $score);
    }

    public function test_calculate_similarity_handles_special_characters(): void
    {
        // Arrange
        $text1 = 'Hello, World!';
        $text2 = 'Hello World';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_numbers(): void
    {
        // Arrange
        $text1 = 'Version 2.0.1';
        $text2 = 'Version 2.0.2';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_is_commutative(): void
    {
        // Arrange
        $text1 = 'John Doe';
        $text2 = 'Jon Doe';

        // Act
        $score1 = $this->calculator->calculateSimilarity($text1, $text2);
        $score2 = $this->calculator->calculateSimilarity($text2, $text1);

        // Assert
        $this->assertSame($score1, $score2);
    }

    public function test_calculate_similarity_handles_very_long_texts(): void
    {
        // Arrange
        $text1 = str_repeat('Lorem ipsum dolor sit amet ', 100);
        $text2 = str_repeat('Lorem ipsum dolor sit amet ', 100);

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_single_characters(): void
    {
        // Arrange
        $text1 = 'a';
        $text2 = 'b';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertLessThan(0.5, $score);
    }

    public function test_calculate_similarity_handles_typos(): void
    {
        // Arrange
        $text1 = 'Laravel Framework';
        $text2 = 'Laravle Framewrok';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.8, $score);
    }

    public function test_calculate_similarity_handles_different_languages(): void
    {
        // Arrange
        $text1 = 'Bonjour le monde';
        $text2 = 'Bonjour le monde';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertSame(1.0, $score);
    }

    public function test_calculate_similarity_handles_french_accents(): void
    {
        // Arrange
        $text1 = 'Éléphant';
        $text2 = 'Elephant';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.9, $score);
    }

    public function test_calculate_similarity_handles_punctuation(): void
    {
        // Arrange
        $text1 = 'Hello, how are you?';
        $text2 = 'Hello how are you';

        // Act
        $score = $this->calculator->calculateSimilarity($text1, $text2);

        // Assert
        $this->assertGreaterThan(0.9, $score);
    }
}
