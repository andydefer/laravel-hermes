<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests;

abstract class SqliteTestCase extends IntegrationTestCase
{
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

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isSQLite()) {
            $this->markTestSkipped('Ce test nécessite SQLite');
        }
    }
}
