<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Automations\ApprovalReminder;
use App\Automations\ClientMonthlyReport;
use App\Automations\ContractExpiring;
use App\Automations\InvoiceDueReminder;
use App\Automations\TaskSlaOverdue;
use App\Core\Container;
use App\Services\NotificationService;

/**
 * BUG-07 — o canal marcado na regra da agência manda.
 *
 * Antes, `automation_rules.channels` só existia para desenhar o checkbox: o
 * canal real estava fixo no handler do evento, então desmarcar WhatsApp não
 * impedia nada — a agência achava que tinha desligado e a cliente continuava
 * recebendo. Aqui a fila é real (banco de teste); nada sai para a rede: o envio
 * de verdade é trabalho do `SendNotificationJob`, que não roda nestes testes.
 */
class AutomationChannelTest extends FeatureTestCase
{
    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @param list<string>|null $channels  null = coluna NULL (regra legada) */
    private function activateRule(int $agencyId, string $key, ?array $channels): void
    {
        $this->pdo->prepare(
            "INSERT INTO automation_rules (agency_id, automation_key, name, status, channels)
             VALUES (:a, :k, :k, 'active', :ch)"
        )->execute([
            ':a'  => $agencyId,
            ':k'  => $key,
            ':ch' => $channels === null ? null : json_encode($channels),
        ]);
    }

    private function optIn(int $agencyId, int $clientId, string $key): void
    {
        $this->pdo->prepare(
            "INSERT INTO client_automation_settings (agency_id, client_id, automation_key, enabled)
             VALUES (:a, :c, :k, TRUE)"
        )->execute([':a' => $agencyId, ':c' => $clientId, ':k' => $key]);
    }

    /** A agência mudou os canais da regra na tela. @param list<string> $channels */
    private function setChannels(int $agencyId, string $key, array $channels): void
    {
        $this->pdo->prepare(
            'UPDATE automation_rules SET channels = :ch WHERE agency_id = :a AND automation_key = :k'
        )->execute([':ch' => json_encode($channels), ':a' => $agencyId, ':k' => $key]);
    }

    /** Log de idempotência da automação. @return array<int,array<string,mixed>> */
    private function automationLog(string $key): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_log WHERE automation_key = :k ORDER BY id');
        $stmt->execute([':k' => $key]);

