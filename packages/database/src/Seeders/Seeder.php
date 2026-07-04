<?php

namespace Kyqo\Database\Seeders;

use Kyqo\Core\Application;

/**
 * Base Seeder
 *
 * All seeders should extend this class and implement run().
 *
 * Usage:
 *   class DatabaseSeeder extends Seeder
 *   {
 *       public function run(): void
 *       {
 *           $this->call(UsersSeeder::class);
 *       }
 *   }
 */
abstract class Seeder
{
    protected Application $app;

    public function __construct(?Application $app = null)
    {
        $this->app = $app ?? Application::getInstance();
    }

    /**
     * Run the seeder.
     */
    abstract public function run(): void;

    /**
     * Call another seeder class by FQCN.
     *
     * @param class-string<Seeder>|array<class-string<Seeder>> $seeders
     */
    public function call(string|array $seeders): void
    {
        foreach ((array) $seeders as $class) {
            /** @var Seeder $seeder */
            $seeder = new $class($this->app);
            $seeder->run();
        }
    }

    /**
     * Output a line to STDOUT (visible when running db:seed).
     */
    protected function line(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
