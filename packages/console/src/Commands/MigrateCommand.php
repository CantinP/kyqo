<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Kyqo\Database\Schema\Migrator;

/**
 * php kyqo migrate
 *
 * Runs all pending database migrations.
 */
class MigrateCommand extends Command
{
    protected string $signature   = 'migrate';
    protected string $description = 'Run the database migrations';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addOption('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force migration in production');
    }

    protected function handle(): int
    {
        $env = $this->app->make('config.env', 'production');
        if ($env === 'production' && !$this->option('force')) {
            if (!$this->confirm('You are in production. Run migrations? (--force to skip)', false)) {
                $this->warn('Migration aborted.');
                return self::SUCCESS;
            }
        }

        $migrator = $this->app->make(Migrator::class);
        $ran      = $migrator->run();

        if (empty($ran)) {
            $this->info('Nothing to migrate.');
        } else {
            foreach ($ran as $migration) {
                $this->info('Migrated: ' . $migration);
            }
        }

        return self::SUCCESS;
    }
}
