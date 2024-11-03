<?php

namespace rajmundtoth0\LaravelJobRemove\Tests;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

use rajmundtoth0\LaravelJobRemove\LaravelJobRemoveServiceProvider;
/**
 * @internal
 */
class TestCase extends Orchestra
{
    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /** @return list<class-string<ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelJobRemoveServiceProvider::class,
        ];
    }
}
