<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * php kyqo make:factory UserFactory --model=User
 */
class MakeFactoryCommand extends Command
{
    protected string $signature   = 'make:factory';
    protected string $description = 'Create a new model factory';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Factory class name (e.g. UserFactory)');
        $this->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model class the factory creates', '');
    }

    protected function handle(): int
    {
        $name  = $this->argument('name');
        $model = $this->option('model') ?: str_replace('Factory', '', $name);
        $path  = $this->app->basePath('database/factories/' . $name . '.php');
        $dir   = dirname($path);

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_exists($path)) {
            $this->error("Factory [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, $this->stub($name, $model));
        $this->info("Factory [{$name}] created at database/factories/{$name}.php");
        return self::SUCCESS;
    }

    protected function stub(string $name, string $model): string
    {
        return <<<PHP
<?php

use App\Models\{$model};
use Kyqo\Database\Factories\ModelFactory;

class {$name} extends ModelFactory
{
    protected string \$model = {$model}::class;

    public function definition(): array
    {
        return [
            'name'  => \$this->fake()->name(),
            'email' => \$this->fake()->unique()->email(),
        ];
    }

    public function admin(): static
    {
        return \$this->state(['role' => 'admin']);
    }
}
PHP;
    }
}
