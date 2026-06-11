<?php

declare(strict_types=1);

namespace App\Core;

class Lang
{
    private static string $locale = 'pt';
    private static array  $lines  = [];
    private static array  $loaded = [];

    public static function setLocale(string $locale): void
    {
        $locale = self::normalize($locale);
        if (self::$locale === $locale && isset(self::$loaded[$locale])) {
            return;
        }
        self::$locale = $locale;
        self::load($locale);
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function t(string $key, array $replace = []): string
    {
        if (!isset(self::$loaded[self::$locale])) {
            self::load(self::$locale);
        }

        $value = self::$lines[$key] ?? self::fallback($key);

        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, (string) $v, $value);
        }

        return $value;
    }

    /** Load locale file into memory */
    public static function load(string $locale): void
    {
        $locale = self::normalize($locale);
        $path   = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '.php';

        if (file_exists($path)) {
            $lines = require $path;
            self::$lines  = array_merge(self::$lines, is_array($lines) ? $lines : []);
            self::$loaded[$locale] = true;
        } else {
            // Fallback to PT if requested locale file doesn't exist
            $fallback = dirname(__DIR__, 2) . '/resources/lang/pt.php';
            if (file_exists($fallback)) {
                $lines = require $fallback;
                self::$lines  = is_array($lines) ? $lines : [];
                self::$loaded[$locale] = true;
            }
        }
    }

    /** Map locale codes to canonical short codes */
    public static function normalize(string $locale): string
    {
        return match (strtolower(substr($locale, 0, 2))) {
            'en' => 'en',
            'es' => 'es',
            default => 'pt',
        };
    }

    public static function supportedLocales(): array
    {
        return [
            'pt' => 'Português',
            'en' => 'English',
            'es' => 'Español',
        ];
    }

    private static function fallback(string $key): string
    {
        // Return last segment after dot as readable label
        $parts = explode('.', $key);
        return str_replace('_', ' ', ucfirst(end($parts)));
    }
}
