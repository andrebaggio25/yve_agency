<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * "Sua semana" no dashboard do portal (CONT-PORTAL): o cliente abre o portal
 * e vê os posts da semana corrente — sem nunca enxergar rascunho.
 */
class PortalWeekTest extends FeatureTestCase
{
    private function seedPlanWithPostToday(int $agencyId, array $client, string $status, string $title): void
    {
        $monday = \App\Services\ContentPlanService::mondayOf(date('Y-m-d'));

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, :t, :ws, (:ws)::date + 6, :s, NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':t' => $title, ':ws' => $monday, ':s' => $status]);
        $planId = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, publish_time, platform, content_type, title, status, created_at)
             VALUES (:p, :c, CURRENT_DATE, '10:00', 'instagram', 'Story', :t, 'draft', NOW())"
        )->execute([':p' => $planId, ':c' => $client['id'], ':t' => $title . ' — post de hoje']);
    }

    public function test_dashboard_mostra_o_post_da_semana_corrente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $this->seedPlanWithPostToday($agencyId, $client, 'sent', 'Plano Vivo');

        $body = $this->get('/portal/' . $client['portal_token'])->getBody();

        $this->assertStringContainsString('Plano Vivo — post de hoje', $body);
    }

    public function test_post_de_rascunho_nao_aparece_na_semana_do_cliente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $this->seedPlanWithPostToday($agencyId, $client, 'draft', 'Plano Oculto');

        $body = $this->get('/portal/' . $client['portal_token'])->getBody();

        $this->assertStringNotContainsString('Plano Oculto — post de hoje', $body);
    }
}
