<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * db:seed — Run database seeders.
 *
 * Usage:
 *   php kyqo db:seed
 *   php kyqo db:seed --class=UsersSeeder
 */
class DbSeedCommand extends Command
{
    protected static string $defaultName = 'db:seed';

    protected function configure(): void
    {
        $this->setName('db:seed')
             ->setDescription('Seed the database with records')
             ->addOption(
                 'class',
                 null,
                 \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                 'The class name of the root seeder',
                 'Database\\Seeders\\DatabaseSeeder'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $input->getOption('class');

        if (!class_exists($class)) {
            // Try to autoload from database/seeders/
            $file = $this->app->basePath('database/seeders/' . class_basename($class) . '.php');
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (!class_exists($class)) {
            $output->writeln("<error>Seeder class [{$class}] not found.</error>");
            return 1;
        }

        $output->writeln("<info>Seeding: {$class}</info>");
        $seeder = new $class($this->app);
        $seeder->run();
        $output->writeln('<info>Database seeding completed successfully.</info>');
        return 0;
    }

    private function class_basename(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
