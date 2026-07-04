<?php

namespace Kyqo\Database\Factories;

/**
 * FakeData — lightweight fake-data generator (no Faker required).
 *
 * Provides deterministic-enough random data for seeding / tests.
 * Requires no external dependencies.
 */
class FakeData
{
    private static array $firstNames = [
        'Alice','Bob','Charlie','Diana','Ethan','Fiona','George','Hannah',
        'Ivan','Julia','Kevin','Laura','Michael','Nina','Oscar','Paula',
        'Quinn','Rachel','Steve','Tina','Uma','Victor','Wendy','Xander','Yara','Zoe',
    ];

    private static array $lastNames = [
        'Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis',
        'Wilson','Taylor','Anderson','Thomas','Jackson','White','Harris','Martin',
        'Thompson','Moore','Young','Allen','King','Wright','Scott','Green','Baker',
    ];

    private static array $domains = [
        'example.com','test.org','demo.net','sample.io','faker.dev',
        'kyqo.dev','mail.com','inbox.net','webmail.org',
    ];

    private static array $words = [
        'lorem','ipsum','dolor','sit','amet','consectetur','adipiscing','elit',
        'sed','do','eiusmod','tempor','incididunt','ut','labore','et','dolore',
        'magna','aliqua','enim','ad','minim','veniam','quis','nostrud',
    ];

    private static array $tlds = ['com','net','org','io','dev','app','co'];

    // ── Generators ──────────────────────────────────────────────────────────

    public function name(): string
    {
        return $this->randomElement(self::$firstNames) . ' ' . $this->randomElement(self::$lastNames);
    }

    public function firstName(): string
    {
        return $this->randomElement(self::$firstNames);
    }

    public function lastName(): string
    {
        return $this->randomElement(self::$lastNames);
    }

    public function email(): string
    {
        $local = strtolower($this->randomElement(self::$firstNames))
               . '.' . strtolower($this->randomElement(self::$lastNames))
               . $this->numberBetween(1, 999);
        return $local . '@' . $this->randomElement(self::$domains);
    }

    public function username(): string
    {
        return strtolower($this->randomElement(self::$firstNames))
             . $this->numberBetween(10, 9999);
    }

    public function password(string $raw = 'password'): string
    {
        return password_hash($raw, PASSWORD_BCRYPT);
    }

    public function url(): string
    {
        $slug = strtolower($this->randomElement(self::$words)) . '-'
              . strtolower($this->randomElement(self::$words));
        $tld  = $this->randomElement(self::$tlds);
        return 'https://www.' . $slug . '.' . $tld;
    }

    public function sentence(int $words = 8): string
    {
        $picked = [];
        for ($i = 0; $i < $words; $i++) {
            $picked[] = $this->randomElement(self::$words);
        }
        return ucfirst(implode(' ', $picked)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $parts = [];
        for ($i = 0; $i < $sentences; $i++) {
            $parts[] = $this->sentence($this->numberBetween(6, 12));
        }
        return implode(' ', $parts);
    }

    public function word(): string
    {
        return $this->randomElement(self::$words);
    }

    public function slug(int $words = 3): string
    {
        $parts = [];
        for ($i = 0; $i < $words; $i++) {
            $parts[] = $this->randomElement(self::$words);
        }
        return implode('-', $parts);
    }

    public function title(): string
    {
        return ucwords($this->slug($this->numberBetween(3, 7)));
    }

    public function numberBetween(int $min = 0, int $max = 100): int
    {
        return random_int($min, $max);
    }

    public function floatBetween(float $min = 0.0, float $max = 100.0, int $decimals = 2): float
    {
        $val = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return round($val, $decimals);
    }

    public function boolean(int $chanceOfTrue = 50): bool
    {
        return random_int(1, 100) <= $chanceOfTrue;
    }

    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }

    public function date(string $format = 'Y-m-d', string $min = '-5 years', string $max = 'now'): string
    {
        $minTs = strtotime($min);
        $maxTs = strtotime($max);
        return date($format, random_int($minTs, $maxTs));
    }

    public function dateTime(string $format = 'Y-m-d H:i:s', string $min = '-1 year', string $max = 'now'): string
    {
        return $this->date($format, $min, $max);
    }

    public function ipv4(): string
    {
        return implode('.', [
            random_int(1, 254), random_int(0, 255),
            random_int(0, 255), random_int(1, 254),
        ]);
    }

    public function phoneNumber(): string
    {
        return sprintf('+%d %d %d %d',
            random_int(1, 99),
            random_int(100, 999),
            random_int(100, 999),
            random_int(1000, 9999)
        );
    }

    public function randomElement(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    public function randomElements(array $array, int $count = 2): array
    {
        $keys = array_rand($array, min($count, count($array)));
        return array_map(fn ($k) => $array[$k], (array) $keys);
    }

    public function unique(): UniqueGenerator
    {
        return new UniqueGenerator($this);
    }
}
