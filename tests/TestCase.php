<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DisposableDatabase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Verify the real target before RefreshDatabase/DatabaseMigrations traits run.
        DisposableDatabase::verify($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Use array session driver for all tests to avoid database session issues
        $this->app['config']->set('session.driver', 'array');
    }
}
