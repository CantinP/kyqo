<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FIX C4 – new command: php kyqo cache:clear
 */
class CacheClearCommand extends Command
{
    protected static string $defaultName = 'cache:clear';

    protected function configure(): void
    {
        $this->setName('cache:clear')
             ->setDescription('Flush the application cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheManager = $this->app->make('cache');
        $cacheManager->flush();
        $output->writeln('<info>Application cache cleared.</info>');
        return 0;
    }
}
