<?php

namespace rajmundtoth0\LaravelJobRemove\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

/** @package rajmundtoth0\LaravelJobRemove\Services */
final class LaravelJobRemoveService
{
    private ?PhpRedisConnection $horizonConnection = null;

    private ?PhpRedisConnection $queueConnection = null;

    private string $horizonPrefix;

    /**
     * @throws RuntimeException
     */
    public function __construct(
        private readonly string $jobName,
        private readonly string $connectionName,
        private readonly string $queueName,
        private readonly bool $removeAll,
        private int $limit,
        private string $horizonConnectionName,
        private int $jobsRemovedCounter = 0,
        private int $jobChunkSize = 999,
    ) {
        list($this->horizonPrefix, $this->horizonConnectionName) = $this->getConfigsFromHorizon();
        $chunkSize                                               = Config::get('job-remove.job_chunk_size', 999);
        assert(is_int($chunkSize));
        $this->jobChunkSize = $chunkSize;
    }

    /**
     * @throws TypeError
     */
    public function removeJobs(): int
    {
        if ($this->removeAll) {
            $this->limit = $this->jobChunkSize;
        }
        $queueData = $this->getQueueData();
        if (!$queueData) {
            return $this->jobsRemovedCounter;
        }

        $queue   = $this->getQueueString($this->queueName);
        $counter = 0;
        foreach ($queueData as $index => $job) {
            $encodedJob = $this->getDecodedJob($job);
            if (
                !$this->removeAll
                && $encodedJob->displayName !== $this->jobName
            ) {
                continue;
            }

            $horizonJobId = "{$this->horizonPrefix}{$encodedJob->id}";
            if (!$this->checkJobStatus($horizonJobId)) {
                continue;
            }

            $this->getQueueConnection()
            /** @phpstan-ignore-next-line */
                ->lrem($queue, $index, $job);

            $this->getHorizonConnection()
                ->del($horizonJobId);

            $counter++;
        }
        $this->jobsRemovedCounter += $counter;
        if ($this->removeAll && $this->jobChunkSize === count($queueData)) {
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

        /** @var PhpRedisConnection */
        $connection            = Redis::connection($this->connectionName);
        $this->queueConnection = $connection;

        return $this->queueConnection;
    }

    private function getHorizonConnection(): PhpRedisConnection
    {
        if ($this->horizonConnection) {
            return $this->horizonConnection;
        }

        /** @var PhpRedisConnection */
        $connection              = Redis::connection($this->horizonConnectionName);
        $this->horizonConnection = $connection;

        return $this->horizonConnection;
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
    private function getQueueData(): array
    {
        $queueData = $this->getQueueConnection()
            ->lrange(
                'queues:' . $this->queueName,
                $this->jobsRemovedCounter,
                $this->limit
            );

        throw_unless(is_array($queueData), 'Invalid response from Redis!');

        /** @var array<int, string> */
        return $queueData;
    }

    private function getQueueString(string $queueName): string
    {
        return 'queues:' . $queueName;
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
