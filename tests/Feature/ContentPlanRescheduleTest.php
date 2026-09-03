<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * CONT-REAG — reagendar um post para outra semana.
 *
 * O caso real: a cliente aprovou o post da semana e pediu para publicar na
 * seguinte. Antes, a única saída era recriar o post do zero no outro plano —
 * legenda, roteiro, capa, imagens, responsável, tudo na mão, e o histórico de
 * feedback ficava para trás.
 *
 * O que este teste protege: o post **migra** (mesma linha, todas as configs),
 * o plano da semana de destino nasce se não existir, e o sistema não passa a
 * mentir sobre o que a cliente aprovou.
 */
class ContentPlanRescheduleTest extends FeatureTestCase
{
    /** @return array{plan:int,client:int,item:int} */
    private function seedPlanWithItem(int $agencyId, string $status = 'sent'): array
    {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', :s, NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':s' => $status]);
        $planId = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, publish_time, platform, content_type,
                 title, caption, script, cta, cover_url, status, sort_order, created_at)
             VALUES (:p, :c, '2026-07-08', '10:30', 'instagram', 'Carrossel',
                 'Post da semana', 'Legenda aprovada', 'Roteiro', 'Clique no link',
                 'https://cdn.test/capa.jpg', 'approved', 3, NOW())
             RETURNING id"
        );
        $stmt->execute([':p' => $planId, ':c' => $client['id']]);
        $itemId = (int) $stmt->fetchColumn();

        return ['plan' => $planId, 'client' => (int) $client['id'], 'item' => $itemId];
    }

    private function reschedule(int $planId, int $itemId, array $data): \App\Core\Response
    {
        return $this->post("/conteudo/{$planId}/items/{$itemId}/reagendar", $data);
    }

    public function test_move_o_post_para_a_semana_seguinte_criando_o_plano(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->actingAs($user['id'], permissions: ['content.edit', 'content.view']);

        $response = $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-15']);
        $this->assertSame(302, $response->getStatus());

        $new = $this->pdo->query(
            "SELECT * FROM content_plans WHERE id <> {$seed['plan']} ORDER BY id DESC LIMIT 1"
        )->fetch();

        $this->assertNotFalse($new, 'O plano da semana de destino precisa nascer.');
        $this->assertSame('2026-07-13', $new['week_start']);
        $this->assertSame('2026-07-19', $new['week_end']);
        $this->assertSame('draft', $new['status'], 'O plano novo nasce em rascunho.');

        $item = $this->pdo->query(
            "SELECT * FROM content_plan_items WHERE id = {$seed['item']}"
        )->fetch();

        // Migrou de plano, não foi copiado.
        $this->assertSame((int) $new['id'], (int) $item['content_plan_id']);
        $this->assertSame('2026-07-15', $item['publish_date']);
        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM content_plan_items')->fetchColumn(),
            'Reagendar move o post — não deixa uma cópia para trás.'
        );

        // Todas as configs do post foram junto: é a mesma linha.
        $this->assertSame('instagram',        $item['platform']);
        $this->assertSame('Carrossel',        $item['content_type']);
        $this->assertSame('Legenda aprovada', $item['caption']);
        $this->assertSame('Roteiro',          $item['script']);
        $this->assertSame('Clique no link',   $item['cta']);
        $this->assertStringContainsString('10:30', (string) $item['publish_time'], 'Sem hora nova, a hora original fica.');
        $this->assertSame('approved', $item['status'], 'Adiar a data não desfaz a aprovação da cliente.');
    }

    public function test_reaproveita_o_plano_existente_da_semana_de_destino(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_at)
             VALUES (:a, :c, 'Semana 13/07', '2026-07-13', '2026-07-19', 'draft', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $seed['client']]);
        $targetId = (int) $stmt->fetchColumn();

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-16', 'publish_time' => '19:00']);

        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE id = {$seed['item']}")->fetch();
        $this->assertSame($targetId, (int) $item['content_plan_id'], 'Não pode criar plano duplicado da mesma semana.');
        $this->assertSame('2026-07-16', $item['publish_date']);
        $this->assertStringContainsString('19:00', (string) $item['publish_time']);

        $this->assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn()
        );
    }

    public function test_data_dentro_da_mesma_semana_so_troca_a_data(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-10']);

        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE id = {$seed['item']}")->fetch();
        $this->assertSame($seed['plan'], (int) $item['content_plan_id']);
        $this->assertSame('2026-07-10', $item['publish_date']);
        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn(),
            'Mesma semana não cria plano nenhum.'
        );
    }

    public function test_o_feedback_do_post_viaja_com_ele(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->pdo->prepare(
            "INSERT INTO content_feedbacks (content_plan_item_id, content_plan_id, client_id, user_id, feedback_type, comment, created_at)
             VALUES (:i, :p, :c, :u, 'approved', 'Pode publicar', NOW())"
        )->execute([':i' => $seed['item'], ':p' => $seed['plan'], ':c' => $seed['client'], ':u' => $user['id']]);

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-15']);

        $newPlanId = (int) $this->pdo->query(
            "SELECT id FROM content_plans WHERE id <> {$seed['plan']} ORDER BY id DESC LIMIT 1"
        )->fetchColumn();

        $feedbackPlan = (int) $this->pdo->query(
            "SELECT content_plan_id FROM content_feedbacks WHERE content_plan_item_id = {$seed['item']}"
        )->fetchColumn();

        $this->assertSame($newPlanId, $feedbackPlan, 'O histórico de aprovação não pode ficar na semana antiga.');
    }

    public function test_nao_move_para_semana_ja_aprovada_pela_cliente(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, approved_at, created_at)
             VALUES (:a, :c, 'Semana 13/07', '2026-07-13', '2026-07-19', 'approved', NOW(), NOW())"
        )->execute([':a' => $agencyId, ':c' => $seed['client']]);

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-15']);

        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE id = {$seed['item']}")->fetch();
        $this->assertSame($seed['plan'], (int) $item['content_plan_id'], 'Semana fechada com a cliente não recebe post novo por trás.');
        $this->assertSame('2026-07-08', $item['publish_date']);
    }

    public function test_data_invalida_nao_altera_nada(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => 'amanhã']);

        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE id = {$seed['item']}")->fetch();
        $this->assertSame('2026-07-08', $item['publish_date']);
    }

    public function test_sem_permissao_nao_reagenda(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlanWithItem($agencyId);

        $this->actingAs($user['id'], permissions: ['content.view']);

        $this->assertSame(403, $this->reschedule($seed['plan'], $seed['item'], ['publish_date' => '2026-07-15'])->getStatus());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn());
    }

    public function test_nao_reagenda_post_de_outra_agencia(): void
    {
        $agencyA = $this->createAgency('A');
        $agencyB = $this->createAgency('B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $seedB   = $this->seedPlanWithItem($agencyB);

        $this->actingAs($user['id'], permissions: ['content.edit']);
        $this->reschedule($seedB['plan'], $seedB['item'], ['publish_date' => '2026-07-15']);

        $item = $this->pdo->query("SELECT * FROM content_plan_items WHERE id = {$seedB['item']}")->fetch();
        $this->assertSame($seedB['plan'], (int) $item['content_plan_id']);
        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn(),
            'Não pode criar plano na agência alheia.'
        );
    }
}
