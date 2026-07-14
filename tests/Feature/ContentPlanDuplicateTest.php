<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * PROD-05 — duplicar plano de conteúdo.
 *
 * Montar o plano do mês seguinte era refazer tudo do zero: o trabalho mais
 * repetitivo da rotina de uma social media.
 *
 * O cuidado que importa: a cópia **não pode herdar o histórico** do original.
 * Um plano duplicado nascendo como "aprovado" seria dizer que a cliente aprovou
 * um conteúdo que ela nunca viu.
 */
class ContentPlanDuplicateTest extends FeatureTestCase
{
    /** @return array{plan:int,client:int} */
    private function seedApprovedPlan(int $agencyId, ?int $createdBy = null): array
    {
        $client = $this->createClient($agencyId);

        // created_by NULL de propósito num dos casos: o plano tem de continuar
        // acessível mesmo se quem o criou saiu da equipe (não há FK).
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, approved_at, sent_at, created_by, created_at)
             VALUES (:a, :c, 'Plano de Julho', '2026-07-06', '2026-07-12', 'approved', NOW(), NOW(), :u, NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':u' => $createdBy]);
        $planId = (int) $stmt->fetchColumn();

        // Dois itens em dias diferentes da semana (segunda e quarta).
        foreach ([['2026-07-06', 'Post A'], ['2026-07-08', 'Post B']] as $i => [$date, $title]) {
            $this->pdo->prepare(
                "INSERT INTO content_plan_items
                    (content_plan_id, client_id, publish_date, platform, content_type, title, caption, status, sort_order, created_at)
                 VALUES (:p, :c, :d, 'instagram', 'feed', :t, 'Legenda original', 'approved', :o, NOW())"
            )->execute([':p' => $planId, ':c' => $client['id'], ':d' => $date, ':t' => $title, ':o' => $i]);
        }

        return ['plan' => $planId, 'client' => (int) $client['id']];
    }

    public function test_duplicar_copia_os_itens_e_nasce_como_rascunho(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedApprovedPlan($agencyId);

        $this->actingAs($user['id'], permissions: ['content.create', 'content.view']);

        $response = $this->post('/conteudo/' . $seed['plan'] . '/duplicar');
        $this->assertSame(302, $response->getStatus());

        $new = $this->pdo->query(
            "SELECT * FROM content_plans WHERE id <> {$seed['plan']} ORDER BY id DESC LIMIT 1"
        )->fetch();

        $this->assertNotFalse($new, 'A cópia precisa existir.');

        // Nunca herda a aprovação da cliente.
        $this->assertSame('draft', $new['status']);
        $this->assertNull($new['approved_at'], 'A cópia não pode nascer aprovada — a cliente nunca viu este plano.');
        $this->assertNull($new['sent_at']);
        // O nome nasce do NOVO período (seg–dom da semana seguinte), não do
        // título antigo com "(cópia)" — a semana é a identidade do plano.
        $this->assertStringContainsString('13/07 – 19/07', $new['title']);

        // Os itens vêm juntos, também em rascunho.
        $items = $this->pdo->query(
            "SELECT * FROM content_plan_items WHERE content_plan_id = {$new['id']} ORDER BY sort_order"
        )->fetchAll();

        $this->assertCount(2, $items);
        $this->assertSame('draft', $items[0]['status'], 'Item copiado não pode vir aprovado.');

        // A ESTRUTURA vem junto (quando, onde, que formato).
        $this->assertSame('instagram', $items[0]['platform']);
        $this->assertSame('feed', $items[0]['content_type']);

        // O POST não vem: cada mês tem conteúdo próprio. Herdar a legenda do mês
        // anterior criaria material errado esperando para ser publicado por engano.
        $this->assertNull($items[0]['caption'], 'A legenda do post anterior não pode ser copiada.');
        $this->assertNull($items[0]['title'], 'O título do post anterior não pode ser copiado.');
    }

    /** As datas deslocam para a semana seguinte preservando os dias da semana. */
    public function test_datas_deslocam_mantendo_a_distribuicao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedApprovedPlan($agencyId);

        $this->actingAs($user['id'], permissions: ['content.create']);
        $this->post('/conteudo/' . $seed['plan'] . '/duplicar');

        $new = $this->pdo->query("SELECT * FROM content_plans WHERE id <> {$seed['plan']} ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('2026-07-13', $new['week_start'], 'Padrão: semana seguinte.');

        $dates = $this->pdo->query(
            "SELECT publish_date FROM content_plan_items WHERE content_plan_id = {$new['id']} ORDER BY publish_date"
        )->fetchAll(\PDO::FETCH_COLUMN);

        // Original: 06/07 (seg) e 08/07 (qua) → cópia: 13/07 (seg) e 15/07 (qua).
        $this->assertSame(['2026-07-13', '2026-07-15'], $dates);
    }

    public function test_sem_permissao_nao_duplica(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedApprovedPlan($agencyId);

        $this->actingAs($user['id'], permissions: ['content.view']);

        $this->assertSame(403, $this->post('/conteudo/' . $seed['plan'] . '/duplicar')->getStatus());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn());
    }

    public function test_nao_duplica_plano_de_outra_agencia(): void
    {
        $agencyA = $this->createAgency('A');
        $agencyB = $this->createAgency('B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $seedB   = $this->seedApprovedPlan($agencyB);

        $this->actingAs($user['id'], permissions: ['content.create']);
        $this->post('/conteudo/' . $seedB['plan'] . '/duplicar');

        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn(),
            'Não pode copiar plano de outra agência.'
        );
    }
}
