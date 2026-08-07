<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests;

abstract class MySqlTestCase extends IntegrationTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'hermes_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'Hello@0405'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        $app['config']->set('database.connections.sqlite', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isMySQL()) {
            $this->markTestSkipped('Ce test nécessite MySQL');
        }
    }
}
