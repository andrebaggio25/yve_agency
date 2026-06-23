#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migração única: cifra em repouso os tokens de integração que ainda estão
 * em texto puro (gravados antes da introdução de App\Core\Secret).
 *
 * Seguro de rodar mais de uma vez: valores já cifrados são ignorados
 * (Secret::isEncrypted detecta via MAC do libsodium).
 *
 *   php bin/encrypt_tokens.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;
use App\Core\Secret;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = Database::connection();

/**
 * @param array<int,string> $columns
 */
function migrateTable(\PDO $pdo, string $table, array $columns): void
{
    $cols = implode(', ', $columns);
    $rows = $pdo->query("SELECT id, {$cols} FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
    $changed = 0;

    foreach ($rows as $row) {
        $sets = [];
        $params = [':id' => $row['id']];

        foreach ($columns as $col) {
            $val = $row[$col] ?? null;
            if ($val === null || $val === '' || Secret::isEncrypted($val)) {
                continue;
            }
            $sets[] = "{$col} = :{$col}";
            $params[":{$col}"] = Secret::encrypt($val);
        }

        if ($sets === []) {
            continue;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        $changed++;
    }

    echo sprintf("[%s] %d registro(s) atualizado(s) de %d\n", $table, $changed, count($rows));
}

echo "Cifrando tokens em repouso...\n";

migrateTable($pdo, 'ad_accounts', ['access_token']);
migrateTable($pdo, 'google_drive_integrations', ['access_token', 'refresh_token']);
migrateTable($pdo, 'clickup_integrations', ['api_token']);

echo "Concluído.\n";
