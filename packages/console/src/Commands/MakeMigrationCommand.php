<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php kyqo make:migration create_posts_table
 */
class MakeMigrationCommand extends Command
{
    protected string $signature   = 'make:migration';
    protected string $description = 'Create a new database migration file';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the migration (e.g. create_posts_table)');
        $this->addOption('table',  null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The table to migrate');
        $this->addOption('create', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The table to be created');
    }

    protected function handle(): int
    {
        $name       = $this->argument('name');
        $table      = $this->option('create') ?? $this->option('table') ?? $this->guessTable($name);
        $create     = $this->option('create') !== null || str_starts_with($name, 'create_');
        $timestamp  = date('Y_m_d_His');
        $filename   = $timestamp . '_' . $name . '.php';
        $dir        = $this->app->basePath('database/migrations');

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $path = $dir . '/' . $filename;
        file_put_contents($path, $this->stub($table, $create));
        $this->info("Migration created: database/migrations/{$filename}");

        return self::SUCCESS;
    }

    protected function guessTable(string $name): string
    {
        // create_posts_table => posts
        if (preg_match('/create_(.+)_table/', $name, $m)) return $m[1];
        if (preg_match('/(?:add|remove)_.+_(?:to|from)_(.+)/', $name, $m)) return $m[1];
        return $name;
    }

    protected function stub(string $table, bool $create): string
    {
        if ($create) {
            return <<<PHP
<?php

use Kyqo\Database\Schema\Migration;
use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\SchemaBuilder as Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
        }

        return <<<PHP
<?php

use Kyqo\Database\Schema\Migration;
use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\SchemaBuilder as Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            //
        });
    }
};
PHP;
    }
}