        return $stmt->fetchAll();
    }

    /** @return array{id:int,portal_token:string} */
    private function clientWithWhatsApp(int $agencyId, bool $portal = true): array
    {
        $client = $this->createClient($agencyId, 'Studio Aline', $portal);
        $this->pdo->prepare("UPDATE clients SET whatsapp = '5511999990001', language = 'pt' WHERE id = :id")
            ->execute([':id' => $client['id']]);

        return $client;
    }

    private function seedInvoiceDueInThreeDays(int $agencyId, int $clientId): void
    {
        $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, due_date,
                                   subtotal, total, amount_paid, currency_code, created_at)
             VALUES (:a, :c, 'FAT-001', 'Mensalidade', 'sent', CURRENT_DATE + 3,
                     1000.00, 1000.00, 0, 'BRL', NOW())"
        )->execute([':a' => $agencyId, ':c' => $clientId]);
    }

    /** @return array<int,array<string,mixed>> */
    private function deliveries(): array
    {
        return $this->pdo->query('SELECT * FROM notification_jobs ORDER BY id')->fetchAll();
    }

    private function queuedJobs(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM jobs WHERE queue = 'notifications'")->fetchColumn();
    }

    private function runAutomation(string $handler, int $agencyId): array
    {
        return Container::getInstance()->make($handler)->run($agencyId, []);
    }

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    public function test_canal_marcado_entrega_o_lembrete_de_fatura(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('whatsapp', $deliveries[0]['channel']);
        $this->assertSame('pending', $deliveries[0]['status']);
        $this->assertSame(1, $this->queuedJobs(), 'Entrega ativa tem de ir para a fila.');
    }

    public function test_canal_desmarcado_nao_entrega_e_registra_o_motivo(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        // A agência desmarcou o WhatsApp e deixou só o e-mail.
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['email']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'O registro fica — o que não existe é a entrega.');
        $this->assertSame('skipped', $deliveries[0]['status']);
        $this->assertSame('channel_off:whatsapp', $deliveries[0]['last_error']);
        $this->assertSame(0, $this->queuedJobs(), 'Canal desmarcado não gera job de envio.');
    }

    /**
     * Entrega barrada não é entrega: se ela queimar a idempotência, religar o
     * canal nunca reenvia — o `dedupe_key` já estaria gravado como "done" e a
     * agência acharia que o ajuste resolveu.
     *
     * As duas rodadas aqui são do MESMO dia (é o cenário em que religar o canal
     * ainda recupera a entrega). O que acontece na rodada do dia seguinte está
     * no teste logo abaixo.
     */
    public function test_entrega_barrada_nao_queima_a_idempotencia(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['email']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $this->assertSame(
            [],
            $this->automationLog('billing.invoice_due_reminder'),
            'Nada saiu: o log de idempotência não pode registrar a entrega como feita.',
        );

        // A agência percebeu e marcou o WhatsApp na tela.
        $this->setChannels($agencyId, 'billing.invoice_due_reminder', ['whatsapp', 'email']);
        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(2, $deliveries, 'Religar o canal tem de voltar a entregar o que foi barrado.');
        $this->assertSame('pending', $deliveries[1]['status']);
        $this->assertSame(1, $this->queuedJobs());
        $this->assertCount(1, $this->automationLog('billing.invoice_due_reminder'));
    }

    /**
     * O limite do que a correção acima entrega — e que o plano não pode
     * prometer a mais: o `dedupe_key` fica livre, mas cada automação tem a sua
     * janela de consulta. Religar o canal recupera a entrega barrada **só
     * enquanto a janela ainda devolve a linha**; para o lembrete D-3 isso é o
     * mesmo dia. Na rodada do dia seguinte a fatura já não vence em 3 dias e
     * aquele lembrete não sai mais (a cliente só é avisada quando vencer, pela
     * `billing.invoice_overdue`).
     */
    public function test_religar_o_canal_no_dia_seguinte_nao_recupera_o_lembrete(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['email']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        // A rodada de amanhã: a mesma fatura vence agora em 2 dias.
        $this->pdo->prepare('UPDATE invoices SET due_date = CURRENT_DATE + 2 WHERE client_id = :c')
            ->execute([':c' => $client['id']]);
        $this->setChannels($agencyId, 'billing.invoice_due_reminder', ['whatsapp', 'email']);

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'Fora da janela D-3 o handler não devolve mais a fatura.');
        $this->assertSame('skipped', $deliveries[0]['status']);
        $this->assertSame(0, $this->queuedJobs());
    }

    /** A entrega que de fato saiu continua trancada pela idempotência. */
    public function test_entrega_feita_nao_repete_na_rodada_seguinte(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['whatsapp']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);
        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $this->assertCount(1, $this->deliveries(), 'O lembrete não pode sair duas vezes.');
        $this->assertSame(1, $this->queuedJobs());
    }

    /** O suporte precisa ler o motivo na tela, não no banco. */
    public function test_entrega_barrada_aparece_na_timeline_com_o_motivo(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', ['email']);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');
        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $user = $this->createUser($agencyId, 'suporte@test.com');
        $this->actingAs($user['id'], permissions: ['automations.view']);
        $response = $this->get('/automations/deliveries');

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString(t('automations.delivery_skipped'), $response->getBody());
        $this->assertStringContainsString(t('automations.delivery_channel_off'), $response->getBody());
    }

    public function test_nenhum_canal_marcado_nao_entrega(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', []);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $this->assertSame('skipped', $this->deliveries()[0]['status']);
        $this->assertSame(0, $this->queuedJobs());
    }

    /** Regra antiga (channels NULL) nunca foi configurada: herda o catálogo. */
    public function test_regra_sem_canais_salvos_segue_o_catalogo(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->seedInvoiceDueInThreeDays($agencyId, $client['id']);
        $this->activateRule($agencyId, 'billing.invoice_due_reminder', null);
        $this->optIn($agencyId, $client['id'], 'billing.invoice_due_reminder');

        $this->runAutomation(InvoiceDueReminder::class, $agencyId);

        $this->assertSame('pending', $this->deliveries()[0]['status']);
        $this->assertSame(1, $this->queuedJobs());
    }

    /**
     * O aviso que não é automação do catálogo (plano enviado para aprovação)
     * não pode ser engolido pelo gate: não existe regra para ele.
     */
    public function test_aviso_que_nao_e_automacao_continua_entregando(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $row      = $this->pdo->query("SELECT * FROM clients WHERE id = {$client['id']}")->fetch();

        Container::getInstance()->make(NotificationService::class)->notifyEvent('plan.sent_for_approval', $agencyId, [
            'client_id'    => $client['id'],
            'client'       => $row,
            'plan_id'      => 1,
            'plan_title'   => 'Semana 02/11',
            'approval_url' => 'https://exemplo.test/portal/x/planos/1',
        ]);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('pending', $deliveries[0]['status']);
    }

    // ── Canal "no sistema" ───────────────────────────────────────────────────

    /**
     * O passo da automação é contado em PHP e a data é filtrada no Postgres
     * (que roda em UTC): a data vem do PHP para os dois lados concordarem — é a
     * mesma armadilha que fazia o PortalWeekTest falhar depois das 21h.
     */
    private function seedContractExpiringSoon(int $agencyId, int $clientId): void
    {
        $this->pdo->prepare(
            "INSERT INTO contracts (agency_id, client_id, title, value, currency_code, status,
                                    start_date, end_date, created_at, updated_at)
             VALUES (:a, :c, 'Contrato Anual', 1000.00, 'BRL', 'active', :start, :end, NOW(), NOW())"
        )->execute([
            ':a'     => $agencyId,
            ':c'     => $clientId,
            ':start' => date('Y-m-d', strtotime('-300 days')),
            ':end'   => date('Y-m-d', strtotime('+15 days')), // passo 15 do catálogo
        ]);
    }

    /** Vincula o usuário a um papel padrão que tem a permissão pedida. */
    private function giveRoleWithPermission(int $userId, string $permission): void
    {
        $roleId = (int) $this->pdo->query(
            "SELECT r.id FROM roles r
             JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = '{$permission}' AND r.agency_id IS NULL
             ORDER BY r.id LIMIT 1"
        )->fetchColumn();

        $this->assertGreaterThan(0, $roleId, "Nenhum papel padrão com {$permission} no banco de teste.");
        $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:u, :r, NOW())')
            ->execute([':u' => $userId, ':r' => $roleId]);
    }

    private function inAppCount(?string $type = null): int
    {
        if ($type === null) {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE type = :t');
        $stmt->execute([':t' => $type]);

        return (int) $stmt->fetchColumn();
    }

    public function test_inapp_marcado_avisa_a_equipe_do_contrato_expirando(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId, 'gestor@test.com');
        $this->giveRoleWithPermission($user['id'], 'contracts.view');
        $this->seedContractExpiringSoon($agencyId, $client['id']);
        $this->activateRule($agencyId, 'contract.expiring', ['inapp']);

        $this->runAutomation(ContractExpiring::class, $agencyId);

        $this->assertSame(1, $this->inAppCount());
    }

    public function test_inapp_desmarcado_nao_cria_aviso_no_sistema(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId, 'gestor@test.com');
        $this->giveRoleWithPermission($user['id'], 'contracts.view');
        $this->seedContractExpiringSoon($agencyId, $client['id']);
        $this->activateRule($agencyId, 'contract.expiring', []);

        $this->runAutomation(ContractExpiring::class, $agencyId);

        $this->assertSame(0, $this->inAppCount(), 'Canal "no sistema" desmarcado não cria notificação.');
    }

    /** Data vinda do PHP, pelo mesmo motivo do contrato acima. */
    private function seedOverdueTask(int $agencyId, int $userId): void
    {
        $this->pdo->prepare(
            "INSERT INTO tasks (agency_id, title, status, priority, assigned_to, created_by, due_date, created_at, updated_at)
             VALUES (:a, 'Editar o reels', 'todo', 'medium', :u, :u, :due, NOW(), NOW())"
        )->execute([
            ':a'   => $agencyId,
            ':u'   => $userId,
            ':due' => date('Y-m-d', strtotime('-3 days')), // passo 3 do catálogo
        ]);
    }

    public function test_inapp_marcado_avisa_o_responsavel_da_tarefa_atrasada(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->seedOverdueTask($agencyId, $user['id']);
        $this->activateRule($agencyId, 'task.sla_overdue', ['inapp']);

        $this->runAutomation(TaskSlaOverdue::class, $agencyId);

        $this->assertSame(1, $this->inAppCount());
    }

    public function test_inapp_desmarcado_nao_avisa_o_responsavel_da_tarefa(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->seedOverdueTask($agencyId, $user['id']);
        $this->activateRule($agencyId, 'task.sla_overdue', []);

        $this->runAutomation(TaskSlaOverdue::class, $agencyId);

        $this->assertSame(0, $this->inAppCount());
    }

    // ── Rascunho da semana seguinte (automação por evento) ───────────────────

    /** Plano na mesa da cliente, pronto para ela aprovar no portal. */
    private function seedPlanAwaitingClient(int $agencyId, int $clientId, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans
                (agency_id, client_id, title, week_start, week_end, status, sent_at, created_by, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', 'sent', NOW(), :u, NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':u' => $createdBy]);
        $planId = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, platform, content_type, title, status, sort_order, created_at)
             VALUES (:p, :c, '2026-07-08', 'instagram', 'Feed Estático', 'Post de quarta', 'draft', 0, NOW())"
        )->execute([':p' => $planId, ':c' => $clientId]);

        return $planId;
    }

    private function plansInWeek(int $clientId, string $weekStart): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM content_plans WHERE client_id = :c AND week_start = :w');
        $stmt->execute([':c' => $clientId, ':w' => $weekStart]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{client:array{id:int,portal_token:string},plan:int} */
    private function seedAutoNextPlan(int $agencyId, array $channels): array
    {
        $client = $this->createClient($agencyId, 'Studio Aline');
        $user   = $this->createUser($agencyId, 'social@test.com');
        $this->giveRoleWithPermission($user['id'], 'content.view');
        $planId = $this->seedPlanAwaitingClient($agencyId, $client['id'], $user['id']);
        $this->activateRule($agencyId, 'content.approved_create_next_plan', $channels);
        $this->optIn($agencyId, $client['id'], 'content.approved_create_next_plan');

        return ['client' => $client, 'plan' => $planId];
    }

    public function test_inapp_marcado_avisa_a_equipe_do_rascunho_da_semana_seguinte(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedAutoNextPlan($agencyId, ['inapp']);

        $this->post("/portal/{$seed['client']['portal_token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(1, $this->plansInWeek($seed['client']['id'], '2026-07-13'));
        $this->assertSame(1, $this->inAppCount('plan.auto_created'));
    }

    /**
     * O checkbox "No sistema" desta automação precisa valer: ela entrega por um
     * evento de nome próprio (`plan.auto_created`), e era o único caminho que
     * continuava avisando com o canal desmarcado.
     */
    public function test_inapp_desmarcado_nao_avisa_do_rascunho_criado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedAutoNextPlan($agencyId, []);

        $this->post("/portal/{$seed['client']['portal_token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(
            1,
            $this->plansInWeek($seed['client']['id'], '2026-07-13'),
            'O canal barra o aviso, não a automação: o rascunho continua nascendo.',
        );
        $this->assertSame(0, $this->inAppCount('plan.auto_created'), 'Canal "no sistema" desmarcado não cria notificação.');
    }

    // ── Relatório mensal: link que a cliente consegue abrir ──────────────────

    public function test_relatorio_mensal_manda_o_link_do_portal_e_nao_a_rota_interna(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->activateRule($agencyId, 'report.client_monthly', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'report.client_monthly');

        $this->runAutomation(ClientMonthlyReport::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('pending', $deliveries[0]['status']);

        $payload = json_decode((string) $deliveries[0]['payload'], true);
        $this->assertStringContainsString("/portal/{$client['portal_token']}", $payload['report_url']);
        $this->assertStringNotContainsString(
            'executive-report',
            $payload['report_url'],
            'A rota interna exige login: a cliente cai na tela de login.'
        );
    }

    public function test_relatorio_mensal_nao_sai_sem_portal_e_diz_por_que(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId, portal: false);
        $this->activateRule($agencyId, 'report.client_monthly', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'report.client_monthly');

        $stats = $this->runAutomation(ClientMonthlyReport::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'Não entregar é certo; sumir calado não — o suporte precisa da linha.');
        $this->assertSame('skipped', $deliveries[0]['status'], 'Mensagem que é só um link não vale nada sem o link.');
        $this->assertSame('portal_off', $deliveries[0]['last_error']);
        $this->assertSame(0, $this->queuedJobs());
        $this->assertSame(1, $stats['skipped']);
    }

    /**
     * O estado mais comum: cliente cadastrado normalmente nasce com
     * `portal_enabled = TRUE` e **sem** `portal_token` (só a automação de
     * onboarding ou "Gerar link do portal" criam o token).
     */
    public function test_relatorio_mensal_nao_sai_sem_token_do_portal_e_diz_por_que(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->pdo->prepare('UPDATE clients SET portal_token = NULL WHERE id = :id')->execute([':id' => $client['id']]);
        $this->activateRule($agencyId, 'report.client_monthly', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'report.client_monthly');

        $this->runAutomation(ClientMonthlyReport::class, $agencyId);

        $this->assertSame('portal_off', $this->deliveries()[0]['last_error']);
        $this->assertSame(
            [],
            $this->automationLog('report.client_monthly'),
            'Gerado o link do portal, o relatório tem de poder sair.',
        );
    }

    /** O motivo tem de ser o da linha: motivo errado é pior do que motivo nenhum. */
    public function test_timeline_mostra_o_motivo_do_portal_e_nao_o_do_canal(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId, portal: false);
        $this->activateRule($agencyId, 'report.client_monthly', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'report.client_monthly');
        $this->runAutomation(ClientMonthlyReport::class, $agencyId);

        $user = $this->createUser($agencyId, 'suporte@test.com');
        $this->actingAs($user['id'], permissions: ['automations.view']);
        $body = $this->get('/automations/deliveries')->getBody();

        $this->assertStringContainsString(t('automations.delivery_portal_off'), $body);
        $this->assertStringNotContainsString(t('automations.delivery_channel_off'), $body);
    }

    public function test_canal_desmarcado_tambem_barra_o_relatorio_mensal(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->activateRule($agencyId, 'report.client_monthly', ['email']);
        $this->optIn($agencyId, $client['id'], 'report.client_monthly');

        $this->runAutomation(ClientMonthlyReport::class, $agencyId);

        $this->assertSame('skipped', $this->deliveries()[0]['status']);
        $this->assertSame(0, $this->queuedJobs());
    }

    // ── Cobrança de aprovação: a mensagem é só o link ────────────────────────

    /**
     * Plano na mesa da cliente há N dias. A data vem do PHP (e não de
     * `NOW() - INTERVAL`) porque o passo d2/d4/d7 é contado em PHP e o Postgres
     * de teste roda em UTC: com a data do banco, "há 2 dias" viraria 1 dia e
     * meio e o passo não casaria.
     */
    private function seedPlanSentDaysAgo(int $agencyId, int $clientId, int $days): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans
                (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', 'sent', :sent, NOW())
             RETURNING id"
        );
        $stmt->execute([
            ':a'    => $agencyId,
            ':c'    => $clientId,
            ':sent' => date('Y-m-d H:i:s', strtotime("-{$days} days")),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function test_cobranca_de_aprovacao_manda_o_link_do_portal(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $planId   = $this->seedPlanSentDaysAgo($agencyId, $client['id'], 2);
        $this->activateRule($agencyId, 'content.approval_reminder', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'content.approval_reminder');

        $this->runAutomation(ApprovalReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('pending', $deliveries[0]['status']);
        $this->assertSame(1, $this->queuedJobs());

        $payload = json_decode((string) $deliveries[0]['payload'], true);
        $this->assertSame(
            "/portal/{$client['portal_token']}/planos/{$planId}",
            parse_url((string) $payload['approval_url'], PHP_URL_PATH),
        );
    }

    /**
     * O estado mais comum (o mesmo do relatório mensal): cliente cadastrada
     * com o onboarding desligado nasce com `portal_enabled = TRUE` e **sem**
     * token. A cobrança é só o link — sem link ela chega como "🔗 " e nada.
     */
    public function test_cobranca_de_aprovacao_nao_sai_sem_token_do_portal_e_diz_por_que(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId);
        $this->pdo->prepare('UPDATE clients SET portal_token = NULL WHERE id = :id')->execute([':id' => $client['id']]);
        $this->seedPlanSentDaysAgo($agencyId, $client['id'], 2);
        $this->activateRule($agencyId, 'content.approval_reminder', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'content.approval_reminder');

        $stats = $this->runAutomation(ApprovalReminder::class, $agencyId);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries, 'Não entregar é certo; sumir calado não — o suporte precisa da linha.');
        $this->assertSame('skipped', $deliveries[0]['status'], 'Mensagem que é só um link não vale nada sem o link.');
        $this->assertSame('portal_off', $deliveries[0]['last_error']);
        $this->assertSame(0, $this->queuedJobs());
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(
            [],
            $this->automationLog('content.approval_reminder'),
            'O passo não pode ser consumido por uma mensagem que não saiu.',
        );
    }

    /** Portal desligado com token: o link existe, mas responde 403 à cliente. */
    public function test_cobranca_de_aprovacao_nao_sai_com_o_portal_desligado(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->clientWithWhatsApp($agencyId, portal: false);
        $this->seedPlanSentDaysAgo($agencyId, $client['id'], 4);
        $this->activateRule($agencyId, 'content.approval_reminder', ['whatsapp', 'email']);
        $this->optIn($agencyId, $client['id'], 'content.approval_reminder');

        $this->runAutomation(ApprovalReminder::class, $agencyId);

        $this->assertSame('portal_off', $this->deliveries()[0]['last_error']);
        $this->assertSame(0, $this->queuedJobs());
    }

    // ── A tela: o que foi salvo é o que volta ────────────────────────────────

    private function saveRule(array $post): void
    {
        $this->post('/automations/billing.invoice_due_reminder', $post + ['_method' => 'PUT', 'status' => 'active']);
    }

    /**
     * O trecho do formulário DESTA automação. A tela lista todas, e várias
     * nascem com WhatsApp marcado — procurar no corpo inteiro não prova nada.
     */
    private function ruleCard(string $body, string $key): string
    {
        $start = strpos($body, '/automations/' . $key . '"');
        $this->assertNotFalse($start, "Cartão da automação {$key} não está na tela.");
        $end = strpos($body, '</form>', (int) $start);

        return substr($body, (int) $start, (int) $end - (int) $start);
    }

    private function savedChannels(int $agencyId): ?array
    {
        $raw = $this->pdo->query(
            "SELECT channels FROM automation_rules WHERE agency_id = {$agencyId} AND automation_key = 'billing.invoice_due_reminder'"
        )->fetchColumn();

        return $raw === null || $raw === false ? null : json_decode((string) $raw, true);
    }

    public function test_desmarcar_todos_os_canais_sobrevive_a_releitura(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['automations.view', 'automations.edit']);

        $this->saveRule([]); // nenhum checkbox marcado

        $this->assertSame([], $this->savedChannels($agencyId));

        $card = $this->ruleCard($this->get('/automations')->getBody(), 'billing.invoice_due_reminder');
        $this->assertStringContainsString('value="whatsapp"', $card);
        $this->assertStringNotContainsString(
            'value="whatsapp" checked',
            $card,
            'A tela remarcava sozinha o canal que a agência desmarcou.'
        );
    }

    public function test_canal_marcado_volta_marcado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['automations.view', 'automations.edit']);

        $this->saveRule(['channels' => ['whatsapp']]);

        $this->assertSame(['whatsapp'], $this->savedChannels($agencyId));

        $card = $this->ruleCard($this->get('/automations')->getBody(), 'billing.invoice_due_reminder');
        $this->assertStringContainsString('value="whatsapp" checked', $card);
        $this->assertStringNotContainsString('value="email" checked', $card, 'O e-mail ficou desmarcado no POST.');
    }

    /** Canal inventado num POST cru desligaria tudo, calado. */
    public function test_canal_fora_do_catalogo_e_descartado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['automations.view', 'automations.edit']);

        $this->saveRule(['channels' => ['whatsapp', 'sms']]);

        $this->assertSame(['whatsapp'], $this->savedChannels($agencyId));
    }
}
