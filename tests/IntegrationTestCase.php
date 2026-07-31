<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests;

use AndyDefer\LaravelCluster\Providers\ClusterServiceProvider;
use AndyDefer\LaravelHermes\Providers\HermesServiceProvider;
use AndyDefer\LaravelIndexer\Providers\IndexerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected string $databasePath;

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClusterServiceProvider::class,
            IndexerServiceProvider::class,
            HermesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        // 1. Charger les migrations des fixtures (modèles de test)
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }

        // 2. Charger les migrations du package laravel-indexer
        $indexerMigrations = realpath(__DIR__.'/../vendor/andydefer/laravel-indexer/database/migrations');
        if ($indexerMigrations !== false && is_dir($indexerMigrations)) {
            $this->loadMigrationsFrom($indexerMigrations);
        }

        // 3. Charger les migrations du package laravel-hermes
        $hermesMigrations = __DIR__.'/../database/migrations';
        if (is_dir($hermesMigrations)) {
            $this->loadMigrationsFrom($hermesMigrations);
        }
    }
}
