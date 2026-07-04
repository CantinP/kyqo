<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FIX C4 – new command: php kyqo make:controller FooController [--resource]
 */
class MakeControllerCommand extends Command
{
    protected static string $defaultName = 'make:controller';

    protected function configure(): void
    {
        $this->setName('make:controller')
             ->setDescription('Create a new HTTP controller class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller (e.g. UserController)')
             ->addOption('resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller with CRUD stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name     = trim($input->getArgument('name'), '\\/');
        $resource = $input->getOption('resource');

        // Support nested: Admin/UserController
        $parts    = explode('/', str_replace('\\', '/', $name));
        $class    = array_pop($parts);
        $subNs    = empty($parts) ? '' : '\\' . implode('\\', $parts);
        $subDir   = empty($parts) ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);

        $namespace = 'App\\Http\\Controllers' . $subNs;
        $dir       = $this->app->appPath('Http/Controllers' . $subDir);
        $file      = $dir . DIRECTORY_SEPARATOR . $class . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $output->writeln("<error>Controller [{$class}] already exists.</error>");
            return 1;
        }

        $stub = $resource
            ? $this->resourceStub($namespace, $class)
            : $this->plainStub($namespace, $class);

        file_put_contents($file, $stub);
        $output->writeln("<info>Controller [{$class}] created at app/Http/Controllers{$subDir}/{$class}.php</info>");
        return 0;
    }

    private function plainStub(string $ns, string $class): string
    {
        return <<<PHP
<?php

namespace {$ns};

use Kyqo\\Http\\Request;
use Kyqo\\Http\\Response;

class {$class}
{
    public function index(Request \$request): Response
    {
        return response('Hello from {$class}');
    }
}
PHP;
    }

    private function resourceStub(string $ns, string $class): string
    {
        return <<<PHP
<?php

namespace {$ns};

use Kyqo\\Http\\Request;
use Kyqo\\Http\\Response;

class {$class}
{
    public function index(Request \$request): Response  { return response('index'); }
    public function create(Request \$request): Response { return view('form'); }
    public function store(Request \$request): Response  { return redirect('/'); }
    public function show(Request \$request): Response   { return response('show'); }
    public function edit(Request \$request): Response   { return view('form'); }
    public function update(Request \$request): Response { return redirect('/'); }
    public function destroy(Request \$request): Response{ return redirect('/'); }
}
PHP;
    }
}
