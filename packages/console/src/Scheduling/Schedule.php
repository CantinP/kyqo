<?php

namespace Kyqo\Console\Scheduling;

/**
 * Task Scheduler — define recurring console commands.
 *
 * Usage in app/Console/Kernel.php:
 *
 *   protected function schedule(Schedule $schedule): void
 *   {
 *       $schedule->command('queue:work --once')->everyMinute();
 *       $schedule->command('reports:daily')->dailyAt('06:00');
 *       $schedule->call(fn () => Cache::flush())->hourly();
 *   }
 *
 * Run via cron (add ONE entry to crontab):
 *   * * * * * php /path/to/project/kyqo schedule:run >> /dev/null 2>&1
 */
class Schedule
{
    /** @var Event[] */
    private array $events = [];

    public function command(string $command): Event
    {
        $event = new Event('command', $command);
        $this->events[] = $event;
        return $event;
    }

    public function call(\Closure $callback, string $description = ''): Event
    {
        $event = new Event('call', $callback, $description);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Return all events that are due right now.
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, fn (Event $e) => $e->isDue());
    }

    public function events(): array { return $this->events; }
}
