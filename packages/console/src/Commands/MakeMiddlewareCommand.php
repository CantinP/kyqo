<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * make:middleware — Generate a new Middleware class.
 *
 * Usage:
 *   php kyqo make:middleware EnsureUserIsAdmin
 */
class MakeMiddlewareCommand extends Command
{
    protected static string $defaultName = 'make:middleware';

    protected function configure(): void
    {
        $this->setName('make:middleware')
             ->setDescription('Create a new HTTP middleware class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim($input->getArgument('name'), '\\/');
        $dir  = $this->app->appPath('Http/Middleware');
        $file = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $output->writeln("<error>Middleware [{$name}] already exists.</error>");
            return 1;
        }

        file_put_contents($file, $this->stub($name));
        $output->writeln("<info>Middleware [{$name}] created at app/Http/Middleware/{$name}.php</info>");
        return 0;
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

namespace App\\Http\\Middleware;

use Kyqo\\Http\\Request;

class {$class}
{
    public function handle(Request \$request, \Closure \$next): mixed
    {
        // Your middleware logic before the request is handled

        \$response = \$next(\$request);

        // Your middleware logic after the response is generated

        return \$response;
    }
}
PHP;
    }
}
