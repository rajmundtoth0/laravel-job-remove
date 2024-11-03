<?php

namespace rajmundtoth0\LaravelJobRemove\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use rajmundtoth0\LaravelJobRemove\Tests\TestCase;
use rajmundtoth0\LaravelJobRemove\Tests\TestJob;

/**
 * @internal
 */
class LaravelJobRemoveCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_removes_specified_job_from_queue(): void
    {
        Config::set('horizon', [
            'use'    => 'default',
            'prefix' => 'horizon:',
        ]);
        dispatch(new TestJob());

        Queue::shouldReceive('getRedis')
            ->andReturnSelf();

        Queue::assertPushed(TestJob::class);

        $this->artisan('queue:remove', [
            'queue'   => 'default',
            'job'     => TestJob::class,
            '--limit' => 1,
        ])
            ->expectsQuestion('Connection name?', 'queue');

        Queue::assertNotPushed(TestJob::class);
    }

    // public function test_it_removes_all_jobs_from_queue(): void
    // {
    //     TestJob::dispatch();
    //     TestJob::dispatch();

    //     Queue::assertPushed(TestJob::class, 2);

    //     Artisan::call('queue:remove', [
    //         'queue'   => 'default',
    //         'job'     => '*',
    //         '--limit' => -1,
    //     ]);

    //     Queue::assertNotPushed(TestJob::class);
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

    // public function test_it_throws_exception_for_invalid_queue_name(): void
    // {
    //     $this->expectException(\RuntimeException::class);

    //     Artisan::call('queue:remove', [
    //         'job'     => TestJob::class,
    //         '--limit' => 1,
    //     ]);
    // }

    // public function test_it_throws_exception_for_invalid_job_name(): void
    // {
    //     $this->expectException(\RuntimeException::class);

    //     Artisan::call('queue:remove', [
    //         'queue'   => 'default',
    //         '--limit' => 1,
    //     ]);
    // }

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
