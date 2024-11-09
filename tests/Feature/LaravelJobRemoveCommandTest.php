<?php

namespace rajmundtoth0\LaravelJobRemove\Tests\Feature;

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use illuminate\support\Str;
use Mockery;
use rajmundtoth0\LaravelJobRemove\Tests\TestCase;
use rajmundtoth0\LaravelJobRemove\Tests\TestJob;

/**
 * @internal
 */
class LaravelJobRemoveCommandTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('horizon', [
            'use'    => 'default',
            'prefix' => 'horizon:',
        ]);
        Config::set('queue.connections', [
            'driver'       => 'redis',
            'connection'   => 'queue',
            'queue'        => 'default',
            'retry_after'  => 5 * 60 + 3 * 60 * 60, // Five minutes after the longest job timeout
            'block_for'    => null,
            'after_commit' => true,
        ]);
        Config::set('database', [
            'redis' => [
                'queue' => [
                    'url'      => env('REDIS_URL'),
                    'host'     => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD', null),
                    'port'     => env('REDIS_PORT', '6379'),
                    'database' => 15,
                ],
                'default' => [
                    'url'      => env('REDIS_URL'),
                    'host'     => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD', null),
                    'port'     => env('REDIS_PORT', '6379'),
                    'database' => 1,
                ],
            ]
        ]);
        Queue::fake();
    }

    private function getJobString(): string
    {
        $uuid = Str::uuid()->toString();

        return '{"uuid":"'.$uuid.'",
                "displayName":"rajmundtoth0\\\\LaravelJobRemove\\\\Tests\\\\TestJob",
                "job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call",
                "maxTries":"null",
                "maxExceptions":"null",
                "failOnTimeout":false,
                "backoff":"null","timeout":"null"
                ,"retryUntil":"null","data":{
                "commandName":"rajmundtoth0\\\\LaravelJobRemove\\\\Tests\\\\TestJob",
                "command":"O:16:\\"App\\\\Jobs\\\\TestJob\\":1:{s:5:\\"queue\\";s:7:\\"default\\";}"},
                "id":"'.$uuid.'","attempts":0,"type":"job",
                "tags":[],"silenced":false,"pushedAt":"1729411372.3932"}';
    }

    private function mockRedis(int $jobCount = 1): void
    {
        $redis = Mockery::mock(PhpRedisConnection::class);
        Redis::shouldReceive('connection')
            ->with('queue')
            ->andReturn($redis
            );
        Redis::shouldReceive('connection')
        ->with('default')
        ->andReturn($redis
        );
        $jobString = $this->getJobString();
        $redis->shouldReceive('lrange')
            ->withArgs(['queues:queue', 0, 1])
            ->andReturn([
                $jobString
            ])
        ->once()
        ->shouldReceive('lrem')
        ->withArgs(['queues:queue', 0, $jobString])
        ->andReturn(1)
        ->once()
        ->shouldReceive('hmget')
        ->withArgs(['horizon:12e2071b-a412-410a-8d8a-68721001ce01', ['status']])
        ->andReturn(['status' => 'pending'])
        ->once()
        ->shouldReceive('del')
        ->withArgs(['horizon:12e2071b-a412-410a-8d8a-68721001ce01'])
        ->andReturn(1)
        ->once();
    }

    public function test_it_removes_specified_job_from_queue(): void
    {
        $this->mockRedis();

        $jobName = TestJob::class;
        $result  = $this->artisan('queue:remove', [
            'queue'   => 'queue',
            'job'     => $jobName,
            '--limit' => 1,
        ])
            ->expectsQuestion('Connection name?', 'queue')
            ->expectsOutputToContain("Removed 1 jobs [{$jobName}] from queue: queue")
            ->assertExitCode(0);
    }

    // public function test_it_removes_all_jobs_from_queue(): void
    // {
    //     $this->mockRedis(jobCount: 2);

    //     $jobName = TestJob::class;
    //     $result  = $this->artisan('queue:remove', [
    //         'queue'   => 'queue',
    //         'job'     => $jobName,
    //         '--limit' => 1,
    //     ])
    //         ->expectsQuestion('Connection name?', 'queue')
    //         ->expectsOutputToContain("Removed 2 jobs [{$jobName}] from queue: queue")
    //         ->assertExitCode(0);
    // }

    // public function test_it_removes_jobs_with_limit(): void
    // {
    //     TestJob::dispatch();
    //     TestJob::dispatch();

    //     Queue::assertPushed(TestJob::class, 2);

    //     Artisan::call('queue:remove', [
    //         'queue'   => 'default',
    //         'job'     => TestJob::class,
    //         '--limit' => 1,
    //     ]);

    //     Queue::assertPushed(TestJob::class, 1);
    // }

    public function test_it_throws_exception_for_invalid_queue_name(): void
    {
        $this->expectException(\RuntimeException::class);

        Artisan::call('queue:remove', [
            'job'     => TestJob::class,
            '--limit' => 1,
        ]);
    }

    public function test_it_throws_exception_for_invalid_job_name(): void
    {
        $this->expectException(\RuntimeException::class);

        Artisan::call('queue:remove', [
            'queue'   => 'default',
            '--limit' => 1,
        ]);
    }

    // public function test_it_throws_exception_for_invalid_limit(): void
    // {
    //     $this->expectException(\RuntimeException::class);

    //     Artisan::call('queue:remove', [
    //         'queue'   => 'default',
    //         'job'     => TestJob::class,
    //         '--limit' => 'invalid',
    //     ]);
    // }
}
