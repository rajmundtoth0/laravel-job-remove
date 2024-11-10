<?php

namespace rajmundtoth0\LaravelJobRemove\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use rajmundtoth0\LaravelJobRemove\Services\LaravelJobRemoveService;
use RuntimeException;
use Throwable;

class LaravelJobRemoveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:remove
    {queue : The name of the queue}
    {job : The name of the job, set to `"all"` to remove all}
    {--L|limit=1 : The amount of jobs to remove, set to `"all"` to remove all}
    {--H|horizonConnectionName= : The name of the Horizon connection}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @throws Throwable
     * @throws RuntimeException
     */
    public function handle(): void
    {
        $connectionName = $this->choice(
            'Connection name?',
            $this->getConnectionsFromConfig(),
            multiple: false
        );

        $queueName = $this->argument('queue');
        throw_unless(is_string($queueName), 'Queue name must be a string');

        $jobName = $this->argument('job');
        throw_unless(is_string($jobName), 'Job name must be a string');

        $limit     = $this->option('limit');
        $removeAll = false;
        if ('all' === $limit) {
            $limit     = 1;
            $removeAll = true;
        }

        throw_unless(
            is_numeric($limit) && $limit > 0,
            'Limit must be a positive integer'
        );

        $horizonConnectionName = $this->option('horizonConnectionName') ?: '';
        throw_unless(is_string($horizonConnectionName), 'Horizon connection name must be a string');

        $laravelJobRemoveService = App::make(LaravelJobRemoveService::class, [
            'connectionName'        => $connectionName,
            'queueName'             => $queueName,
            'jobName'               => $jobName,
            'limit'                 => $limit,
            'horizonConnectionName' => $horizonConnectionName,
            'removeAll'             => $removeAll,
        ]);

        $result = $laravelJobRemoveService->removeJobs();
        $this->info("Removed {$result} jobs [{$jobName}] from queue: {$queueName}");
    }

    /**
     * @throws Throwable
     *
     * @return array<int, int|string>
     */
    private function getConnectionsFromConfig(): array
    {
        $connections = Config::get('database.redis', null);
        throw_unless(is_array($connections), 'No Redis connections found!');

        return array_keys(
            array_filter($connections, fn(array|string $connection): bool => is_array($connection))
        );
    }
}
