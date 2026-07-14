<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * PROD-04 — calendário de conteúdo.
 *
 * Ninguém planeja conteúdo em lista: planeja olhando o mês, onde buraco e
 * acúmulo ficam evidentes. Os dados já existiam; faltava a forma de ver.
 */
class ContentCalendarTest extends FeatureTestCase
{
    private function seedItem(int $agencyId, int $clientId, string $date, string $title): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, status, created_at)
             VALUES (:a, :c, 'Plano', :w, 'draft', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':w' => $date]);
        $planId = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, platform, content_type, title, status, created_at)
             VALUES (:p, :c, :d, 'instagram', 'feed', :t, 'draft', NOW())"
        )->execute([':p' => $planId, ':c' => $clientId, ':d' => $date, ':t' => $title]);
    }

    public function test_calendario_mostra_apenas_os_itens_do_mes_pedido(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId);

        $this->seedItem($agencyId, $client['id'], '2026-07-15', 'Post de Julho');
        $this->seedItem($agencyId, $client['id'], '2026-08-03', 'Post de Agosto');

        $this->actingAs($user['id'], permissions: ['content.view', 'clients.view_all']);

        $julho = $this->get('/conteudo/calendario?month=2026-07');
        $this->assertSame(200, $julho->getStatus());
        $this->assertStringContainsString('Post de Julho', $julho->getBody());
        $this->assertStringNotContainsString('Post de Agosto', $julho->getBody());
        $this->assertStringContainsString('Julho de 2026', $julho->getBody());

        $agosto = $this->get('/conteudo/calendario?month=2026-08');
        $this->assertStringContainsString('Post de Agosto', $agosto->getBody());
        $this->assertStringNotContainsString('Post de Julho', $agosto->getBody());
    }

    /** Mês inválido na URL não pode quebrar a tela — cai no mês atual. */
    public function test_mes_invalido_cai_no_mes_atual(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: ['content.view']);

        $response = $this->get('/conteudo/calendario?month=lixo-aqui');
        $this->assertSame(200, $response->getStatus());
    }

    public function test_nao_mostra_conteudo_de_outra_agencia(): void
    {
        $agencyA = $this->createAgency('A');
        $agencyB = $this->createAgency('B');
        $user    = $this->createUser($agencyA, 'a@test.com');

        $clientB = $this->createClient($agencyB, 'Cliente da B');
        $this->seedItem($agencyB, $clientB['id'], '2026-07-15', 'Post secreto da B');

        $this->actingAs($user['id'], permissions: ['content.view', 'clients.view_all']);

        $body = $this->get('/conteudo/calendario?month=2026-07')->getBody();
        $this->assertStringNotContainsString('Post secreto da B', $body);
    }

    public function test_exige_permissao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: []);
        $this->assertSame(403, $this->get('/conteudo/calendario')->getStatus());
    }
}
