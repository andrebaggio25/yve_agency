<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Modelo semanal por cliente: a agência monta uma semana boa, salva como
 * padrão, e os próximos planos do cliente nascem com a mesma grade —
 * dias, horários, plataformas e formatos. Nunca o conteúdo.
 */
class ContentPlanTemplateTest extends FeatureTestCase
{
    /** @return array{plan:int,client:int} */
    private function seedPlan(int $agencyId, ?array $client = null): array
    {
        $client ??= $this->createClient($agencyId, 'Studio Aline');

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Semana base', '2026-07-13', '2026-07-19', 'draft', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id']]);
        $planId = (int) $stmt->fetchColumn();

        // Terça (Reels 09:00) e sexta (Carrossel 18:00) — a grade da casa.
        foreach ([['2026-07-14', '09:00', 'Reels / Vídeo'], ['2026-07-17', '18:00', 'Carrossel']] as $i => [$d, $t, $type]) {
            $this->pdo->prepare(
                "INSERT INTO content_plan_items
                    (content_plan_id, client_id, publish_date, publish_time, platform, content_type, caption, status, sort_order, created_at)
                 VALUES (:p, :c, :d, :t, 'instagram', :ct, 'Legenda que NÃO vira modelo', 'draft', :o, NOW())"
            )->execute([':p' => $planId, ':c' => $client['id'], ':d' => $d, ':t' => $t, ':ct' => $type, ':o' => $i]);
        }

        return ['plan' => $planId, 'client' => (int) $client['id']];
    }

    private function login(int $agencyId): void
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view', 'content.create', 'content.edit', 'clients.view_all']);
    }

    public function test_salvar_modelo_captura_a_grade_do_plano(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);
        $seed = $this->seedPlan($agencyId);

        $response = $this->post("/conteudo/{$seed['plan']}/salvar-modelo");

        $this->assertSame(302, $response->getStatus());
        $tpl = $this->pdo->query("SELECT * FROM content_plan_templates WHERE client_id = {$seed['client']}")->fetch();
        $this->assertNotFalse($tpl, 'O modelo precisa existir.');

        $items = json_decode((string) $tpl['items'], true);
        $this->assertCount(2, $items);
        // 14/07/2026 é terça (weekday 2); 17/07 é sexta (5).
        $this->assertSame(2, $items[0]['weekday']);
        $this->assertSame('09:00', $items[0]['publish_time']);
        $this->assertSame('Reels / Vídeo', $items[0]['content_type']);
        $this->assertSame(5, $items[1]['weekday']);
        $this->assertArrayNotHasKey('caption', $items[0], 'Modelo é grade, nunca conteúdo.');
    }

    public function test_criar_plano_com_modelo_preenche_os_dias_certos(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);
        $seed = $this->seedPlan($agencyId);
        $this->post("/conteudo/{$seed['plan']}/salvar-modelo");

        // Semana de 20/07 (segunda) — terça = 21/07, sexta = 24/07.
        $this->post('/conteudo', [
            'client_id'      => $seed['client'],
            'week_start'     => '2026-07-20',
            'apply_template' => '1',
        ]);

        $newPlan = (int) $this->pdo->query(
            "SELECT id FROM content_plans WHERE week_start = '2026-07-20'"
        )->fetchColumn();
        $this->assertGreaterThan(0, $newPlan);

        $dates = $this->pdo->query(
            "SELECT publish_date, publish_time, caption FROM content_plan_items WHERE content_plan_id = {$newPlan} ORDER BY sort_order"
        )->fetchAll();

        $this->assertCount(2, $dates);
        $this->assertSame('2026-07-21', $dates[0]['publish_date'], 'Reels de terça continua na terça.');
        $this->assertSame('2026-07-24', $dates[1]['publish_date'], 'Carrossel de sexta continua na sexta.');
        $this->assertNull($dates[0]['caption'], 'O conteúdo nasce vazio.');
    }

    public function test_sem_a_flag_o_plano_nasce_vazio_mesmo_com_modelo(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);
        $seed = $this->seedPlan($agencyId);
        $this->post("/conteudo/{$seed['plan']}/salvar-modelo");

        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-20']);

        $newPlan = (int) $this->pdo->query("SELECT id FROM content_plans WHERE week_start = '2026-07-20'")->fetchColumn();
        $count   = (int) $this->pdo->query("SELECT COUNT(*) FROM content_plan_items WHERE content_plan_id = {$newPlan}")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_salvar_de_novo_substitui_o_modelo_anterior(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);
        $client = $this->createClient($agencyId, 'Studio Aline');

        $seedA = $this->seedPlan($agencyId, $client);
        $this->post("/conteudo/{$seedA['plan']}/salvar-modelo");

        // Segundo plano com UM item só — o modelo novo tem de vencer.
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Semana enxuta', '2026-07-20', '2026-07-26', 'draft', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id']]);
        $planB = (int) $stmt->fetchColumn();
        $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, platform, content_type, status, created_at)
             VALUES (:p, :c, '2026-07-22', 'tiktok', 'Story', 'draft', NOW())"
        )->execute([':p' => $planB, ':c' => $client['id']]);

        $this->post("/conteudo/{$planB}/salvar-modelo");

        $rows = $this->pdo->query("SELECT items FROM content_plan_templates WHERE client_id = {$client['id']}")->fetchAll();
        $this->assertCount(1, $rows, 'Um modelo por cliente — upsert, não duplicata.');
        $this->assertCount(1, json_decode((string) $rows[0]['items'], true));
    }

    public function test_modelo_de_um_cliente_nao_vaza_para_outro(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);
        $seed  = $this->seedPlan($agencyId);
        $outro = $this->createClient($agencyId, 'Outro Cliente');
        $this->post("/conteudo/{$seed['plan']}/salvar-modelo");

        $this->post('/conteudo', [
            'client_id'      => $outro['id'],
            'week_start'     => '2026-07-20',
            'apply_template' => '1',
        ]);

        $newPlan = (int) $this->pdo->query(
            "SELECT id FROM content_plans WHERE client_id = {$outro['id']}"
        )->fetchColumn();
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM content_plan_items WHERE content_plan_id = {$newPlan}")->fetchColumn();
        $this->assertSame(0, $count, 'O modelo é do cliente, não da agência.');
    }
}
