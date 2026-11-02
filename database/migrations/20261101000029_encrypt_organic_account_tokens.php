<?php

declare(strict_types=1);

use App\Core\Secret;
use Phinx\Migration\AbstractMigration;

/**
 * SEC-14: cifra em repouso os tokens de Page/Instagram já gravados em
 * organic_accounts. Idempotente: pula o que já está cifrado. Reversível:
 * down() decifra de volta. A leitura tolera texto puro (Secret::decrypt),
 * então deploy e migration podem acontecer em qualquer ordem.
 */
final class EncryptOrganicAccountTokens extends AbstractMigration
{
    public function up(): void
    {
        $rows = $this->fetchAll('SELECT id, access_token FROM organic_accounts WHERE access_token IS NOT NULL');
        $stmt = $this->getAdapter()->getConnection()->prepare('UPDATE organic_accounts SET access_token = :t WHERE id = :id');

        foreach ($rows as $row) {
            $token = (string) $row['access_token'];
            if ($token === '' || Secret::isEncrypted($token)) {
                continue;
            }
            $stmt->execute([':t' => Secret::encrypt($token), ':id' => $row['id']]);
        }
    }

    public function down(): void
    {
        $rows = $this->fetchAll('SELECT id, access_token FROM organic_accounts WHERE access_token IS NOT NULL');
        $stmt = $this->getAdapter()->getConnection()->prepare('UPDATE organic_accounts SET access_token = :t WHERE id = :id');

        foreach ($rows as $row) {
            $token = (string) $row['access_token'];
            if ($token === '' || !Secret::isEncrypted($token)) {
                continue;
            }
            $stmt->execute([':t' => Secret::decrypt($token), ':id' => $row['id']]);
        }
    }
}
