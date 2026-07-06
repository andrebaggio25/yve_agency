<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Secret;
use PDO;

/**
 * Repositório para configurações globais da plataforma.
 * Não estende Repository base pois não tem agency_id scope.
 *
 * Segredos (API keys, secret de app) são cifrados em repouso com {@see Secret}.
 * A cifragem é transparente: set() cifra na escrita e as leituras (get,
 * getMultiple, getAll) decifram. Valores legados em texto puro seguem
 * funcionando (Secret é tolerante a texto puro).
 */
class PlatformSettingsRepository
{
    /** Chaves cujo valor é sensível e deve ser cifrado em repouso. */
    private const ENCRYPTED_KEYS = [
        'evolution_api_key',
        'meta_app_secret',
        'openai_api_key',
        'anthropic_api_key',
        'mail_password',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->pdo->prepare('SELECT value FROM platform_settings WHERE key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $default;
        }
        return self::isSensitive($key) ? Secret::decrypt((string) $value) : $value;
    }

    public function set(string $key, mixed $value): void
    {
        $stored = self::isSensitive($key)
            ? Secret::encrypt((string) $value)
            : (string) $value;

        $this->pdo->prepare("
            INSERT INTO platform_settings (key, value, created_at, updated_at)
            VALUES (:key, :value, NOW(), NOW())
            ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()
        ")->execute([':key' => $key, ':value' => $stored]);
    }

    /** @param string[] $keys */
    public function getMultiple(array $keys): array
    {
        if (empty($keys)) return [];
        $placeholders = implode(',', array_map(fn($i) => ":k{$i}", array_keys($keys)));
        $params = [];
        foreach (array_values($keys) as $i => $k) $params[":k{$i}"] = $k;

        $stmt = $this->pdo->prepare(
            "SELECT key, value FROM platform_settings WHERE key IN ({$placeholders})"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Return all requested keys with defaults
        return $this->decryptMap(array_merge(array_fill_keys($keys, null), $rows));
    }

    public function getAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT key, value FROM platform_settings ORDER BY key'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        return $this->decryptMap($rows);
    }

    private static function isSensitive(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private function decryptMap(array $map): array
    {
        foreach ($map as $key => $value) {
            if ($value !== null && self::isSensitive($key)) {
                $map[$key] = Secret::decrypt((string) $value);
            }
        }
        return $map;
    }

    /** @param array<string,mixed> $map */
    public function setMultiple(array $map): void
    {
        foreach ($map as $key => $value) {
            $this->set($key, $value);
        }
    }
}
