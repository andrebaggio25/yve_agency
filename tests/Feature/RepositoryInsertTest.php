<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\DriveFolderRepository;

/**
 * INFRA-03 — `Repository::insert()` devolve o ID de verdade.
 *
 * Usava `lastInsertId()` sem nome de sequência: no PostgreSQL isso depende de o
 * driver inferir a sequência certa e pode devolver o ID errado (ou vazio),
 * especialmente atrás de um pooler como o do Supabase. Um ID errado aqui vira
 * registro órfão ou, pior, vínculo apontando para a linha de outro. Agora usa
 * `RETURNING id` — o ID vem do próprio INSERT.
 */
class RepositoryInsertTest extends FeatureTestCase
{
    public function test_insert_devolve_o_id_da_linha_criada(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $repo = new DriveFolderRepository();

        $id = $repo->create([
            'agency_id'       => $agencyId,
            'client_id'       => $client['id'],
            'parent_id'       => null,
            'drive_folder_id' => 'folder-abc',
            'name'            => 'Pasta A',
        ]);

        $this->assertGreaterThan(0, $id, 'insert() precisa devolver o ID gerado.');

        // O ID tem de apontar para a linha que acabamos de criar — não para outra.
        $stmt = $this->pdo->prepare('SELECT name FROM drive_folders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->assertSame('Pasta A', $stmt->fetchColumn());
    }

    public function test_ids_sequenciais_nao_se_repetem(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $repo     = new DriveFolderRepository();

        $ids = [];
        foreach (['A', 'B', 'C'] as $n) {
            $ids[] = $repo->create([
                'agency_id'       => $agencyId,
                'client_id'       => $client['id'],
                'parent_id'       => null,
                'drive_folder_id' => 'folder-' . $n,
                'name'            => 'Pasta ' . $n,
            ]);
        }

        $this->assertCount(3, array_unique($ids), 'IDs repetidos indicam lastInsertId/sequência errada.');
    }
}
