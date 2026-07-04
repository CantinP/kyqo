<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Kyqo\Database\Schema\Migrator;

/**
 * php kyqo migrate:rollback
 */
class MigrateRollbackCommand extends Command
{
    protected string $signature   = 'migrate:rollback';
    protected string $description = 'Rollback the last database migration batch';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addOption('step', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Number of batches to rollback', 1);
        $this->addOption('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force rollback in production');
    }

    protected function handle(): int
    {
        $env = $this->app->make('config.env', 'production');
        if ($env === 'production' && !$this->option('force')) {
            if (!$this->confirm('You are in production. Rollback migrations? (--force to skip)', false)) {
                $this->warn('Rollback aborted.');
                return self::SUCCESS;
            }
        }

        $migrator = $this->app->make(Migrator::class);
        $step     = (int) ($this->option('step') ?? 1);
        $rolled   = $migrator->rollback($step);

        if (empty($rolled)) {
            $this->info('Nothing to rollback.');
        } else {
            foreach ($rolled as $migration) {
                $this->info('Rolled back: ' . $migration);
            }
        }

        return self::SUCCESS;
    }
}
