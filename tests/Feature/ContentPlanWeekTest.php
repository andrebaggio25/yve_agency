<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Fluxo semanal (seg–dom) de ponta a ponta: criação ancora a semana na
 * segunda-feira, o título nasce sozinho e nenhum post escapa da semana do
 * plano — mas mais de um post no mesmo dia é bem-vindo.
 */
class ContentPlanWeekTest extends FeatureTestCase
{
    /** @return array{user:int,client:int} */
    private function seed(int $agencyId): array
    {
        $user   = $this->createUser($agencyId);
        $client = $this->createClient($agencyId, 'Studio Aline');
        // clients.view_all: sem ele, o usuário só cria plano para clientes com
        // acesso explícito (client_user_access) — irrelevante para estes testes.
        $this->actingAs($user['id'], permissions: ['content.view', 'content.create', 'content.edit', 'clients.view_all']);

        return ['user' => $user['id'], 'client' => (int) $client['id']];
    }

    private function lastPlan(): array
    {
        return $this->pdo->query('SELECT * FROM content_plans ORDER BY id DESC LIMIT 1')->fetch() ?: [];
    }

    public function test_criar_plano_numa_quarta_ancora_a_semana_na_segunda(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        // 2026-07-15 é quarta-feira.
        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-15']);

        $plan = $this->lastPlan();
        $this->assertSame('2026-07-13', $plan['week_start'], 'A semana tem de encaixar na segunda-feira.');
        $this->assertSame('2026-07-19', $plan['week_end'], 'O domingo é derivado, nunca editado.');
    }

    public function test_titulo_vazio_gera_nome_automatico_com_cliente_e_periodo(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-13']);

        $this->assertSame('STUDIO ALINE | 13/07 – 19/07', $this->lastPlan()['title']);
    }

    public function test_titulo_digitado_pelo_usuario_e_respeitado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        $this->post('/conteudo', [
            'client_id'  => $seed['client'],
            'week_start' => '2026-07-13',
            'title'      => 'Campanha Dia dos Pais',
        ]);

        $this->assertSame('Campanha Dia dos Pais', $this->lastPlan()['title']);
    }

    public function test_post_fora_da_semana_do_plano_e_rejeitado_com_422(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-13']);
        $planId = (int) $this->lastPlan()['id'];

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $response = $this->post("/conteudo/{$planId}/items", [
            'publish_date' => '2026-07-25', // sábado da OUTRA semana
            'platform'     => 'instagram',
            'content_type' => 'Feed Estático',
        ]);
        unset($_SERVER['HTTP_ACCEPT']);

        $this->assertSame(422, $response->getStatus());
        $this->assertStringContainsString('semana do plano', $response->getBody());
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM content_plan_items')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_dois_posts_no_mesmo_dia_sao_aceitos(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-13']);
        $planId = (int) $this->lastPlan()['id'];

        foreach (['09:00', '18:30'] as $time) {
            $this->post("/conteudo/{$planId}/items", [
                'publish_date' => '2026-07-15',
                'publish_time' => $time,
                'platform'     => 'instagram',
                'content_type' => 'Story',
            ]);
        }

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM content_plan_items WHERE publish_date = '2026-07-15'"
        )->fetchColumn();
        $this->assertSame(2, $count, 'Mais de um post no mesmo dia faz parte da rotina.');
    }

    public function test_editar_data_do_post_tambem_valida_a_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seed($agencyId);

        $this->post('/conteudo', ['client_id' => $seed['client'], 'week_start' => '2026-07-13']);
        $planId = (int) $this->lastPlan()['id'];

        $this->post("/conteudo/{$planId}/items", [
            'publish_date' => '2026-07-14',
            'platform'     => 'instagram',
            'content_type' => 'Feed Estático',
        ]);
        $itemId = (int) $this->pdo->query('SELECT id FROM content_plan_items ORDER BY id DESC LIMIT 1')->fetchColumn();

        $response = $this->post("/conteudo/{$planId}/items/{$itemId}", [
            '_method'      => 'PUT',
            'publish_date' => '2026-08-01',
        ]);

        $this->assertSame(422, $response->getStatus());
        $date = $this->pdo->query("SELECT publish_date FROM content_plan_items WHERE id = {$itemId}")->fetchColumn();
        $this->assertSame('2026-07-14', $date, 'A data original não pode ser tocada quando a nova é inválida.');
    }
}
