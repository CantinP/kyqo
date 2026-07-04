<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FIX C4 – new command: php kyqo serve [--host] [--port]
 */
class ServeCommand extends Command
{
    protected static string $defaultName = 'serve';

    protected function configure(): void
    {
        $this->setName('serve')
             ->setDescription('Start the PHP development server')
             ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', '127.0.0.1')
             ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', '8000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host    = $input->getOption('host');
        $port    = $input->getOption('port');
        $public  = $this->app->publicPath();

        $output->writeln("<info>Starting development server on http://{$host}:{$port}</info>");
        $output->writeln('<comment>Press Ctrl+C to stop the server.</comment>');

        passthru(sprintf(
            '%s -S %s:%s -t %s',
            PHP_BINARY,
            escapeshellarg($host),
            (int) $port,
            escapeshellarg($public)
        ));

        return 0;
    }
}
