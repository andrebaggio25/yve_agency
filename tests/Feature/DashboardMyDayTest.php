<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * "Meu dia" (PROD-08): o dashboard lista o que exige ação — aprovação parada,
 * fatura vencendo/vencida, tarefa atrasada — respeitando a permissão de cada
 * módulo e o escopo de agência.
 */
class DashboardMyDayTest extends FeatureTestCase
{
    private const ALL_PERMS = ['dashboard.view', 'content.view', 'invoices.view', 'tasks.view', 'ads_metrics.view'];

    private function login(int $agencyId, array $permissions = self::ALL_PERMS): int
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: $permissions);

        return $user['id'];
    }

    /** Recorte da seção "Meu dia" — o resto do dashboard (ex.: Planos recentes) não conta. */
    private function myDaySection(string $body): string
    {
        $start = strpos($body, 'data-my-day');
        $end   = strpos($body, 'Stats', $start ?: 0);

        $this->assertNotFalse($start, 'Seção "Meu dia" não encontrada no dashboard.');

        return substr($body, $start, ($end !== false ? $end : strlen($body)) - $start);
    }

    private function seedStalledApproval(int $agencyId, int $clientId, string $title = 'Plano Parado'): void
    {
        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, :t, '2026-07-20', '2026-07-26', 'sent', NOW() - INTERVAL '5 days', NOW())"
        )->execute([':a' => $agencyId, ':c' => $clientId, ':t' => $title]);
    }

    private function seedOverdueInvoice(int $agencyId, int $clientId, string $number = 'FAT-0001'): void
    {
        $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, total, amount_paid, due_date, created_at)
             VALUES (:a, :c, :n, 'Mensalidade', 'sent', 1500.00, 0, CURRENT_DATE - 2, NOW())"
        )->execute([':a' => $agencyId, ':c' => $clientId, ':n' => $number]);
    }

    private function seedOverdueTask(int $agencyId, int $createdBy, string $title = 'Tarefa Estourada'): void
    {
        $this->pdo->prepare(
            "INSERT INTO tasks (agency_id, title, status, priority, due_date, created_by, created_at, updated_at)
             VALUES (:a, :t, 'todo', 'high', CURRENT_DATE - 3, :u, NOW(), NOW())"
        )->execute([':a' => $agencyId, ':t' => $title, ':u' => $createdBy]);
    }

    public function test_lista_aprovacao_parada_fatura_vencida_e_tarefa_atrasada(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Meu Dia');
        $userId   = $this->login($agencyId);
        $this->seedStalledApproval($agencyId, (int) $client['id']);
        $this->seedOverdueInvoice($agencyId, (int) $client['id']);
        $this->seedOverdueTask($agencyId, $userId);

        $section = $this->myDaySection($this->get('/dashboard')->getBody());

        $this->assertStringContainsString('Plano Parado', $section);
        $this->assertStringContainsString('parada há 5d', $section);
        $this->assertStringContainsString('FAT-0001', $section);
        $this->assertStringContainsString('vencida há 2d', $section);
        $this->assertStringContainsString('Tarefa Estourada', $section);
        $this->assertStringContainsString('atrasada 3d', $section);
    }

    public function test_sem_permissao_de_faturas_o_bloco_de_fatura_some(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $userId   = $this->login($agencyId, ['dashboard.view', 'tasks.view']);
        $this->seedOverdueInvoice($agencyId, (int) $client['id'], 'FAT-SIGILO');
        $this->seedOverdueTask($agencyId, $userId, 'Tarefa Visível');

        $section = $this->myDaySection($this->get('/dashboard')->getBody());

        $this->assertStringNotContainsString('FAT-SIGILO', $section);
        $this->assertStringContainsString('Tarefa Visível', $section);
    }

    public function test_pendencia_de_outra_agencia_nao_aparece(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clientB  = $this->createClient($agenciaB, 'Cliente da B');
        $userB    = $this->createUser($agenciaB, 'userb@test.com');
        $this->seedOverdueInvoice($agenciaB, (int) $clientB['id'], 'FAT-DA-B');
        $this->seedOverdueTask($agenciaB, $userB['id'], 'Tarefa da B');

        $this->login($agenciaA);
        $section = $this->myDaySection($this->get('/dashboard')->getBody());

        $this->assertStringNotContainsString('FAT-DA-B', $section);
        $this->assertStringNotContainsString('Tarefa da B', $section);
        $this->assertStringContainsString('Tudo em dia', $section);
    }

    public function test_sem_pendencias_mostra_tudo_em_dia(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $section = $this->myDaySection($this->get('/dashboard')->getBody());

        $this->assertStringContainsString('Tudo em dia', $section);
    }

    public function test_fatura_paga_e_plano_aprovado_nao_entram(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, total, amount_paid, due_date, created_at)
             VALUES (:a, :c, 'FAT-PAGA', 'Quitada', 'paid', 900.00, 900.00, CURRENT_DATE - 10, NOW())"
        )->execute([':a' => $agencyId, ':c' => $client['id']]);
        $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, 'Plano Aprovado', '2026-07-13', '2026-07-19', 'approved', NOW() - INTERVAL '10 days', NOW())"
        )->execute([':a' => $agencyId, ':c' => $client['id']]);

        $this->login($agencyId);
        $section = $this->myDaySection($this->get('/dashboard')->getBody());

        $this->assertStringNotContainsString('FAT-PAGA', $section);
        $this->assertStringNotContainsString('Plano Aprovado', $section);
        $this->assertStringContainsString('Tudo em dia', $section);
    }
}
