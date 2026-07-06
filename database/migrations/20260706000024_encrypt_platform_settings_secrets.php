<?php

declare(strict_types=1);

use App\Core\Secret;
use Phinx\Migration\AbstractMigration;

/**
 * SEC-05: cifra em repouso as credenciais globais sensíveis já gravadas em
 * platform_settings (API keys, secret de app). Idempotente — pula valores já
 * cifrados. Reversível: down() decifra de volta.
 */
final class EncryptPlatformSettingsSecrets extends AbstractMigration
{
    private const KEYS = [
        'evolution_api_key',
        'meta_app_secret',
        'openai_api_key',
        'anthropic_api_key',
        'mail_password',
    ];

    public function up(): void
    {
        // Lista de chaves é constante (sem input do usuário) — seguro inline.
        $keyList = "'" . implode("','", self::KEYS) . "'";
        $rows    = $this->fetchAll("SELECT key, value FROM platform_settings WHERE key IN ({$keyList})");
        $pdo     = $this->getAdapter()->getConnection();

        foreach ($rows as $row) {
            $value = (string) ($row['value'] ?? '');
            if ($value === '' || Secret::isEncrypted($value)) {
                continue; // vazio ou já cifrado
            }
            $stmt = $pdo->prepare('UPDATE platform_settings SET value = :v WHERE key = :k');
            $stmt->execute([':v' => Secret::encrypt($value), ':k' => $row['key']]);
        }
    }

    public function down(): void
    {
        $keyList = "'" . implode("','", self::KEYS) . "'";
        $rows    = $this->fetchAll("SELECT key, value FROM platform_settings WHERE key IN ({$keyList})");
        $pdo     = $this->getAdapter()->getConnection();

        foreach ($rows as $row) {
            $value = (string) ($row['value'] ?? '');
            if ($value === '' || !Secret::isEncrypted($value)) {
                continue;
            }
            $stmt = $pdo->prepare('UPDATE platform_settings SET value = :v WHERE key = :k');
            $stmt->execute([':v' => Secret::decrypt($value), ':k' => $row['key']]);
        }
    }
}
