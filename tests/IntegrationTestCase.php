<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests;

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
        $migrationPath = __DIR__.'/Fixtures/migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
        $packageMigrations = __DIR__.'/../database/migrations';
        if (is_dir($packageMigrations)) {
            $this->loadMigrationsFrom($packageMigrations);
        }
    }
}
