<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use Illuminate\Console\Command;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class LaravelJobRemoveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:remove
    {queue : The name of the queue}
    {job : The name of the job, set to * to remove all}
    {--L|limit=1 : The amount of jobs to remove, set to -1 to remove all}
    {--H|horizonConnectionName= : The name of the Horizon connection}';
    // {--job=App\Jobs\TestJob : The name of the job}
    // {--amount=1 : The amount of jobs to remove}';

    private readonly array $horizonConfig;
    private PhpRedisConnection $horizonConnection;
    private PhpRedisConnection $queueConnection;
    private string $horizonPrefix;
    private string $jobName;

    private const int JOB_CHUNK_SIZE = 1_000;

    public function __construct(

    ) {
        parent::__construct();
        $this->horizonConfig = $this->getHorizonConfig();
    }
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle(): void
    {
        $queueName = $this->argument('queue');
        $this->jobName = $this->argument('job');
        $limit = (int)$this->option('limit');
        if ($this->option('horizonConnectionName')) {
            $horizonConnectionName = $this->option('horizonConnectionName');
        } else {
            $horizonConnectionName = $this->horizonConfig['use'];
        }

        $connectionName = $this->choice(
            'Connection name?',
            $this->getConnectionsFromConfig(),
            multiple: false
        );

        $this->queueConnection = Queue::getRedis()
            ->connection($connectionName);
        $this->horizonConnection = $this->getHorizonConnection(
            connectionName: $horizonConnectionName,
        );
        $this->horizonPrefix = $this->horizonConfig['prefix'];
        $this->removeJobs(
            queueName: $queueName,
            limitArg: $limit,
        );

    }

    private function removeJobs(string $queueName, int $limitArg): void
    {
        $limit = $limitArg === -1 ? self::JOB_CHUNK_SIZE : $limitArg;

        $queueData = $this->getQueueData(
            queueName: $queueName,
            connection: $this->queueConnection,
            limit: $limit - 1,
        );
        if (!$queueData) {
            dump("No jobs [{$this->jobName}] found in queue: {$queueName}");

            return;
        }

        $queue = $this->getQueueString($queueName);
        $counter = 0;
        foreach ($queueData as $index => $job) {
            $encodedJob = json_decode($job);
            if ($this->jobName !== '*' && $encodedJob->displayName !== $this->jobName) {
                continue;
            }
            $this->queueConnection
                ->lrem($queue, $index, $job);

            $horizonJobId = "{$this->horizonPrefix}{$encodedJob->id}";
            $horizonJob = $this->horizonConnection->hmget(
                $horizonJobId,
                ['status']
            );
            if (
                !$horizonJob
                || !array_key_exists('status', $horizonJob)
                || $horizonJob['status'] !== 'pending'
            ) {
                continue;
            }

            $this->horizonConnection
                ->del($horizonJobId);

            $this->horizonConnection
                ->del($horizonJobId);
            $counter++;
        }

        dump("Removed {$counter} jobs [{$this->jobName}] from queue: {$queueName}");

        if ($limitArg === -1 && $counter) {
            $this->removeJobs(
                queueName: $queueName,
                limitArg: $limitArg,
            );
        }

    }

    private function getQueueData(string $queueName, PhpRedisConnection $connection, int $limit): array
    {
        return $connection
            ->lrange('queues:' . $queueName, 0, $limit);
    }

    private function getQueueString(string $queueName): string
    {
        return 'queues:' . $queueName;
    }

    private function getHorizonConnection(string $connectionName): PhpRedisConnection
    {
        return Queue::getRedis()
            ->connection($connectionName);
    }

    private function getHorizonConfig(): array
    {
        return Config::get('horizon', []);
    }

    private function getConnectionsFromConfig(): array
    {
        $connections = Config::get('database.redis', null);
        throw_unless(is_array($connections) || $connections, 'No Redis connections found!');

        return array_keys(
            array_filter($connections, fn(array|string $connection): bool => is_array($connection))
        );
    }

    public function handle1(): void
    {

        // $connectionName = $this->choice(
        //     'Connection name?',
        //     $this->getConnectionsFromConfig(),
        //     multiple: false
        // );
        $connectionName = 'default';

        // $queueName = $this->ask('Queue name?');
        $queueName = 'admin_horizon';
        // $jobName = $this->ask('Job name?');
        $jobName = TestJob::class;
        // $amount = $this->ask('Amount?', 1);
        $amount = 55;
        // foreach (range(0, 15) as $_) {
        //     dispatch(new TestJob());
        // }
        $connection = Queue::getRedis()
            ->connection($connectionName);
        $data = $connection
            ->lrange('queues:' . $queueName, 0, -1);
        // ->zrange('queues:' . $queueName . ':delayed', 0, -1);
        $count = 0;
        foreach ($data as $index => $job) {
            $jobJ = json_decode($job);
            if ($count < 2) {
                $count++;

                continue;
            }
            if ($jobJ->displayName === $jobName && $amount > 0) {
                $connId = 'admin_horizon:' . $jobJ->uuid;
                $result = $connection->del($connId);

                if ($result === 1) {
                    dump($connId);
                    dd($result);
                }

            }
        }
        $data = $connection
            ->lrange('queues:' . $queueName, 0, -1);
        dd($data);
        // $firstJob = json_decode($data[0]);
        // $redis = Redis::keys('admin_horizon:12e2071b-a412-410a-8d8a-68721001ce01');
        // //$result = $connection->del('admin_horizon:12e2071b-a412-410a-8d8a-68721001ce01');
        // $data = $connection
        // ->lrange('queues:default', 0, -1);
        // var_dump(
        //     $data
        // );
    }
}
//   string(484) "{"uuid":"12e2071b-a412-410a-8d8a-68721001ce01","displayName":"App\\Jobs\\TestJob","job":"Illuminate\\Queue\\CallQueuedHandler@call",
// "maxTries":null,"maxExceptions":null,"failOnTimeout":false,"backoff":null,"timeout":null,"retryUntil":null,
// "data":{"commandName":"App\\Jobs\\TestJob","command":"O:16:\"App\\Jobs\\TestJob\":1:{s:5:\"queue\";s:7:\"default\";}"},
// "id":"12e2071b-a412-410a-8d8a-68721001ce01","attempts":0,"type":"job","tags":[],"silenced":false,"pushedAt":"1729411372.3932"}"
