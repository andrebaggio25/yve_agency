<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bloqueio de login por conta (SEC-18): complementa o rate limit por IP, que não
 * segura um ataque distribuído contra um e-mail só.
 *
 * Fica em arquivo de propósito, não em coluna de `users`: migrations rodam pelo
 * painel /admin/migrations, que exige login. Se o bloqueio dependesse de coluna
 * nova, um deploy antes da migration deixaria todo mundo de fora, inclusive quem
 * roda a migration.
 *
 * Conta falhas para qualquer e-mail, exista ou não: tratar só os que existem
 * revelaria quais contas são reais.
 */
class LoginThrottle
{
    public const MAX_FAILURES = 10;
    public const LOCK_SECONDS = 900;

    /** Segundos restantes de bloqueio; 0 se a conta pode tentar. */
    public static function secondsLocked(string $email): int
    {
        $data = self::read($email);
        if (($data['failures'] ?? 0) < self::MAX_FAILURES) {
            return 0;
        }

        return max(0, (int) ($data['expires_at'] ?? 0) - time());
    }

    public static function recordFailure(string $email): void
    {
        $file = self::file($email);
        $fp   = @fopen($file, 'c+');
        if ($fp === false) {
            return; // Falha aberto: problema de I/O não pode impedir login legítimo.
        }

        flock($fp, LOCK_EX);
        $data = json_decode(stream_get_contents($fp) ?: '', true);
        if (!is_array($data) || (int) ($data['expires_at'] ?? 0) <= time()) {
            $data = ['failures' => 0];
        }

        $data['failures']   = (int) ($data['failures'] ?? 0) + 1;
        $data['expires_at'] = time() + self::LOCK_SECONDS;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public static function clear(string $email): void
    {
        $file = self::file($email);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** @return array<string, mixed> */
    private static function read(string $email): array
    {
        $file = self::file($email);
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || (int) ($data['expires_at'] ?? 0) <= time()) {
            return [];
        }

        return $data;
    }

    private static function file(string $email): string
    {
        return storage_path('framework/login_throttle_' . hash('sha256', mb_strtolower(trim($email))) . '.json');
    }
}
