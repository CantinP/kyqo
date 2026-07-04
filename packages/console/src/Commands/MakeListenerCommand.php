<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * php kyqo make:listener SendWelcomeEmail --event=UserRegistered
 */
class MakeListenerCommand extends Command
{
    protected string $signature   = 'make:listener';
    protected string $description = 'Create a new event listener class';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the listener');
        $this->addOption('event', 'e', InputOption::VALUE_OPTIONAL, 'The event class the listener handles', 'Event');
    }

    protected function handle(): int
    {
        $name  = $this->argument('name');
        $event = $this->option('event');
        $path  = $this->app->basePath('app/Listeners/' . $name . '.php');
        $dir   = dirname($path);

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_exists($path)) {
            $this->error("Listener [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, $this->stub($name, $event));
        $this->info("Listener [{$name}] created at app/Listeners/{$name}.php");
        return self::SUCCESS;
    }

    protected function stub(string $name, string $event): string
    {
        $eventNs   = 'App\\Events\\' . $event;
        $eventShort = $event;
        return <<<PHP
<?php

namespace App\Listeners;

use Kyqo\Core\Events\Listener;
use Kyqo\Core\Events\Event;
use {$eventNs};

class {$name} extends Listener
{
    public function handle(Event \$event): void
    {
        /** @var {$eventShort} \$event */
        //
    }
}
PHP;
    }
}
