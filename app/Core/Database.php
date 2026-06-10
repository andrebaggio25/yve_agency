<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $adapter = env('DB_ADAPTER', 'pgsql');

        self::$pdo = match ($adapter) {
            'pgsql'  => self::connectPgsql(),
            'mysql'  => self::connectMysql(),
            'sqlite' => self::connectSqlite(),
            default  => throw new RuntimeException("Unsupported DB adapter: {$adapter}"),
        };

        return self::$pdo;
    }

    private static function connectPgsql(): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '5432'),
            env('DB_NAME', 'yve_agency'),
        );

        return self::makePdo($dsn, env('DB_USER', 'postgres'), env('DB_PASS', ''));
    }

    private static function connectMysql(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'yve_agency'),
        );

        return self::makePdo($dsn, env('DB_USER', 'root'), env('DB_PASS', ''));
    }

    private static function connectSqlite(): PDO
    {
        $name = env('DB_NAME', ':memory:');
        return self::makePdo("sqlite:{$name}", '', '');
    }

    private static function makePdo(string $dsn, string $user, string $pass): PDO
    {
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return $pdo;
    }

    /** Resets connection (used in tests) */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
