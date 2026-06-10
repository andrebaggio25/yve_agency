<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;

class Logger
{
    /** @var array<string, Monolog> */
    private static array $channels = [];

    public static function channel(string $name = 'app'): Monolog
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = self::create($name);
        }

        return self::$channels[$name];
    }

    private static function create(string $name): Monolog
    {
        $logger  = new Monolog($name);
        $level   = self::resolveLevel(env('LOG_LEVEL', 'debug'));
        $logPath = storage_path("logs/{$name}.log");

        $logger->pushHandler(new RotatingFileHandler($logPath, 30, $level));

        if (env('APP_ENV', 'production') === 'development') {
            $logger->pushHandler(new StreamHandler('php://stderr', $level));
        }

        return $logger;
    }

    private static function resolveLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug'     => Level::Debug,
            'info'      => Level::Info,
            'notice'    => Level::Notice,
            'warning'   => Level::Warning,
            'error'     => Level::Error,
            'critical'  => Level::Critical,
            'alert'     => Level::Alert,
            'emergency' => Level::Emergency,
            default     => Level::Debug,
        };
    }
}
