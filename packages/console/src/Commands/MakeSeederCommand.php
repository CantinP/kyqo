<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * make:seeder — Generate a new Seeder class.
 *
 * Usage:
 *   php kyqo make:seeder UsersSeeder
 */
class MakeSeederCommand extends Command
{
    protected static string $defaultName = 'make:seeder';

    protected function configure(): void
    {
        $this->setName('make:seeder')
             ->setDescription('Create a new database seeder class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim($input->getArgument('name'), '\\/');
        $dir  = $this->app->basePath('database/seeders');
        $file = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $output->writeln("<error>Seeder [{$name}] already exists.</error>");
            return 1;
        }

        file_put_contents($file, $this->stub($name));
        $output->writeln("<info>Seeder [{$name}] created at database/seeders/{$name}.php</info>");
        return 0;
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

namespace Database\\Seeders;

use Kyqo\\Database\\Seeders\\Seeder;

class {$class} extends Seeder
{
    public function run(): void
    {
        // Seed the database here
        // Example: \$this->app->make(\Kyqo\Database\DatabaseManager::class)
        //              ->table('users')->insert([...]);
    }
}
PHP;
    }
}
