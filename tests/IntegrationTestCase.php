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
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('database.connections.mysql', []);
    }

    /**
     * Définit l'environnement de test avec MySQL par défaut.
     */
    /* protected function defineEnvironment($app): void
    {
        // Connexion MySQL par défaut
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'hermes_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }
 */
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
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }

        $indexerMigrations = realpath(__DIR__.'/../vendor/andydefer/laravel-indexer/database/migrations');
        if ($indexerMigrations !== false && is_dir($indexerMigrations)) {
            $this->loadMigrationsFrom($indexerMigrations);
        }

        $hermesMigrations = __DIR__.'/../database/migrations';
        if (is_dir($hermesMigrations)) {
            $this->loadMigrationsFrom($hermesMigrations);
        }
    }

    protected function isMySQL(): bool
    {
        return config('database.default') === 'mysql';
    }

    protected function isSQLite(): bool
    {
        return config('database.default') === 'sqlite';
    }

    protected function requireMySQL(): void
    {
        if (! $this->isMySQL()) {
            $this->markTestSkipped('Ce test nécessite MySQL');
        }
    }

    protected function requireSQLite(): void
    {
        if (! $this->isSQLite()) {
            $this->markTestSkipped('Ce test nécessite SQLite');
        }
    }
}
