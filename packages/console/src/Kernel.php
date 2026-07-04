<?php

namespace Kyqo\Console;

use Kyqo\Core\Application;
use Kyqo\Console\Commands\MigrateCommand;
use Kyqo\Console\Commands\KeyGenerateCommand;
use Kyqo\Console\Commands\QueueWorkCommand;
use Kyqo\Console\Commands\MigrateRollbackCommand;
use Kyqo\Console\Commands\MakeModelCommand;
use Kyqo\Console\Commands\MakeMigrationCommand;
use Symfony\Component\Console\Application as ConsoleApp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console Kernel
 *
 * Boots Symfony Console, registers all built-in Kyqo commands,
 * and hands control to the CLI input/output.
 */
class Kernel
{
    protected ConsoleApp $console;

    /**
     * Built-in framework commands.
     */
    protected array $commands = [
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        KeyGenerateCommand::class,
        QueueWorkCommand::class,
        MakeModelCommand::class,
        MakeMigrationCommand::class,
    ];

    public function __construct(protected Application $app)
    {
        $this->console = new ConsoleApp('Kyqo', '1.0.0');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->registerCommands();
        return $this->console->run($input, $output);
    }

    public function terminate(InputInterface $input, int $status): void {}

    protected function registerCommands(): void
    {
        foreach ($this->commands as $class) {
            $this->console->add(new $class($this->app));
        }
    }
}
