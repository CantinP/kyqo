<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php kyqo make:model User
 * php kyqo make:model Post -m  (also generates migration)
 */
class MakeModelCommand extends Command
{
    protected string $signature   = 'make:model';
    protected string $description = 'Create a new Eloquent-style model';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the model');
        $this->addOption('migration', 'm', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Also create a migration');
    }

    protected function handle(): int
    {
        $name  = $this->argument('name');
        $path  = $this->app->basePath('app/Models/' . $name . '.php');
        $dir   = dirname($path);

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (file_exists($path)) {
            $this->error("Model [{$name}] already exists.");
            return self::FAILURE;
        }

        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
        $stub  = $this->stub($name, $table);
        file_put_contents($path, $stub);
        $this->info("Model [{$name}] created at app/Models/{$name}.php");

        if ($this->option('migration')) {
            $this->app->make(Kernel::class);
            $migrationName = 'create_' . $table . '_table';
            $this->info("Run: php kyqo make:migration {$migrationName} to generate the migration.");
        }

        return self::SUCCESS;
    }

    protected function stub(string $name, string $table): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Kyqo\Database\Orm\Model;

class {$name} extends Model
{
    protected string \$table = '{$table}';

    protected array \$fillable = [
        //
    ];

    protected array \$hidden = [
        //
    ];
}
PHP;
    }
}
