<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $databaseName = (string) config('database.connections.'.config('database.default').'.database');

        if ($databaseName === '' || ! str_ends_with($databaseName, '_test')) {
            throw new RuntimeException("Testing database must use a dedicated '*_test' database. Current database: [{$databaseName}].");
        }

        // Use array session driver for all tests to avoid database session issues
        $this->app['config']->set('session.driver', 'array');
    }
}
