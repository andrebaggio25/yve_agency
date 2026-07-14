<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\ClientRepository;

/**
 * `Repository::query()` faz bind por TIPO — não como string.
 *
 * Bug latente encontrado ao arquivar cliente (UX-02): `execute($params)` trata
 * todo parâmetro como string, então `false` vira `''` — e o PostgreSQL rejeita
 * (`invalid input syntax for type boolean`). Na prática, **qualquer `update`
 * com um booleano falso explodia com erro 500**. Ninguém tinha esbarrado porque
 * nenhum fluxo gravava `false` explicitamente.
 */
class RepositoryBindTest extends FeatureTestCase
{
    public function test_update_com_booleano_falso_funciona(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente', portal: true);

        $repo = new ClientRepository();
        $repo->updateById($client['id'], ['portal_enabled' => false]);

        $value = $this->pdo->query("SELECT portal_enabled FROM clients WHERE id = {$client['id']}")->fetchColumn();
        $this->assertFalse((bool) $value, 'Gravar false num campo boolean não pode falhar nem virar true.');
    }

    public function test_update_com_booleano_verdadeiro_e_com_null(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente', portal: false);

        $repo = new ClientRepository();
        $repo->updateById($client['id'], ['portal_enabled' => true, 'drive_folder_id' => null]);

        $row = $this->pdo->query("SELECT portal_enabled, drive_folder_id FROM clients WHERE id = {$client['id']}")->fetch();
        $this->assertTrue((bool) $row['portal_enabled']);
        $this->assertNull($row['drive_folder_id']);
    }
}
