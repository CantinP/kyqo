<?php

namespace Kyqo\Console;

use Kyqo\Core\Application;
use Kyqo\Console\Commands\CacheClearCommand;
use Kyqo\Console\Commands\DbSeedCommand;
use Kyqo\Console\Commands\KeyGenerateCommand;
use Kyqo\Console\Commands\MakeControllerCommand;
use Kyqo\Console\Commands\MakeMigrationCommand;
use Kyqo\Console\Commands\MakeModelCommand;
use Kyqo\Console\Commands\MakeJobCommand;
use Kyqo\Console\Commands\MakeMiddlewareCommand;
use Kyqo\Console\Commands\MakeSeederCommand;
use Kyqo\Console\Commands\MigrateCommand;
use Kyqo\Console\Commands\MigrateRollbackCommand;
use Kyqo\Console\Commands\QueueFailedCommand;
use Kyqo\Console\Commands\QueueWorkCommand;
use Kyqo\Console\Commands\ServeCommand;
use Symfony\Component\Console\Application as ConsoleApp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel
{
    protected ConsoleApp $console;

    protected array $commands = [
        // Migrations
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MakeMigrationCommand::class,
        // Database
        DbSeedCommand::class,
        // Generators
        MakeModelCommand::class,
        MakeControllerCommand::class,
        MakeJobCommand::class,
        MakeMiddlewareCommand::class,
        MakeSeederCommand::class,
        // Runtime
        KeyGenerateCommand::class,
        CacheClearCommand::class,
        ServeCommand::class,
        // Queue
        QueueWorkCommand::class,
        QueueFailedCommand::class,
    ];

    public function __construct(protected Application $app)
    {
        $this->console = new ConsoleApp('Kyqo', Application::VERSION);
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
