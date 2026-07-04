<?php

namespace Kyqo\Console\Scheduling;

/**
 * Minimal cron expression parser.
 *
 * Supports: * , - / for each of the 5 fields (minute hour dom month dow).
 */
class CronExpression
{
    public static function isDue(string $expression, string $timezone = 'UTC'): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) return false;

        $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));

        [$minute, $hour, $dom, $month, $dow] = $parts;

        return self::matchField($minute, (int) $now->format('i'), 0, 59)
            && self::matchField($hour,   (int) $now->format('G'), 0, 23)
            && self::matchField($dom,    (int) $now->format('j'), 1, 31)
            && self::matchField($month,  (int) $now->format('n'), 1, 12)
            && self::matchField($dow,    (int) $now->format('w'), 0, 6);
    }

    private static function matchField(string $field, int $current, int $min, int $max): bool
    {
        if ($field === '*') return true;

        // */n  → every n units
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);
            return $step > 0 && ($current - $min) % $step === 0;
        }

        // a-b/n  or  a-b
        if (str_contains($field, '-')) {
            [$range, $step] = array_pad(explode('/', $field, 2), 2, '1');
            [$from, $to]    = explode('-', $range);
            if ($current < (int)$from || $current > (int)$to) return false;
            return (int)$step <= 1 || ($current - (int)$from) % (int)$step === 0;
        }

        // a,b,c  → list
        if (str_contains($field, ',')) {
            return in_array((string) $current, explode(',', $field), true);
        }

        return (int) $field === $current;
    }
}
