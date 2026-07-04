<?php

namespace Kyqo\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base Command
 *
 * Extends Symfony Console Command with Kyqo-specific helpers.
 * Concrete commands override handle() instead of execute().
 */
abstract class Command extends SymfonyCommand
{
    protected SymfonyStyle $io;
    protected InputInterface $input;
    protected OutputInterface $output;

    /** The console command name. */
    protected string $signature = '';

    /** The console command description. */
    protected string $description = '';

    protected function configure(): void
    {
        if ($this->signature !== '') {
            $this->setName($this->parseName($this->signature));
        }
        if ($this->description !== '') {
            $this->setDescription($this->description);
        }
        $this->defineArguments();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;
        $this->io     = new SymfonyStyle($input, $output);

        return (int) $this->handle();
    }

    abstract protected function handle(): int;

    protected function info(string $message): void    { $this->io->writeln('<info>'    . $message . '</info>'); }
    protected function error(string $message): void   { $this->io->writeln('<error>'   . $message . '</error>'); }
    protected function warn(string $message): void    { $this->io->writeln('<comment>' . $message . '</comment>'); }
    protected function comment(string $message): void { $this->io->writeln('<comment>' . $message . '</comment>'); }
    protected function line(string $message): void    { $this->io->writeln($message); }

    protected function ask(string $question, mixed $default = null): mixed
    {
        return $this->io->ask($question, $default);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->io->confirm($question, $default);
    }

    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function parseName(string $signature): string
    {
        return trim(explode(' ', $signature)[0]);
    }

    protected function defineArguments(): void
    {
        // Subclasses override to add arguments/options.
    }
}
