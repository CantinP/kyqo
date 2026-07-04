<?php

namespace Kyqo\Console\Scheduling;

/**
 * Represents a single scheduled task.
 */
class Event
{
    private string $expression = '* * * * *';
    private string $timezone   = 'UTC';
    private bool   $inBackground = false;
    private ?string $outputFile  = null;
    private ?\Closure $thenCallback = null;
    public  string $description = '';

    public function __construct(
        private string          $type,
        private string|\Closure $payload,
        string                  $description = ''
    ) {
        $this->description = $description ?: (is_string($payload) ? $payload : '[Closure]');
    }

    // ── Frequency helpers ───────────────────────────────────────────────

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static               { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static           { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static            { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static        { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static         { return $this->cron('*/30 * * * *'); }
    public function hourly(): static                     { return $this->cron('0 * * * *'); }
    public function hourlyAt(int $offset): static        { return $this->cron("{$offset} * * * *"); }
    public function daily(): static                      { return $this->cron('0 0 * * *'); }
    public function dailyAt(string $time): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '00'];
        return $this->cron("{$minute} {$hour} * * *");
    }
    public function twiceDaily(int $h1 = 1, int $h2 = 13): static { return $this->cron("0 {$h1},{$h2} * * *"); }
    public function weekly(): static                     { return $this->cron('0 0 * * 0'); }
    public function weeklyOn(int $day, string $time = '0:0'): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '0'];
        return $this->cron("{$minute} {$hour} * * {$day}");
    }
    public function monthly(): static                    { return $this->cron('0 0 1 * *'); }
    public function monthlyOn(int $day = 1, string $time = '0:0'): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '0'];
        return $this->cron("{$minute} {$hour} {$day} * *");
    }
    public function yearly(): static                     { return $this->cron('0 0 1 1 *'); }
    public function weekdays(): static                   { return $this->cron('0 0 * * 1-5'); }
    public function weekends(): static                   { return $this->cron('0 0 * * 6,0'); }
    public function mondays(): static                    { return $this->cron('0 0 * * 1'); }
    public function tuesdays(): static                   { return $this->cron('0 0 * * 2'); }
    public function wednesdays(): static                 { return $this->cron('0 0 * * 3'); }
    public function thursdays(): static                  { return $this->cron('0 0 * * 4'); }
    public function fridays(): static                    { return $this->cron('0 0 * * 5'); }
    public function saturdays(): static                  { return $this->cron('0 0 * * 6'); }
    public function sundays(): static                    { return $this->cron('0 0 * * 0'); }

    // ── Options ────────────────────────────────────────────────────────────

    public function timezone(string $tz): static         { $this->timezone = $tz; return $this; }
    public function runInBackground(): static             { $this->inBackground = true; return $this; }
    public function appendOutputTo(string $file): static  { $this->outputFile = $file; return $this; }
    public function then(\Closure $cb): static            { $this->thenCallback = $cb; return $this; }

    // ── Execution ───────────────────────────────────────────────────────────

    public function isDue(): bool
    {
        return CronExpression::isDue($this->expression, $this->timezone);
    }

    public function run(): void
    {
        if ($this->type === 'call') {
            ($this->payload)();
        } else {
            $cmd = PHP_BINARY . ' ' . base_path('kyqo') . ' ' . $this->payload;
            if ($this->outputFile) $cmd .= ' >> ' . escapeshellarg($this->outputFile) . ' 2>&1';
            if ($this->inBackground) $cmd .= ' &';
            passthru($cmd);
        }

        if ($this->thenCallback !== null) {
            ($this->thenCallback)();
        }
    }

    public function getExpression(): string { return $this->expression; }
}
