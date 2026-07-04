<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Kyqo\Queue\QueueManager;

/**
 * php kyqo queue:work
 *
 * Processes jobs from a queue connection.
 * Runs until stopped or --once is passed.
 */
class QueueWorkCommand extends Command
{
    protected string $signature   = 'queue:work';
    protected string $description = 'Process jobs on the queue';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addOption('connection', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Queue connection', null);
        $this->addOption('queue',      null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Queue name',       'default');
        $this->addOption('once',       null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,     'Process one job and exit');
        $this->addOption('sleep',      null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Seconds to sleep when no jobs', 3);
        $this->addOption('tries',      null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Max job attempts', 3);
    }

    protected function handle(): int
    {
        $manager    = $this->app->make(QueueManager::class);
        $connection = $this->option('connection');
        $queueName  = $this->option('queue') ?? 'default';
        $once       = (bool) $this->option('once');
        $sleep      = (int) ($this->option('sleep') ?? 3);
        $maxTries   = (int) ($this->option('tries') ?? 3);
        $queue      = $manager->connection($connection);

        $this->info('Kyqo Queue Worker started. Press Ctrl+C to stop.');

        do {
            $job = $queue->pop($queueName);
            if ($job !== null) {
                try {
                    $this->info('Processing job: ' . get_class($job));
                    $job->handle();
                    $this->info('Job processed successfully.');
                } catch (\Throwable $e) {
                    $this->error('Job failed: ' . $e->getMessage());
                    if (method_exists($job, 'failed')) {
                        $job->failed($e);
                    }
                }
            } else {
                if ($once) break;
                sleep($sleep);
            }
        } while (!$once);

        return self::SUCCESS;
    }
}
