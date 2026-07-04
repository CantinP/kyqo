<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;

/**
 * php kyqo key:generate
 *
 * Generates a new 32-byte base64-encoded APP_KEY and writes it to .env.
 */
class KeyGenerateCommand extends Command
{
    protected string $signature    = 'key:generate';
    protected string $description  = 'Set the application key in your .env file';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $key  = 'base64:' . base64_encode(random_bytes(32));
        $root = $this->app->basePath();
        $env  = $root . '/.env';

        if (!file_exists($env)) {
            $example = $root . '/.env.example';
            if (file_exists($example)) {
                copy($example, $env);
                $this->info('.env created from .env.example');
            } else {
                file_put_contents($env, '');
            }
        }

        $contents = file_get_contents($env);

        if (str_contains($contents, 'APP_KEY=')) {
            $contents = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $contents);
        } else {
            $contents .= PHP_EOL . 'APP_KEY=' . $key . PHP_EOL;
        }

        file_put_contents($env, $contents);
        $this->info('Application key set: ' . $key);
        return self::SUCCESS;
    }
}
