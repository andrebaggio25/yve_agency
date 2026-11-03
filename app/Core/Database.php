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
        $host = env('DB_HOST', '127.0.0.1');
        $isRemote = $host !== '127.0.0.1' && $host !== 'localhost';

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;application_name=yve_agency',
            $host,
            env('DB_PORT', '5432'),
            env('DB_NAME', 'yve_agency'),
            $isRemote ? 'require' : 'prefer',
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
        // Reuses the TCP+SSL socket across PHP-FPM requests — eliminates the
        // 200-500ms SSL handshake cost on every page load for remote hosts.
        // DB_PERSISTENT=false desliga, para pooler em modo transação (INFRA-02).
        $persistent = filter_var(env('DB_PERSISTENT', 'true'), FILTER_VALIDATE_BOOL);

        try {
            $pdo = self::open($dsn, $user, $pass, $persistent);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        if ($persistent && !self::isAlive($pdo)) {
            // INFRA-02: a conexão guardada morreu (pooler, rede, reinício do
            // banco) e o PHP a devolve sem reclamar — a primeira query dava 500
            // na cara do usuário. Esta requisição segue numa conexão nova; o
            // processo troca a persistente sozinho na próxima, ao ver que ela
            // ficou marcada como quebrada.
            try {
                $pdo = self::open($dsn, $user, $pass, false);
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return $pdo;
    }

    private static function open(string $dsn, string $user, string $pass, bool $persistent): PDO
    {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => $persistent,
        ]);
    }

    /** `exec`, não `query`: não cria prepared statement no servidor, e passa pelo pooler. */
    private static function isAlive(PDO $pdo): bool
    {
        try {
            $pdo->exec('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** Resets connection (used in tests) */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
