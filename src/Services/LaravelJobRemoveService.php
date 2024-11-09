<?php

namespace rajmundtoth0\LaravelJobRemove\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use stdClass;
use Throwable;

/** @package rajmundtoth0\LaravelJobRemove\Services */
final class LaravelJobRemoveService
{
    private const int JOB_CHUNK_SIZE = 1_000;

    private ?PhpRedisConnection $horizonConnection = null;

    private ?PhpRedisConnection $queueConnection = null;

    private string $horizonPrefix;

    public function __construct(
        private readonly string $jobName,
        private readonly string $connectionName,
        private readonly int $limit,
        private readonly string $queueName,
        private string $horizonConnectionName,
        private int $jobsRemovedCounter = 0,
    ) {
        list($this->horizonPrefix, $this->horizonConnectionName) = $this->getConfigsFromHorizon();
    }

    public function removeJobs(): int
    {
        $limit     = -1 === $this->limit ? self::JOB_CHUNK_SIZE : $this->limit;
        $limit     = 2; // FIX
        $queueData = $this->getQueueData(
            limit: $limit - 1,
        );

        $queue   = $this->getQueueString($this->queueName);
        $counter = 0;
        foreach ($queueData as $index => $job) {
            $encodedJob = $this->getDecodedJob($job);
            if ('*' !== $this->jobName && $encodedJob->displayName !== $this->jobName) {
                continue;
            }

            $this->getQueueConnection()
                ->lrem($queue, $index, $job);

            $horizonJobId = "{$this->horizonPrefix}{$encodedJob->id}";
            if (!$this->checkJobStatus($horizonJobId)) {
                continue;
            }

            $this->getHorizonConnection()
                ->del($horizonJobId);

            $counter++;
        }

        $this->jobsRemovedCounter += $counter;
        if (-1 === $this->limit && $counter) {
            $this->removeJobs();
        }

        return $this->jobsRemovedCounter;
    }

    /**
     * @throws Throwable
     *
     * @return stdClass
     */
    private function getDecodedJob(string $job): object
    {
        $encodedJob = json_decode($job, false, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
        throw_unless(
            $encodedJob && $encodedJob instanceof stdClass,
            'Invalid job data found!'
        );

        return $encodedJob;
    }

    private function getQueueConnection(): Connection
    {
        if ($this->queueConnection) {
            return $this->queueConnection;
        }

        return Redis::connection($this->connectionName);
    }

    private function getHorizonConnection(): PhpRedisConnection
    {
        if ($this->horizonConnection) {
            return $this->horizonConnection;
        }

        return Redis::connection($this->horizonConnectionName);
    }

    private function checkJobStatus(string $horizonJobId): bool
    {
        $horizonJob = $this->getHorizonConnection()
            ->hmget(
                $horizonJobId,
                ['status']
            );
        if (
            !$horizonJob
            || !array_key_exists('status', $horizonJob)
            || 'pending' !== $horizonJob['status'] // check statuses
        ) {
            return false;
        }

        return true;
    }

    /**
     * @throws Throwable
     *
     * @return array<int, string>
     */
    private function getQueueData(int $limit): array
    {
        //dd($this->getQueueConnection());
        //dd($limit);
        $queueData = $this->getQueueConnection()
            ->lrange('queues:'.$this->queueName, 0, $limit);
        if (!$queueData) {
            exit("No jobs [{$this->jobName}] found in queue: {$this->queueName}");
        }

        throw_unless(is_array($queueData), 'Invalid response from Redis!');

        /** @var array<int, string> */
        return $queueData;
    }

    private function getQueueString(string $queueName): string
    {
        return 'queues:'.$queueName;
    }

    /**
     * @throws Throwable
     *
     * @return array<int, string>
     */
    private function getConfigsFromHorizon(): array
    {
        $config = Config::get('horizon', null);

        throw_unless(is_array($config), 'No Horizon config found!');
        throw_unless(
            array_key_exists('prefix', $config) && is_string($config['prefix']),
            'Horizon prefix is invalid or not found!'
        );
        throw_unless(
            array_key_exists('use', $config) && is_string($config['use']),
            'Horizon connection name is invalid or not found!'
        );

        return [
            $config['prefix'],
            $config['use'],
        ];
    }
}
