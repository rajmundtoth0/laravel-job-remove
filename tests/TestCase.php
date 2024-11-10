<?php

namespace rajmundtoth0\LaravelJobRemove\Tests;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use rajmundtoth0\LaravelJobRemove\LaravelJobRemoveServiceProvider;

use RuntimeException;
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

    /**
     * @throws RuntimeException
     */
    public function getJobString(string $jobName = 'TestJob.json'): string
    {
        return File::get(__DIR__ . '/Misc/' . $jobName);
    }
}
