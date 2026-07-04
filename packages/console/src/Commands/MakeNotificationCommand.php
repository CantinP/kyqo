<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php kyqo make:notification InvoicePaid
 */
class MakeNotificationCommand extends Command
{
    protected string $signature   = 'make:notification';
    protected string $description = 'Create a new notification class';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Notification class name');
    }

    protected function handle(): int
    {
        $name = $this->argument('name');
        $path = $this->app->basePath('app/Notifications/' . $name . '.php');
        $dir  = dirname($path);

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_exists($path)) {
            $this->error("Notification [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, $this->stub($name));
        $this->info("Notification [{$name}] created at app/Notifications/{$name}.php");
        return self::SUCCESS;
    }

    protected function stub(string $name): string
    {
        return <<<PHP
<?php

namespace App\Notifications;

use Kyqo\Notifications\Notification;
use Kyqo\Notifications\Messages\MailMessage;

class {$name} extends Notification
{
    public function __construct()
    {
        parent::__construct();
    }

    public function via(object \$notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object \$notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('{$name}')
            ->greeting('Hello!')
            ->line('This is a notification.');
    }

    // public function toDatabase(object \$notifiable): array
    // {
    //     return [];
    // }

    // public function toSlack(object \$notifiable): \Kyqo\Notifications\Messages\SlackMessage
    // {
    //     return (new \Kyqo\Notifications\Messages\SlackMessage)->content('...');
    // }
}
PHP;
    }
}
