<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Radar de pauta (CONT-RADAR): a listagem de planos avisa quais clientes
 * ativos ainda não têm plano na PRÓXIMA semana — o lembrete que evita
 * segunda-feira sem pauta — e agrupa os planos por semana.
 */
class ContentRadarTest extends FeatureTestCase
{
    private function login(int $agencyId): void
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view', 'content.create', 'clients.view_all']);
    }

    private function createPlanForWeek(int $agencyId, int $clientId, string $monday): void
    {
        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Plano', :ws, (:ws)::date + 6, 'draft', NOW())"
        )->execute([':a' => $agencyId, ':c' => $clientId, ':ws' => $monday]);
    }

    public function test_cliente_sem_plano_na_proxima_semana_aparece_no_radar(): void
    {
        $agencyId   = $this->createAgency();
        $comPlano   = $this->createClient($agencyId, 'Cliente Planejado');
        $semPlano   = $this->createClient($agencyId, 'Cliente Esquecido');
        $nextMonday = \App\Services\ContentPlanService::mondayOf(date('Y-m-d', strtotime('+7 days')));
        $this->createPlanForWeek($agencyId, (int) $comPlano['id'], $nextMonday);

        $this->login($agencyId);
        $body = $this->get('/conteudo')->getBody();

        // data-radar marca o banner de verdade — o comentário HTML da view
        // contém a frase e enganaria um assert por texto.
        $this->assertStringContainsString('data-radar', $body);
        $this->assertStringContainsString('Cliente Esquecido', $body);
        // Quem já tem plano não entra no aviso (o link de criar leva o nome no title).
        $this->assertStringNotContainsString('para Cliente Planejado', $body);
    }

    public function test_cliente_inativo_nao_aparece_no_radar_nem_no_plano_novo(): void
    {
        $agencyId = $this->createAgency();
        $ativo    = $this->createClient($agencyId, 'Cliente Ativo');
        $inativo  = $this->createClient($agencyId, 'Cliente Parado');
        $this->pdo->prepare("UPDATE clients SET status = 'inactive' WHERE id = :id")->execute([':id' => $inativo['id']]);

        $this->login($agencyId);

        // O filtro da listagem ainda cita o inativo, então o assert olha o link do radar.
        $lista = $this->get('/conteudo')->getBody();
        $this->assertStringContainsString('para Cliente Ativo', $lista);
        $this->assertStringNotContainsString('para Cliente Parado', $lista);
        $this->assertStringContainsString('Cliente Parado', $lista, 'filtro mantém o inativo para achar planos antigos');

        $form = $this->get('/conteudo/criar')->getBody();
        $this->assertStringContainsString('Cliente Ativo', $form);
        $this->assertStringNotContainsString('Cliente Parado', $form);

        $resp = $this->post('/conteudo', [
            'client_id'  => $inativo['id'],
            'week_start' => \App\Services\ContentPlanService::mondayOf(date('Y-m-d', strtotime('+7 days'))),
        ]);
        $this->assertSame(302, $resp->getStatus());
        $criados = $this->pdo->prepare('SELECT COUNT(*) FROM content_plans WHERE client_id = :c');
        $criados->execute([':c' => $inativo['id']]);
        $this->assertSame(0, (int) $criados->fetchColumn());
    }

    public function test_todos_planejados_nao_mostra_radar(): void
    {
        $agencyId   = $this->createAgency();
        $client     = $this->createClient($agencyId, 'Cliente Único');
        $nextMonday = \App\Services\ContentPlanService::mondayOf(date('Y-m-d', strtotime('+7 days')));
        $this->createPlanForWeek($agencyId, (int) $client['id'], $nextMonday);

        $this->login($agencyId);
        $body = $this->get('/conteudo')->getBody();

        $this->assertStringNotContainsString('data-radar', $body);
    }

    public function test_listagem_agrupa_por_semana(): void
    {
        $agencyId      = $this->createAgency();
        $client        = $this->createClient($agencyId);
        $currentMonday = \App\Services\ContentPlanService::mondayOf(date('Y-m-d'));
        $nextMonday    = date('Y-m-d', strtotime($currentMonday . ' +7 days'));
        $pastMonday    = date('Y-m-d', strtotime($currentMonday . ' -14 days'));

        foreach ([$currentMonday, $nextMonday, $pastMonday] as $monday) {
            $this->createPlanForWeek($agencyId, (int) $client['id'], $monday);
        }

        $this->login($agencyId);
        $body = $this->get('/conteudo')->getBody();

        $this->assertStringContainsString('Semana atual', $body);
        $this->assertStringContainsString('Próxima semana', $body);
        $this->assertStringContainsString('Semanas anteriores', $body);
    }
}
