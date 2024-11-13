<?php

namespace rajmundtoth0\LaravelJobRemove\Tests\Feature;

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\PendingCommand;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use rajmundtoth0\LaravelJobRemove\Tests\TestCase;
use rajmundtoth0\LaravelJobRemove\Tests\TestJob;
use RuntimeException;
use stdClass;
/**
 * @internal
 */
class LaravelJobRemoveCommandTest extends TestCase
{
    use WithConsoleEvents;

    private MockInterface $redis;

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
            'retry_after'  => 5 * 60 + 3 * 60 * 60,
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

        $this->redis = $this->getMockedRedisConnection();
        $this->setUpRedisFacade();
    }

    private function getMockedRedisConnection(): MockInterface
    {
        return Mockery::mock(PhpRedisConnection::class);
    }

    private function setUpRedisFacade(): void
    {
        Redis::shouldReceive('connection')
            ->with('queue')
            ->andReturn($this->redis);
        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($this->redis);
    }

    /**
     * @param array<int|string, int|string> $jobStrings
     */
    private function mockLrangeCommand(array $jobStrings, int $stop = 2, int $start = 0): void
    {
        $this->redis->shouldReceive('lrange')
            ->withArgs(['queues:queue', $start, $stop])
           ->once()
           ->andReturn($jobStrings)
           ->once();
    }

    private function mockLremCommand(int $index, string $jobString): void
    {
        $this->redis->shouldReceive('lrem')
            ->withArgs(['queues:queue', $index, $jobString])
            ->andReturn(1)
            ->once();
    }

    private function mockHmgetCommand(string $jobId): void
    {
        $this->redis->shouldReceive('hmget')
            ->withArgs(["horizon:{$jobId}", ['status']])
            ->andReturn(['pending'])
            ->once();
    }

    private function mockDelCommand(string $jobId): void
    {
        $this->redis->shouldReceive('del')
            ->withArgs(["horizon:{$jobId}"])
            ->andReturn(1)
            ->once();
    }

    private function decodeJobString(string $jobString): stdClass
    {
        $decodedJob = json_decode($jobString, false);
        assert($decodedJob instanceof stdClass);

        return $decodedJob;
    }

    private function getCommand(?string $queue, ?string $jobName, string|int $limit): PendingCommand
    {
        $command = $this->artisan('queue:remove', [
            'queue'   => $queue,
            'job'     => $jobName,
            '--limit' => $limit,
        ]);

        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * @throws RuntimeException
     */
    #[DataProvider('itRemovesSpecifiedJobFromQueueCases')]
    public function testItRemovesSpecifiedJobFromQueue(int $limit, int $removedJobs): void
    {
        $jobString      = $this->getJobString();
        $decodedJob     = $this->decodeJobString($jobString);
        $otherJobString = $this->getJobString('OtherJob.json');

        $this->mockLrangeCommand(
            jobStrings: [$jobString, $otherJobString],
            stop: $limit,
        );
        $this->mockLremCommand(index: 0, jobString: $jobString);
        $this->mockHmgetCommand(jobId: $decodedJob->id);
        $this->mockDelCommand(jobId: $decodedJob->id);

        $jobName = TestJob::class;
        $this->getCommand(
            queue: 'queue',
            jobName: $jobName,
            limit: $limit,
        )
        ->expectsQuestion('Connection name?', 'queue')
        ->expectsOutputToContain("Removed {$removedJobs} jobs [{$jobName}] from queue: queue")
        ->assertExitCode(0);
    }

    /**
     * @throws RuntimeException
     */
    public function testSkipsJobRemovalIfJobIsNotPending(): void
    {
        $jobString       = $this->getJobString();
        $decodedJob      = $this->decodeJobString($jobString);
        $otherJobString  = $this->getJobString('OtherJob.json');
        $decodedOtherJob = $this->decodeJobString($otherJobString);

        $this->mockLrangeCommand(
            jobStrings: [$jobString, $otherJobString],
            stop: 999,
        );
        $this->mockHmgetCommand(jobId: $decodedJob->id);
        $this->mockLremCommand(index: 0, jobString: $jobString);
        $this->mockDelCommand(jobId: $decodedJob->id);
        $this->redis->shouldReceive('hmget')
            ->withArgs(["horizon:{$decodedOtherJob->id}", ['status']])
            ->andReturn(['started'])
            ->once();

        $this->getCommand(
            queue: 'queue',
            jobName: '*',
            limit: 'all',
        )
            ->expectsQuestion('Connection name?', 'queue')
            ->expectsOutputToContain("Removed 1 jobs [*] from queue: queue")
            ->assertExitCode(0);
    }

    public function testItExistsOnEmptyQueue(): void
    {
        $this->mockLrangeCommand(jobStrings: [], stop: 999);

        $this->getCommand(
            queue: 'queue',
            jobName: '*',
            limit: 'all',
        )
            ->expectsQuestion('Connection name?', 'queue')
            ->expectsOutputToContain("Removed 0 jobs [*] from queue: queue")
            ->assertExitCode(0);
    }

    /**
     * @throws RuntimeException
     */
    public function testItRemovesAllJobsFromQueue(): void
    {
        $jobString       = $this->getJobString();
        $decodedJob      = $this->decodeJobString($jobString);
        $otherJobString  = $this->getJobString('OtherJob.json');
        $decodedOtherJob = $this->decodeJobString($otherJobString);

        $this->mockLrangeCommand(
            jobStrings: [$jobString, $otherJobString],
            stop: 999,
        );
        $this->mockLremCommand(index: 0, jobString: $jobString);
        $this->mockHmgetCommand(jobId: $decodedJob->id);
        $this->mockDelCommand(jobId: $decodedJob->id);
        $this->mockLremCommand(index:1, jobString: $otherJobString);
        $this->mockHmgetCommand(jobId: $decodedOtherJob->id);
        $this->mockDelCommand(jobId: $decodedOtherJob->id);

        $this->getCommand(
            queue: 'queue',
            jobName: '*',
            limit: 'all',
        )
            ->expectsQuestion('Connection name?', 'queue')
            ->expectsOutputToContain("Removed 2 jobs [*] from queue: queue")
            ->assertExitCode(0);
    }

    /**
     * @throws RuntimeException
     */
    public function testItRemovesAllJobsFromQueueInChunks(): void
    {
        Config::set('job-remove.job_chunk_size', 1);
        $jobString       = $this->getJobString();
        $decodedJob      = $this->decodeJobString($jobString);
        $otherJobString  = $this->getJobString('OtherJob.json');
        $decodedOtherJob = $this->decodeJobString($otherJobString);

        $this->mockLrangeCommand(
            jobStrings: [$jobString],
            stop: 1,
            start: 0,
        );
        $this->mockLremCommand(index: 0, jobString: $jobString);
        $this->mockHmgetCommand(jobId: $decodedJob->id);
        $this->mockDelCommand(jobId: $decodedJob->id);
        $this->mockLrangeCommand(
            jobStrings: [$otherJobString],
            stop: 1,
            start: 1,
        );
        $this->mockLremCommand(index:0, jobString: $otherJobString);
        $this->mockHmgetCommand(jobId: $decodedOtherJob->id);
        $this->mockDelCommand(jobId: $decodedOtherJob->id);

        $this->mockLrangeCommand(
            jobStrings: [],
            stop: 1,
            start: 2,
        );
        $this->getCommand(
            queue: 'queue',
            jobName: '*',
            limit: 'all',
        )
            ->expectsQuestion('Connection name?', 'queue')
            ->expectsOutputToContain("Removed 2 jobs [*] from queue: queue")
            ->assertExitCode(0);
    }

    #[DataProvider('itThrowsExceptionCases')]
    public function testItThrowsException(?string $queueName, ?string $jobName, int|string $limit, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->getCommand(
            queue: $queueName,
            jobName:$jobName,
            limit: $limit,
        )
        ->expectsQuestion('Connection name?', 'queue');
    }

    /**
     * @return array<int, array{queueName: null|string, jobName: null|string, limit: int|string, message: string}>
     */
    public static function itThrowsExceptionCases(): array
    {
        return [
            [
                'queueName' => null,
                'jobName'   => TestJob::class,
                'limit'     => 1,
                'message'   => 'Queue name must be a string',
            ],
            [
                'queueName' => 'default',
                'jobName'   => null,
                'limit'     => 1,
                'message'   => 'Job name must be a string',
            ],
            [
                'queueName' => 'default',
                'jobName'   => TestJob::class,
                'limit'     => 'a',
                'message'   => 'Limit must be a positive integer',
            ],
            [
                'queueName' => 'default',
                'jobName'   => TestJob::class,
                'limit'     => 'a',
                'message'   => 'Limit must be a positive integer',
            ],
        ];
    }

    /**
     * @return array<int, array{limit: int, removedJobs: int}>
     */
    public static function itRemovesSpecifiedJobFromQueueCases(): array
    {
        return [
            [
                'limit'       => 1,
                'removedJobs' => 1,
            ],
            [
                'limit'       => 2,
                'removedJobs' => 1,
            ],
            [
                'limit'       => 3,
                'removedJobs' => 1,
            ],
        ];
    }
}
