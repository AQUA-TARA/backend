<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestEnvironmentGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Intercept test application bootstrapping immediately when instantiated.
     * Evaluates resolved configuration before setUpTraits() runs RefreshDatabase/transactions.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->assertSafeTestEnvironment($app);

        return $app;
    }

    /**
     * Defense-in-depth: Re-verify before each test method execution.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestEnvironment($this->app);
    }

    /**
     * Delegate to pure, isolated guard logic.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    protected function assertSafeTestEnvironment($app): void
    {
        $config = $app['config'];
        $defaultConnection = (string) $config->get('database.default', 'pgsql');
        $connections = (array) $config->get('database.connections', []);

        TestEnvironmentGuard::assertSafeEnvironment($defaultConnection, $connections);
    }
}
