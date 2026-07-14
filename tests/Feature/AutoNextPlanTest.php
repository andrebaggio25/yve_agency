<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Automação content.approved_create_next_plan: o cliente aprova a semana e o
 * rascunho da seguinte nasce sozinho. As regras que não podem falhar:
 * idempotência (aprovar duas vezes não duplica) e respeito à criação manual
 * antecipada (semana ocupada não ganha segundo plano).
 */
class AutoNextPlanTest extends FeatureTestCase
{
    /** @return array{plan:int,client:array{id:int,portal_token:string}} */
    private function seedSentPlan(int $agencyId): array
    {
        $client = $this->createClient($agencyId, 'Studio Aline');

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, 'Semana 13/07', '2026-07-13', '2026-07-19', 'sent', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id']]);
        $planId = (int) $stmt->fetchColumn();

        // Um post na quarta — é essa estrutura que a cópia herda sem modelo.
        $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, publish_time, platform, content_type, caption, status, created_at)
             VALUES (:p, :c, '2026-07-15', '09:00', 'instagram', 'Reels / Vídeo', 'Legenda antiga', 'draft', NOW())"
        )->execute([':p' => $planId, ':c' => $client['id']]);

        return ['plan' => $planId, 'client' => $client];
    }

    private function enableAutomation(int $agencyId, int $clientId): void
    {
        $this->pdo->prepare(
            "INSERT INTO automation_rules (agency_id, automation_key, status) VALUES (:a, 'content.approved_create_next_plan', 'active')"
        )->execute([':a' => $agencyId]);
        $this->pdo->prepare(
            "INSERT INTO client_automation_settings (agency_id, client_id, automation_key, enabled)
             VALUES (:a, :c, 'content.approved_create_next_plan', TRUE)"
        )->execute([':a' => $agencyId, ':c' => $clientId]);
    }

    private function approve(array $seed): void
    {
        $this->post("/portal/{$seed['client']['portal_token']}/planos/{$seed['plan']}/aprovar");
    }

    public function test_aprovacao_cria_o_rascunho_da_segunda_seguinte(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $this->enableAutomation($agencyId, $seed['client']['id']);

        $this->approve($seed);

        $next = $this->pdo->query(
            "SELECT * FROM content_plans WHERE week_start = '2026-07-20'"
        )->fetch();

        $this->assertNotFalse($next, 'O plano da semana seguinte tem de nascer na aprovação.');
        $this->assertSame('draft', $next['status']);
        $this->assertSame('2026-07-26', $next['week_end']);
        $this->assertStringContainsString('20/07 – 26/07', $next['title']);

        // Estrutura herdada do plano aprovado (sem modelo): quarta 22/07, sem conteúdo.
        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE content_plan_id = {$next['id']}")->fetch();
        $this->assertNotFalse($item);
        $this->assertSame('2026-07-22', $item['publish_date']);
        $this->assertNull($item['caption']);
    }

    public function test_aprovar_duas_vezes_nao_duplica(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $this->enableAutomation($agencyId, $seed['client']['id']);

        $this->approve($seed);
        // Segundo aprovo forçado direto no service (o portal já bloqueia pelo status).
        $service = (new \App\Core\Container())->get(\App\Services\ContentPlanService::class);
        $service->approvePlan($seed['plan'], $seed['client']['id']);

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM content_plans WHERE week_start = '2026-07-20'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Dedupe por plano: uma aprovação, um rascunho.');
    }

    public function test_semana_seguinte_ja_planejada_nao_ganha_duplicata(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $this->enableAutomation($agencyId, $seed['client']['id']);

        // Criação manual antecipada ("Planejar próxima semana").
        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Já planejado à mão', '2026-07-20', '2026-07-26', 'draft', NOW())"
        )->execute([':a' => $agencyId, ':c' => $seed['client']['id']]);

        $this->approve($seed);

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM content_plans WHERE week_start = '2026-07-20'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'A criação manual vence — a automação não duplica.');
    }

    public function test_automacao_desligada_nao_cria_nada(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        // Sem enableAutomation.

        $this->approve($seed);

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM content_plans WHERE week_start = '2026-07-20'"
        )->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_com_modelo_do_cliente_o_rascunho_usa_o_modelo(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $this->enableAutomation($agencyId, $seed['client']['id']);

        // Modelo: sexta (weekday 5), TikTok Story — diferente do plano aprovado.
        $this->pdo->prepare(
            "INSERT INTO content_plan_templates (agency_id, client_id, items, created_at, updated_at)
             VALUES (:a, :c, :items, NOW(), NOW())"
        )->execute([
            ':a'     => $agencyId,
            ':c'     => $seed['client']['id'],
            ':items' => json_encode([['weekday' => 5, 'publish_time' => '18:00', 'platform' => 'tiktok', 'content_type' => 'Story', 'assigned_to' => null, 'sort_order' => 0]]),
        ]);

        $this->approve($seed);

        $next = $this->pdo->query("SELECT id FROM content_plans WHERE week_start = '2026-07-20'")->fetch();
        $this->assertNotFalse($next);
        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE content_plan_id = {$next['id']}")->fetch();

        $this->assertSame('2026-07-24', $item['publish_date'], 'Weekday 5 do modelo = sexta da nova semana.');
        $this->assertSame('tiktok', $item['platform'], 'O modelo vence a estrutura do plano aprovado.');
    }
}
