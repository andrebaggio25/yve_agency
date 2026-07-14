<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * UX-02 — arquivar cliente.
 *
 * O botão dizia "Excluir" e **nunca excluiu**: marcava `status = 'cancelled'`.
 * Isso, por si, era o comportamento certo (faturas e contratos são `RESTRICT` —
 * apagar de verdade falharia; planos e arquivos são `CASCADE` — sumiriam sem
 * aviso). O problema era outro, e sério: **o soft-delete não desativava o
 * portal**. O cliente "removido" continuava entrando pelo link, vendo faturas e
 * enviando arquivos.
 */
class ClientArchiveTest extends FeatureTestCase
{
    public function test_arquivar_revoga_o_acesso_ao_portal(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Cliente a Arquivar');

        // Antes: o portal abre normalmente.
        $this->assertSame(200, $this->get('/portal/' . $client['portal_token'])->getStatus());

        $this->actingAs($user['id'], permissions: ['clients.delete', 'clients.view', 'clients.view_all']);
        $this->post('/clientes/' . $client['id'], ['_method' => 'DELETE']);

        // Depois: o link do cliente arquivado deixa de funcionar.
        $this->assertSame(
            403,
            $this->get('/portal/' . $client['portal_token'])->getStatus(),
            'Cliente arquivado NÃO pode continuar acessando o portal.'
        );
    }

    public function test_arquivar_preserva_o_historico(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId);

        // Um plano de conteúdo vinculado (CASCADE no banco — não pode sumir).
        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, status, created_at)
             VALUES (:a, :c, 'Plano de Julho', 'approved', NOW())"
        )->execute([':a' => $agencyId, ':c' => $client['id']]);

        $this->actingAs($user['id'], permissions: ['clients.delete']);
        $this->post('/clientes/' . $client['id'], ['_method' => 'DELETE']);

        // O cliente continua no banco (arquivado), e o plano segue lá.
        $row = $this->pdo->query("SELECT status, portal_enabled FROM clients WHERE id = {$client['id']}")->fetch();
        $this->assertSame('cancelled', $row['status']);
        $this->assertFalse((bool) $row['portal_enabled'], 'Arquivar precisa desligar o portal.');

        $plans = (int) $this->pdo->query("SELECT COUNT(*) FROM content_plans WHERE client_id = {$client['id']}")->fetchColumn();
        $this->assertSame(1, $plans, 'O histórico do cliente não pode ser apagado ao arquivar.');
    }

    public function test_cliente_arquivado_pode_ser_reativado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId);

        $this->actingAs($user['id'], permissions: ['clients.delete'], clientIds: [$client['id']]);

        $this->post('/clientes/' . $client['id'], ['_method' => 'DELETE']);
        $this->post('/clientes/' . $client['id'] . '/reativar');

        $status = $this->pdo->query("SELECT status FROM clients WHERE id = {$client['id']}")->fetchColumn();
        $this->assertSame('active', $status);
    }

    public function test_sem_permissao_nao_arquiva(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId);

        $this->actingAs($user['id'], permissions: ['clients.view']);
        $this->assertSame(403, $this->post('/clientes/' . $client['id'], ['_method' => 'DELETE'])->getStatus());

        $status = $this->pdo->query("SELECT status FROM clients WHERE id = {$client['id']}")->fetchColumn();
        $this->assertSame('active', $status);
    }
}
