<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Providers;

use AndyDefer\LaravelHermes\Configs\SimilarityConfig;
use AndyDefer\LaravelHermes\Contracts\Configs\SimilarityConfigInterface;
use AndyDefer\LaravelHermes\Contracts\Repositories\HermesRepositoryInterface;
use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Contracts\Services\SimilarityCalculatorInterface;
use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Services\HermesService;
use AndyDefer\LaravelHermes\Services\SimilarityCalculatorService;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\PhpServices\Configs\TextNormalizerConfig;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\Services\WordVectorGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerConfigInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Services\NGramGeneratorService;
use AndyDefer\PhpServices\Services\TextNormalizerService;
use AndyDefer\PhpServices\Services\WordVectorGeneratorService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel Hermes package.
 *
 * Registers all configuration, services, and interfaces for:
 * - Text similarity calculation
 * - N-gram generation
 * - Text normalization
 * - Vector generation
 * - Hermes completion, suggestion and search services
 *
 * @example
 * // In config/app.php
 * 'providers' => [
 *     AndyDefer\LaravelHermes\Providers\HermesServiceProvider::class,
 * ];
 */
final class HermesServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/hermes.php',
            'hermes'
        );

        // ============================================================
        // CONFIGURATIONS
        // ============================================================

        $this->app->singleton(SimilarityConfigInterface::class, function ($app) {
            return new SimilarityConfig(
                $app->make(ConfigRepository::class)
            );
        });

        $this->app->singleton(TextNormalizerConfigInterface::class, function ($app) {
            return new TextNormalizerConfig(
                $app->make(ConfigRepository::class)
            );
        });

        $this->app->alias(SimilarityConfigInterface::class, 'hermes.similarity.config');
        $this->app->alias(TextNormalizerConfigInterface::class, 'hermes.normalizer.config');

        // ============================================================
        // TEXT NORMALIZER
        // ============================================================

        $this->app->singleton(TextNormalizerInterface::class, function ($app) {
            return new TextNormalizerService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        $this->app->alias(TextNormalizerInterface::class, 'hermes.normalizer');

        // ============================================================
        // N-GRAM GENERATOR
        // ============================================================

        $this->app->singleton(NGramGeneratorInterface::class, function ($app) {
            return new NGramGeneratorService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        $this->app->alias(NGramGeneratorInterface::class, 'hermes.ngram.generator');

        // ============================================================
        // WORD VECTOR GENERATOR
        // ============================================================

        $this->app->singleton(WordVectorGeneratorInterface::class, function ($app) {
            return new WordVectorGeneratorService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        $this->app->alias(WordVectorGeneratorInterface::class, 'hermes.vector.generator');

        // ============================================================
        // SIMILARITY CALCULATOR
        // ============================================================

        $this->app->singleton(SimilarityCalculatorInterface::class, function ($app) {
            return new SimilarityCalculatorService(
                normalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                vectorGenerator: $app->make(WordVectorGeneratorInterface::class),
                config: $app->make(SimilarityConfigInterface::class),
            );
        });

        $this->app->singleton(SimilarityCalculatorService::class, function ($app) {
            return $app->make(SimilarityCalculatorInterface::class);
        });

        $this->app->alias(SimilarityCalculatorInterface::class, 'hermes.similarity.calculator');
        $this->app->alias(SimilarityCalculatorService::class, 'hermes.similarity.calculator');

        // ============================================================
        // HERMES REPOSITORY
        // ============================================================

        $this->app->singleton(HermesRepositoryInterface::class, function ($app) {
            return new HermesRepository(
                $app->make(IndexedTokenRepository::class)
            );
        });

        $this->app->alias(HermesRepositoryInterface::class, 'hermes.repository');

        // ============================================================
        // HERMES SERVICE
        // ============================================================

        $this->app->singleton(HermesInterface::class, function ($app) {
            return new HermesService(
                hermesRepository: $app->make(HermesRepositoryInterface::class),
                normalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                similarityCalculator: $app->make(SimilarityCalculatorInterface::class),
                config: $app->make(SimilarityConfigInterface::class),
            );
        });

        $this->app->singleton(HermesService::class, function ($app) {
            return $app->make(HermesInterface::class);
        });

        $this->app->alias(HermesInterface::class, 'hermes.service');
        $this->app->alias(HermesService::class, 'hermes.service');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/hermes.php' => config_path('hermes.php'),
        ], 'hermes-config');
    }
}
