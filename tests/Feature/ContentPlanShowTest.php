<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Visão "Semana" do plano: a grade seg–dom é a superfície onde a agência
 * encaixa as programações nos dias — inclusive mais de uma no mesmo dia.
 */
class ContentPlanShowTest extends FeatureTestCase
{
    /** @return array{plan:int} */
    private function seedPlanWithItems(int $agencyId): array
    {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Semana 13/07', '2026-07-13', '2026-07-19', 'draft', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id']]);
        $planId = (int) $stmt->fetchColumn();

        // Dois posts na quarta (15/07) e um sem data.
        foreach ([['2026-07-15', '09:00'], ['2026-07-15', '18:00'], [null, null]] as $i => [$date, $time]) {
            $this->pdo->prepare(
                "INSERT INTO content_plan_items
                    (content_plan_id, client_id, publish_date, publish_time, platform, content_type, title, status, sort_order, created_at)
                 VALUES (:p, :c, :d, :t, 'instagram', 'Story', :ti, 'draft', :o, NOW())"
            )->execute([':p' => $planId, ':c' => $client['id'], ':d' => $date, ':t' => $time, ':ti' => "Post {$i}", ':o' => $i]);
        }

        return ['plan' => $planId];
    }

    public function test_show_renderiza_a_grade_com_os_7_dias_da_semana(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItems($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view']);

        $response = $this->get('/conteudo/' . $seed['plan']);

        $this->assertSame(200, $response->getStatus());
        $body = $response->getBody();
        foreach (['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19'] as $day) {
            $this->assertStringContainsString('data-day="' . $day . '"', $body, "A grade precisa do dia {$day}.");
        }
        $this->assertStringContainsString('de 7 dias com post', $body);
    }

    public function test_dois_posts_do_mesmo_dia_aparecem_na_mesma_coluna(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItems($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view']);

        $body = $this->get('/conteudo/' . $seed['plan'])->getBody();

        // Recorta a coluna da quarta e confere os dois horários dentro dela.
        $start = strpos($body, 'data-day="2026-07-15"');
        $end   = strpos($body, 'data-day="2026-07-16"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $column = substr($body, $start, $end - $start);

        $this->assertStringContainsString('09:00', $column);
        $this->assertStringContainsString('18:00', $column);
    }

    public function test_post_sem_data_aparece_na_faixa_de_reagendamento(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItems($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view']);

        $body = $this->get('/conteudo/' . $seed['plan'])->getBody();

        $this->assertStringContainsString('sem dia definido', $body);
    }
}
