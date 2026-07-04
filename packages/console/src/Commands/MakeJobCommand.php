<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * make:job — Generate a new Job class.
 *
 * Usage:
 *   php kyqo make:job SendWelcomeEmail
 */
class MakeJobCommand extends Command
{
    protected static string $defaultName = 'make:job';

    protected function configure(): void
    {
        $this->setName('make:job')
             ->setDescription('Create a new job class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the job class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim($input->getArgument('name'), '\\/');
        $dir  = $this->app->appPath('Jobs');
        $file = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $output->writeln("<error>Job [{$name}] already exists.</error>");
            return 1;
        }

        file_put_contents($file, $this->stub($name));
        $output->writeln("<info>Job [{$name}] created at app/Jobs/{$name}.php</info>");
        return 0;
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

namespace App\\Jobs;

use Kyqo\\Queue\\Job;

class {$class} extends Job
{
    public function __construct(
        // Inject any data the job needs
    ) {}

    public function handle(): void
    {
        // Implement job logic here
    }
}
PHP;
    }
}
