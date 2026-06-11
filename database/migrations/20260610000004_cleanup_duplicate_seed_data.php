<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Remove dados duplicados criados por execução dupla do seeder.
 *
 * Problema: o seeder usava `WHERE slug = ?` para roles, mas o constraint único
 * na tabela é (slug, agency_id). Como agency_id é NULL para roles globais e
 * PostgreSQL trata dois NULLs como distintos em constraints UNIQUE, a segunda
 * execução conseguiu inserir roles duplicadas (ids 10-18).
 *
 * Também havia 2 agências com o mesmo nome por bug similar no check de idempotência.
 *
 * Este script:
 *  1. Para cada role duplicada: remapeia user_roles para o id canônico (menor id)
 *  2. Apaga role_permissions das duplicatas
 *  3. Apaga as roles duplicadas
 *  4. Apaga agências duplicadas (move users para a agência canônica antes)
 *  5. Corrige o seeder: adiciona `ON CONFLICT DO NOTHING` via unique index em roles(slug)
 *     onde agency_id IS NULL (parcial, só para roles globais)
 */
final class CleanupDuplicateSeedData extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // ── 1. Roles duplicadas ────────────────────────────────────────────────
        // Para cada slug com mais de uma role de agency_id IS NULL, manter o
        // menor id (canônico) e redirecionar referências para ele.

        $duplicates = $pdo->query("
            SELECT slug, MIN(id) AS canonical_id, array_agg(id ORDER BY id) AS all_ids
            FROM roles
            WHERE agency_id IS NULL
            GROUP BY slug
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($duplicates as $row) {
            $canonicalId = (int) $row['canonical_id'];
            // Parse Postgres array literal: {1,2,3}
            $allIds = array_map('intval', explode(',', trim($row['all_ids'], '{}')));
            $extraIds = array_filter($allIds, fn($id) => $id !== $canonicalId);

            if (empty($extraIds)) continue;

            $placeholders = implode(',', $extraIds);

            // Redirecionar user_roles para o id canônico
            $pdo->exec("
                UPDATE user_roles
                SET role_id = {$canonicalId}
                WHERE role_id IN ({$placeholders})
            ");

            // Remover role_permissions duplicadas
            $pdo->exec("
                DELETE FROM role_permissions
                WHERE role_id IN ({$placeholders})
            ");

            // Remover roles duplicadas
            $pdo->exec("
                DELETE FROM roles
                WHERE id IN ({$placeholders})
            ");

            echo "  ✓ Role '{$row['slug']}': kept id={$canonicalId}, removed ids=[{$placeholders}]\n";
        }

        // ── 2. Agências duplicadas ─────────────────────────────────────────────
        // Manter a agência com menor id para cada nome, mover users das demais.

        $dupAgencies = $pdo->query("
            SELECT name, MIN(id) AS canonical_id, array_agg(id ORDER BY id) AS all_ids
            FROM agencies
            GROUP BY name
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dupAgencies as $row) {
            $canonicalId = (int) $row['canonical_id'];
            $allIds = array_map('intval', explode(',', trim($row['all_ids'], '{}')));
            $extraIds = array_filter($allIds, fn($id) => $id !== $canonicalId);

            if (empty($extraIds)) continue;

            $placeholders = implode(',', $extraIds);

            // Mover users para a agência canônica
            $pdo->exec("
                UPDATE users
                SET agency_id = {$canonicalId}
                WHERE agency_id IN ({$placeholders})
            ");

            // Mover clients
            $pdo->exec("
                UPDATE clients
                SET agency_id = {$canonicalId}
                WHERE agency_id IN ({$placeholders})
            ");

            // Mover whatsapp_instances
            $pdo->exec("
                UPDATE whatsapp_instances
                SET agency_id = {$canonicalId}
                WHERE agency_id IN ({$placeholders})
            ");

            // Remover agências duplicadas (sem dependentes agora)
            $pdo->exec("
                DELETE FROM agencies
                WHERE id IN ({$placeholders})
            ");

            echo "  ✓ Agency '{$row['name']}': kept id={$canonicalId}, removed ids=[{$placeholders}]\n";
        }

        // ── 3. Prevenir re-ocorrência: índice parcial único em roles globais ───
        // Garante que (slug, agency_id IS NULL) seja único sem impedir que
        // agências criem roles com o mesmo slug no futuro.
        if (!$this->hasIndexByName('roles', 'roles_global_slug_unique')) {
            $pdo->exec("
                CREATE UNIQUE INDEX roles_global_slug_unique
                ON roles (slug)
                WHERE agency_id IS NULL
            ");
            echo "  ✓ Unique partial index on roles(slug) WHERE agency_id IS NULL created\n";
        }

        echo "  ✓ Cleanup complete\n";
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $pdo->exec("DROP INDEX IF EXISTS roles_global_slug_unique");
    }

    private function hasIndexByName(string $table, string $indexName): bool
    {
        $pdo = $this->getAdapter()->getConnection();
        $result = $pdo->query("
            SELECT 1 FROM pg_indexes
            WHERE tablename = '{$table}' AND indexname = '{$indexName}'
        ")->fetchColumn();
        return (bool) $result;
    }
}
