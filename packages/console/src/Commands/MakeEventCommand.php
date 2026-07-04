<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php kyqo make:event UserRegistered
 */
class MakeEventCommand extends Command
{
    protected string $signature   = 'make:event';
    protected string $description = 'Create a new event class';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the event');
    }

    protected function handle(): int
    {
        $name = $this->argument('name');
        $path = $this->app->basePath('app/Events/' . $name . '.php');
        $dir  = dirname($path);

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_exists($path)) {
            $this->error("Event [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, $this->stub($name));
        $this->info("Event [{$name}] created at app/Events/{$name}.php");
        return self::SUCCESS;
    }

    protected function stub(string $name): string
    {
        return <<<PHP
<?php

namespace App\Events;

use Kyqo\Core\Events\Event;

class {$name} extends Event
{
    public function __construct(
        // public readonly User \$user,
    ) {}
}
PHP;
    }
}
