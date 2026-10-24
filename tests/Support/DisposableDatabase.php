<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class DisposableDatabase
{
    public const MARKER = 'waspro-disposable-tests';

    public static function verify(Application $app): void
    {
        $connectionName = $app['config']->get('database.default');
        $configuration = $app['config']->get("database.connections.{$connectionName}", []);

        self::assertConfiguration(
            $app->environment(),
            $app['config']->get('testing.allow_destructive_database_tests', false),
            $configuration,
        );

        $identity = $app['db']->connection($connectionName)->selectOne(
            "SELECT current_database() AS name, shobj_description(oid, 'pg_database') AS marker FROM pg_database WHERE datname = current_database()",
        );

        self::assertIdentity((string) $configuration['database'], $identity->name, $identity->marker);
    }

    public static function assertConfiguration(string $environment, mixed $allowed, array $configuration): void
    {
        if ($environment !== 'testing' || $allowed !== true) {
            throw new RuntimeException('Destructive tests require APP_ENV=testing and WASPRO_ALLOW_DESTRUCTIVE_TESTS=true.');
        }

        if (($configuration['driver'] ?? null) !== 'pgsql'
            || ! empty($configuration['url'])
            || ! str_ends_with((string) ($configuration['database'] ?? ''), '_test')) {
            throw new RuntimeException('Destructive tests require an explicit PostgreSQL *_test database without DB_URL.');
        }
    }

    public static function assertIdentity(string $expected, string $actual, ?string $marker): void
    {
        if ($expected !== $actual || $marker !== self::MARKER) {
            throw new RuntimeException('Refusing destructive tests: the connected database is not the explicitly marked disposable target.');
        }
    }
}
