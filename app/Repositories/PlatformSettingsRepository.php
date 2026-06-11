<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repositório para configurações globais da plataforma.
 * Não estende Repository base pois não tem agency_id scope.
 */
class PlatformSettingsRepository
{
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
        return $value !== false ? $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->pdo->prepare("
            INSERT INTO platform_settings (key, value, created_at, updated_at)
            VALUES (:key, :value, NOW(), NOW())
            ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()
        ")->execute([':key' => $key, ':value' => (string) $value]);
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
        return array_merge(array_fill_keys($keys, null), $rows);
    }

    public function getAll(): array
    {
        return $this->pdo->query(
            'SELECT key, value FROM platform_settings ORDER BY key'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @param array<string,mixed> $map */
    public function setMultiple(array $map): void
    {
        foreach ($map as $key => $value) {
            $this->set($key, $value);
        }
    }
}
