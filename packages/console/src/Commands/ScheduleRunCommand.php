<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Kyqo\Console\Scheduling\Schedule;

/**
 * php kyqo schedule:run
 *
 * Runs all tasks in the schedule that are currently due.
 * Add to crontab: * * * * * php /path/project/kyqo schedule:run
 */
class ScheduleRunCommand extends Command
{
    protected string $signature   = 'schedule:run';
    protected string $description = 'Run all scheduled tasks that are due';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void {}

    protected function handle(): int
    {
        $schedule = new Schedule();

        // Delegate to app/Console/Kernel if it defines schedule()
        try {
            $kernel = $this->app->make(\Kyqo\Console\Kernel::class);
            if (method_exists($kernel, 'schedule')) {
                $kernel->schedule($schedule);
            }
        } catch (\Throwable $e) {
            $this->error('Could not load schedule: ' . $e->getMessage());
        }

        $due = $schedule->dueEvents();

        if (empty($due)) {
            $this->info('No scheduled tasks are due.');
            return self::SUCCESS;
        }

        foreach ($due as $event) {
            $this->info('Running: ' . $event->description);
            try {
                $event->run();
            } catch (\Throwable $e) {
                $this->error('Task failed: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
