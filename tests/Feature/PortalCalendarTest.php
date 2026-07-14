<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Calendário mensal do portal: consulta pura, escopada pelo token do cliente.
 * As duas regras que não podem falhar: rascunho nunca aparece (o cliente não
 * vê o que a agência ainda está montando) e um cliente jamais enxerga o
 * conteúdo de outro.
 */
class PortalCalendarTest extends FeatureTestCase
{
    private function seedPlanWithItem(int $agencyId, array $client, string $status, string $title): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, :t, '2026-07-13', '2026-07-19', :s, NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':t' => $title, ':s' => $status]);
        $planId = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, platform, content_type, title, status, created_at)
             VALUES (:p, :c, '2026-07-15', 'instagram', 'Story', :t, 'draft', NOW())"
        )->execute([':p' => $planId, ':c' => $client['id'], ':t' => $title . ' — post']);
    }

    public function test_calendario_abre_com_token_valido_e_mostra_o_post(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $this->seedPlanWithItem($agencyId, $client, 'sent', 'Plano Enviado');

        $response = $this->get("/portal/{$client['portal_token']}/planos/calendario?month=2026-07");

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Plano Enviado — post', $response->getBody());
    }

    public function test_rascunho_nao_aparece_no_calendario_do_cliente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $this->seedPlanWithItem($agencyId, $client, 'draft', 'Plano Secreto');

        $body = $this->get("/portal/{$client['portal_token']}/planos/calendario?month=2026-07")->getBody();

        $this->assertStringNotContainsString('Plano Secreto', $body, 'Rascunho é bastidor da agência — o cliente não vê.');
    }

    public function test_nao_mostra_post_de_outro_cliente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente A');
        $outro    = $this->createClient($agencyId, 'Cliente B');
        $this->seedPlanWithItem($agencyId, $outro, 'sent', 'Plano do Outro');

        $body = $this->get("/portal/{$client['portal_token']}/planos/calendario?month=2026-07")->getBody();

        $this->assertStringNotContainsString('Plano do Outro', $body);
    }

    public function test_mes_invalido_cai_no_mes_atual_sem_quebrar(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $response = $this->get("/portal/{$client['portal_token']}/planos/calendario?month=banana");

        $this->assertSame(200, $response->getStatus());
    }

    public function test_navegacao_entre_semanas_no_detalhe_do_plano(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $ids = [];
        foreach ([['2026-07-06', '2026-07-12'], ['2026-07-13', '2026-07-19']] as [$ws, $we]) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
                 VALUES (:a, :c, :t, :ws, :we, 'sent', NOW()) RETURNING id"
            );
            $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':t' => "Semana {$ws}", ':ws' => $ws, ':we' => $we]);
            $ids[] = (int) $stmt->fetchColumn();
        }

        $body = $this->get("/portal/{$client['portal_token']}/planos/{$ids[1]}")->getBody();

        // O plano da semana 13/07 aponta para o anterior (06/07).
        $this->assertStringContainsString("/planos/{$ids[0]}", $body);
    }
}
