<?php

declare(strict_types=1);

namespace App\Core;

class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true',  '(true)'  => true,
            'false', '(false)' => false,
            'null',  '(null)'  => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new \RuntimeException("Required environment variable [{$key}] is not set.");
        }

        return (string) $value;
    }
}
