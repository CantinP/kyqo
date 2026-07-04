<?php

namespace Kyqo\Console\Commands;

use Kyqo\Console\Command;
use Kyqo\Core\Application;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php kyqo make:lang fr
 * Creates resources/lang/fr/ and copies stubs from en/ if it exists.
 */
class MakeLangCommand extends Command
{
    protected string $signature   = 'make:lang';
    protected string $description = 'Create a new language folder with stub files';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function defineArguments(): void
    {
        $this->addArgument('locale', InputArgument::REQUIRED, 'Locale code (e.g. fr, de, es)');
    }

    protected function handle(): int
    {
        $locale  = $this->argument('locale');
        $langDir = $this->app->resourcePath('lang/' . $locale);

        if (!is_dir($langDir)) mkdir($langDir, 0755, true);

        // Create stub translation files
        $stubs = [
            'auth'       => $this->authStub($locale),
            'validation' => $this->validationStub($locale),
            'pagination' => $this->paginationStub($locale),
        ];

        foreach ($stubs as $file => $content) {
            $path = $langDir . '/' . $file . '.php';
            if (!file_exists($path)) {
                file_put_contents($path, $content);
                $this->info("Created: resources/lang/{$locale}/{$file}.php");
            }
        }

        $this->info("Language [{$locale}] created.");
        return self::SUCCESS;
    }

    private function authStub(string $locale): string
    {
        return <<<PHP
<?php

// resources/lang/{$locale}/auth.php
return [
    'failed'   => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'password' => 'The provided password is incorrect.',
];
PHP;
    }

    private function validationStub(string $locale): string
    {
        return <<<PHP
<?php

// resources/lang/{$locale}/validation.php
return [
    'required' => 'The :attribute field is required.',
    'email'    => 'The :attribute must be a valid email address.',
    'min'      => [
        'string'  => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
    ],
    'max'      => [
        'string'  => 'The :attribute must not exceed :max characters.',
    ],
    'unique'   => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',
];
PHP;
    }

    private function paginationStub(string $locale): string
    {
        return <<<PHP
<?php

// resources/lang/{$locale}/pagination.php
return [
    'previous' => '&laquo; Previous',
    'next'     => 'Next &raquo;',
];
PHP;
    }
}
