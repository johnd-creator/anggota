<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor {--failed} {--pending} {--busted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue workers and job status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('failed')) {
            return $this->showFailedJobs();
        }

        if ($this->option('pending')) {
            return $this->showPendingJobs();
        }

        if ($this->option('busted')) {
            return $this->showBustedJobs();
        }

        // Default: Show overview
        return $this->showOverview();
    }

    /**
     * Show queue overview
     */
    protected function showOverview(): int
    {
        $this->info('Queue Overview');
        $this->newLine();

        // Get queue sizes
        $queues = ['default', 'exports', 'emails'];

        $this->table(['Queue', 'Pending Jobs', 'Status'], $this->getQueueStatuses($queues));

        // Redis health check
        $this->newLine();
        $this->info('Redis Connection:');
        try {
            Redis::connection()->ping();
            $this->line('  <fg=green>✓</> Connected');
        } catch (\Exception $e) {
            $this->error('  ✗ Failed to connect: '.$e->getMessage());

            return 1;
        }

        // Worker info
        $this->newLine();
        $this->info('Worker Status:');
        $this->line('  Run <fg=yellow>php artisan queue:work</> to start workers');
        $this->line('  Run <fg=yellow>php artisan queue:listen</> to listen (single worker)');
        $this->line('  Run <fg=yellow>queue:monitor --failed</> to show failed jobs');

        return Command::SUCCESS;
    }

    /**
     * Show failed jobs
     */
    protected function showFailedJobs(): int
    {
        $this->info('Failed Jobs');
        $this->newLine();

        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(20)
                ->get();

            if ($failedJobs->isEmpty()) {
                $this->info('No failed jobs found');

                return Command::SUCCESS;
            }

            $this->table(
                ['ID', 'Queue', 'Connection', 'Failed At', 'Exception'],
                $failedJobs->map(function ($job) {
                    return [
                        substr($job->id, 0, 8).'...',
                        $job->queue,
                        $job->connection,
                        $job->failed_at,
                        substr($job->exception, 0, 100).'...',
                    ];
                })->toArray()
            );

            $this->newLine();
            $this->warn('Found '.$failedJobs->count().' failed jobs');
            $this->line('  Run <fg=yellow>php artisan queue:flush</> to delete all failed jobs');
            $this->line('  Run <fg=yellow>php artisan queue:retry {id}</> to retry a specific job');

        } catch (\Exception $e) {
            $this->error('Failed to retrieve failed jobs: '.$e->getMessage());

            return 1;
        }

        return Command::SUCCESS;
    }

    /**
     * Show pending jobs
     */
    protected function showPendingJobs(): int
    {
        $this->info('Pending Jobs');
        $this->newLine();

        try {
            // Get queue sizes from Redis
            $queues = config('queue.connections.redis.queue', ['default', 'exports', 'emails']);

            foreach ($queues as $queue) {
                $queueName = 'queues:'.$queue;
                $size = Redis::connection()->llen($queueName);

                if ($size > 0) {
                    $this->line("  <fg=yellow>{$queue}:</fg=reset> {$size} jobs");
                }
            }

            $this->newLine();
            $this->info('To view specific jobs, use Redis CLI:');
            $this->line('  <fg=yellow>redis-cli LRANGE queues:default 0 -1</fg=reset>');

        } catch (\Exception $e) {
            $this->error('Failed to retrieve pending jobs: '.$e->getMessage());

            return 1;
        }

        return Command::SUCCESS;
    }

    /**
     * Show busted jobs (jobs that have exceeded max tries)
     */
    protected function showBustedJobs(): int
    {
        $this->info('Busted Jobs (Exceeded Max Tries)');
        $this->newLine();

        try {
            $bustedJobs = DB::table('failed_jobs')
                ->where('exception', 'like', '%maximum number of tries%')
                ->orderBy('failed_at', 'desc')
                ->limit(20)
                ->get();

            if ($bustedJobs->isEmpty()) {
                $this->info('No busted jobs found');

                return Command::SUCCESS;
            }

            $this->table(
                ['ID', 'Queue', 'Payload', 'Failed At'],
                $bustedJobs->map(function ($job) {
                    return [
                        substr($job->id, 0, 8).'...',
                        $job->queue,
                        substr($job->payload, 0, 100).'...',
                        $job->failed_at,
                    ];
                })->toArray()
            );

            $this->newLine();
            $this->warn('Found '.$bustedJobs->count().' busted jobs');
            $this->line('  Consider reviewing and fixing the issues');
            $this->line('  <fg=yellow>php artisan queue:retry {id}</fg=reset> to retry after fixing');

        } catch (\Exception $e) {
            $this->error('Failed to retrieve busted jobs: '.$e->getMessage());

            return 1;
        }

        return Command::SUCCESS;
    }

    /**
     * Get queue statuses
     */
    protected function getQueueStatuses(array $queues): array
    {
        $statuses = [];

        foreach ($queues as $queue) {
            $queueName = 'queues:'.$queue;
            try {
                $size = Redis::connection()->llen($queueName);
                $status = $size > 0 ? 'Pending' : 'Empty';

                $statuses[] = [
                    $queue,
                    $size,
                    $size > 10 ? '<fg=red>High</>' : ($size > 0 ? '<fg=yellow>Medium</>' : '<fg=green>OK</>'),
                ];
            } catch (\Exception $e) {
                $statuses[] = [
                    $queue,
                    'N/A',
                    '<fg=red>Error</>',
                ];
            }
        }

        return $statuses;
    }
}
