<?php

namespace Kyqo\Core\Logging;

/**
 * PSR-3-compatible file logger.
 *
 * Writes structured log lines to a file.
 * Format: [YYYY-MM-DD HH:MM:SS] channel.LEVEL: message {context_json}
 */
class Logger
{
    public const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    protected int $minLevel;

    public function __construct(
        protected string $path,
        protected string $level = 'debug',
        protected string $channel = 'kyqo'
    ) {
        $this->minLevel = self::LEVELS[strtolower($level)] ?? 100;
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log('alert',     $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log('critical',  $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log('error',     $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log('warning',   $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log('notice',    $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log('info',      $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->log('debug',     $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        $levelInt = self::LEVELS[strtolower($level)] ?? 100;
        if ($levelInt < $this->minLevel) {
            return;
        }

        $message = $this->interpolate($message, $context);
        $ctx     = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line    = sprintf(
            '[%s] %s.%s: %s%s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $message,
            $ctx
        );

        $fh = fopen($this->path, 'a');
        if ($fh !== false) {
            flock($fh, LOCK_EX);
            fwrite($fh, $line);
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Interpolate context values into the message placeholders.
     * e.g. 'Hello {name}' + ['name' => 'world'] => 'Hello world'
     */
    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }
}
