<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FIX C4 – new command: php kyqo queue:failed
 */
class QueueFailedCommand extends Command
{
    protected static string $defaultName = 'queue:failed';

    protected function configure(): void
    {
        $this->setName('queue:failed')
             ->setDescription('List all of the failed queue jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $db   = $this->app->make('db');
            $rows = $db->connection()->table('failed_jobs')->get();
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not query failed_jobs: ' . $e->getMessage() . '</error>');
            return 1;
        }

        if (empty($rows)) {
            $output->writeln('<info>No failed jobs found.</info>');
            return 0;
        }

        foreach ($rows as $row) {
            $output->writeln(sprintf(
                '[%s] %s — failed at %s',
                $row['id']      ?? '?',
                $row['queue']   ?? '?',
                $row['failed_at'] ?? '?'
            ));
        }
        return 0;
    }
}
