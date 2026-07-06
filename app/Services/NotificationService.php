<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $repo,
        private readonly EvolutionApiService    $whatsapp,
        private readonly EmailService           $email,
        private readonly UserRepository         $users,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Notify all agency users who have the given permission,
     * plus optionally a client via WhatsApp/email.
     */
    public function notifyEvent(string $event, int $agencyId, array $context = []): void
    {
        match ($event) {
            'plan.sent_for_approval'  => $this->onPlanSent($agencyId, $context),
            'plan.approved'           => $this->onPlanApproved($agencyId, $context),
            'plan.revision_requested' => $this->onPlanRevision($agencyId, $context),
            'item.feedback_added'     => $this->onItemFeedback($agencyId, $context),
            'task.assigned'           => $this->onTaskAssigned($agencyId, $context),
            // Automações
            'billing.invoice_due_reminder' => $this->onInvoiceDue($agencyId, $context),
            'billing.invoice_overdue'      => $this->onInvoiceOverdue($agencyId, $context),
            'content.approval_reminder'    => $this->onApprovalReminder($agencyId, $context),
            'content.approval_escalation'  => $this->onApprovalEscalation($agencyId, $context),
            'contract.expiring'            => $this->onContractExpiring($agencyId, $context),
            'task.sla_overdue'             => $this->onTaskSlaOverdue($agencyId, $context),
            'report.client_monthly'        => $this->onClientMonthlyReport($agencyId, $context),
            'digest.team_daily'            => $this->onTeamDailyDigest($agencyId, $context),
            'client.onboarding'            => $this->onClientOnboarding($agencyId, $context),
            default                        => null,
        };
    }

    /**
     * Process pending jobs from the queue (called by cron endpoint).
     * Returns count of jobs processed.
     */
    public function processQueue(int $limit = 10): int
    {
        $jobs = $this->repo->pendingJobs($limit);
        $processed = 0;

        foreach ($jobs as $job) {
            $payload = json_decode($job['payload'], true) ?? [];
            $result  = match ($job['channel']) {
                'whatsapp' => $this->whatsapp->sendText(
                    (int) $job['agency_id'],
                    $job['recipient'],
                    $this->renderWhatsAppMessage($job['template'], $payload, $job['locale'])
                ),
                'email' => $this->email->send(
                    $job['recipient'],
                    $payload['to_name'] ?? '',
                    $job['template'],
                    $payload,
                    $job['locale']
                ),
                default => ['success' => false, 'error' => 'Unknown channel'],
            };

            if ($result['success']) {
                $this->repo->markJobSent((int) $job['id']);
            } else {
                $this->repo->markJobFailed((int) $job['id'], $result['error'] ?? 'Unknown error');
            }
            $processed++;
        }

        return $processed;
    }

    // ── In-app ────────────────────────────────────────────────────────────────

    public function createInApp(int $agencyId, int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null): void
    {
        $this->repo->createNotification([
            'agency_id'  => $agencyId,
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public function unreadCount(int $userId, int $agencyId): int
    {
        return $this->repo->unreadCount($userId, $agencyId);
    }

    public function unreadList(int $userId, int $agencyId): array
    {
        return $this->repo->unreadForUser($userId, $agencyId);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->repo->markRead($notificationId, $userId);
    }

    public function markAllRead(int $userId, int $agencyId): void
    {
        $this->repo->markAllRead($userId, $agencyId);
    }

    // ── Event handlers ────────────────────────────────────────────────────────

    private function onPlanSent(int $agencyId, array $ctx): void
    {
        $clientId  = $ctx['client_id'] ?? null;
        $planId    = $ctx['plan_id'] ?? null;
        $planTitle = $ctx['plan_title'] ?? 'Plano';
        $approvalUrl = $ctx['approval_url'] ?? (env('APP_URL') . "/aprovacoes/{$planId}");

        // Notify client via WhatsApp + email
        if ($clientId) {
            $client = $ctx['client'] ?? null;
            $locale = $client['language'] ?? 'pt';

            if (!empty($client['whatsapp']) && ($client['notify_whatsapp'] ?? true)) {
                $this->repo->enqueue([
                    'agency_id' => $agencyId,
                    'channel'   => 'whatsapp',
                    'recipient' => $client['whatsapp'],
                    'template'  => 'plan_sent_for_approval',
                    'locale'    => $locale,
                    'payload'   => ['plan_title' => $planTitle, 'approval_url' => $approvalUrl, 'client_name' => $client['name'] ?? ''],
                ]);
            }

            // Notify agency users with content.view permission (in-app)
            $agencyUsers = $this->users->findByAgencyAndPermission($agencyId, 'content.view');
            foreach ($agencyUsers as $u) {
                $this->repo->createNotification([
                    'agency_id'  => $agencyId,
                    'user_id'    => (int) $u['id'],
                    'type'       => 'plan.sent_for_approval',
                    'title'      => "Plano enviado: {$planTitle}",
                    'body'       => "O plano foi enviado para aprovação do cliente.",
                    'action_url' => "/conteudo/{$planId}",
                ]);
            }
        }
    }

    private function onPlanApproved(int $agencyId, array $ctx): void
    {
        $planId    = $ctx['plan_id'] ?? null;
        $planTitle = $ctx['plan_title'] ?? 'Plano';
        $client    = $ctx['client'] ?? null;

        // Notify responsible user in-app
        if (!empty($ctx['responsible_user_id'])) {
            $this->repo->createNotification([
                'agency_id'  => $agencyId,
                'user_id'    => (int) $ctx['responsible_user_id'],
                'type'       => 'plan.approved',
                'title'      => "Plano aprovado: {$planTitle}",
                'body'       => "O cliente aprovou o plano de conteúdo.",
                'action_url' => "/conteudo/{$planId}",
            ]);
        }

        // WhatsApp to client (confirmation)
        if ($client && !empty($client['whatsapp']) && ($client['notify_whatsapp'] ?? true)) {
            $this->repo->enqueue([
                'agency_id' => $agencyId,
                'channel'   => 'whatsapp',
                'recipient' => $client['whatsapp'],
                'template'  => 'plan_approved_confirmation',
                'locale'    => $client['language'] ?? 'pt',
                'payload'   => ['plan_title' => $planTitle, 'client_name' => $client['name'] ?? ''],
            ]);
        }
    }

    private function onPlanRevision(int $agencyId, array $ctx): void
    {
        $planId    = $ctx['plan_id'] ?? null;
        $planTitle = $ctx['plan_title'] ?? 'Plano';
        $note      = $ctx['note'] ?? '';

        // In-app to all users with content.edit
        $agencyUsers = $this->users->findByAgencyAndPermission($agencyId, 'content.edit');
        foreach ($agencyUsers as $u) {
            $this->repo->createNotification([
                'agency_id'  => $agencyId,
                'user_id'    => (int) $u['id'],
                'type'       => 'plan.revision',
                'title'      => "Revisão solicitada: {$planTitle}",
                'body'       => $note ?: "O cliente solicitou revisão do plano.",
                'action_url' => "/conteudo/{$planId}",
            ]);
        }
    }

    private function onItemFeedback(int $agencyId, array $ctx): void
    {
        $planId       = $ctx['plan_id'] ?? null;
        $itemTitle    = $ctx['item_title'] ?? 'Item';
        $feedbackType = $ctx['feedback_type'] ?? 'comment';
        $responsibleId = $ctx['responsible_user_id'] ?? null;

        if (!$responsibleId) return;

        $typeLabels = ['approved' => 'aprovado', 'changes_requested' => 'com revisão', 'comment' => 'comentado'];
        $label = $typeLabels[$feedbackType] ?? $feedbackType;

        $this->repo->createNotification([
            'agency_id'  => $agencyId,
            'user_id'    => (int) $responsibleId,
            'type'       => 'item.feedback',
            'title'      => "Feedback em: {$itemTitle}",
            'body'       => "Item marcado como {$label} pelo cliente.",
            'action_url' => "/conteudo/{$planId}",
        ]);
    }

    private function onTaskAssigned(int $agencyId, array $ctx): void
    {
        $taskId      = $ctx['task_id'] ?? null;
        $taskTitle   = $ctx['task_title'] ?? 'Tarefa';
        $assignedTo  = $ctx['assigned_to'] ?? null;
        $assignedBy  = $ctx['assigned_by'] ?? 'Equipe';

        if (!$assignedTo) return;

        $this->repo->createNotification([
            'agency_id'  => $agencyId,
            'user_id'    => (int) $assignedTo,
            'type'       => 'task.assigned',
            'title'      => "Nova tarefa: {$taskTitle}",
            'body'       => "Atribuída por {$assignedBy}.",
            'action_url' => "/tarefas/{$taskId}",
        ]);

        // Email ao responsável
        $user = $this->users->findByIdAndAgency((int) $assignedTo, $agencyId);
        if ($user && !empty($user['email'])) {
            $this->email->send($user['email'], $user['name'], 'task_assigned', [
                'user_name'  => $user['name'],
                'task_title' => $taskTitle,
                'assigned_by'=> $assignedBy,
                'task_url'   => rtrim(env('APP_URL', ''), '/') . "/tarefas/{$taskId}",
                'app_name'   => env('APP_NAME', 'YVE Agency'),
            ]);
        }
    }

    // ── Automation event handlers ──────────────────────────────────────────────

    private function onInvoiceDue(int $agencyId, array $ctx): void
    {
        $invoice = $ctx['invoice'] ?? [];
        $client  = $ctx['client'] ?? null;
        $this->whatsAppToClient($agencyId, $client, 'invoice_due_reminder', [
            'client_name'    => $client['name'] ?? '',
            'invoice_number' => $invoice['invoice_number'] ?? '',
            'total'          => $this->money($invoice['total'] ?? 0, $invoice['currency_code'] ?? 'BRL'),
            'due_date'       => $this->dateBr($invoice['due_date'] ?? null),
            'days'           => (string) ($ctx['days'] ?? 3),
        ]);
    }

    private function onInvoiceOverdue(int $agencyId, array $ctx): void
    {
        $invoice = $ctx['invoice'] ?? [];
        $client  = $ctx['client'] ?? null;
        $this->whatsAppToClient($agencyId, $client, 'invoice_overdue', [
            'client_name'    => $client['name'] ?? '',
            'invoice_number' => $invoice['invoice_number'] ?? '',
            'total'          => $this->money($invoice['total'] ?? 0, $invoice['currency_code'] ?? 'BRL'),
            'days_overdue'   => (string) ($ctx['days_overdue'] ?? 1),
        ]);
    }

    private function onApprovalReminder(int $agencyId, array $ctx): void
    {
        $client = $ctx['client'] ?? null;
        $this->whatsAppToClient($agencyId, $client, 'content_approval_reminder', [
            'client_name'  => $client['name'] ?? '',
            'plan_title'   => $ctx['plan_title'] ?? 'Plano',
            'approval_url' => $ctx['approval_url'] ?? '',
            'days'         => (string) ($ctx['days'] ?? 2),
        ]);
    }

    private function onClientMonthlyReport(int $agencyId, array $ctx): void
    {
        $client = $ctx['client'] ?? null;
        $this->whatsAppToClient($agencyId, $client, 'client_monthly_report', [
            'client_name' => $client['name'] ?? '',
            'month_label' => $ctx['month_label'] ?? '',
            'report_url'  => $ctx['report_url'] ?? '',
        ]);
    }

    private function onApprovalEscalation(int $agencyId, array $ctx): void
    {
        $planId = $ctx['plan_id'] ?? null;
        $this->inAppToPermission($agencyId, 'content.edit', 'content.escalation',
            "Aprovação parada: {$ctx['plan_title']}",
            "O plano de {$ctx['client_name']} está sem aprovação há {$ctx['days']} dias.",
            "/conteudo/{$planId}");
    }

    private function onContractExpiring(int $agencyId, array $ctx): void
    {
        $this->inAppToPermission($agencyId, 'contracts.view', 'contract.expiring',
            "Contrato expirando: {$ctx['contract_title']}",
            "O contrato de {$ctx['client_name']} vence em {$ctx['days']} dia(s).",
            "/contratos/{$ctx['contract_id']}");
    }

    private function onTaskSlaOverdue(int $agencyId, array $ctx): void
    {
        $taskId = $ctx['task_id'] ?? null;
        if (!empty($ctx['assigned_to'])) {
            $this->repo->createNotification([
                'agency_id'  => $agencyId,
                'user_id'    => (int) $ctx['assigned_to'],
                'type'       => 'task.sla_overdue',
                'title'      => "Tarefa atrasada: {$ctx['task_title']}",
                'body'       => "Esta tarefa está {$ctx['days']} dia(s) atrasada.",
                'action_url' => "/tarefas/{$taskId}",
            ]);
        }
    }

    private function onClientOnboarding(int $agencyId, array $ctx): void
    {
        $client = $ctx['client'] ?? null;
        $this->whatsAppToClient($agencyId, $client, 'client_welcome', [
            'client_name' => $client['name'] ?? '',
            'portal_url'  => $ctx['portal_url'] ?? '',
        ]);
    }

    private function onTeamDailyDigest(int $agencyId, array $ctx): void
    {
        $body = sprintf(
            "Hoje: %d tarefa(s) vencendo · %d aprovação(ões) pendente(s) · %d fatura(s) em aberto.",
            (int) ($ctx['tasks_today'] ?? 0),
            (int) ($ctx['plans_pending'] ?? 0),
            (int) ($ctx['invoices_open'] ?? 0),
        );
        $this->inAppToPermission($agencyId, 'dashboard.view', 'digest.daily', 'Resumo do dia', $body, '/');
    }

    // ── Automation helpers ─────────────────────────────────────────────────────

    private function whatsAppToClient(int $agencyId, ?array $client, string $template, array $payload): void
    {
        if (!$client || empty($client['whatsapp']) || !($client['notify_whatsapp'] ?? true)) {
            return;
        }
        $this->repo->enqueue([
            'agency_id' => $agencyId,
            'channel'   => 'whatsapp',
            'recipient' => $client['whatsapp'],
            'template'  => $template,
            'locale'    => $client['language'] ?? 'pt',
            'payload'   => $payload,
        ]);
    }

    private function inAppToPermission(int $agencyId, string $permission, string $type, string $title, string $body, string $actionUrl): void
    {
        foreach ($this->users->findByAgencyAndPermission($agencyId, $permission) as $u) {
            $this->repo->createNotification([
                'agency_id'  => $agencyId,
                'user_id'    => (int) $u['id'],
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $actionUrl,
            ]);
        }
    }

    private function money(float|string $amount, string $currency = 'BRL'): string
    {
        $symbols = ['BRL' => 'R$', 'USD' => '$', 'EUR' => '€'];
        return ($symbols[$currency] ?? '') . ' ' . number_format((float) $amount, 2, ',', '.');
    }

    private function dateBr(?string $date): string
    {
        return $date ? date('d/m/Y', strtotime($date)) : '';
    }

    // ── WhatsApp message templates ────────────────────────────────────────────

    private function renderWhatsAppMessage(string $template, array $vars, string $locale): string
    {
        $templates = $this->whatsAppTemplates($locale);
        $message   = $templates[$template] ?? $template;

        foreach ($vars as $k => $v) {
            $message = str_replace("::{$k}::", (string) $v, $message);
        }
        return $message;
    }

    private function whatsAppTemplates(string $locale): array
    {
        return match($locale) {
            'en' => [
                'plan_sent_for_approval' =>
                    "Hello, *::client_name::*! 👋\n\nA new content plan *::plan_title::* is ready for your review.\n\n🔗 ::approval_url::\n\nThank you!",
                'plan_approved_confirmation' =>
                    "Great news, *::client_name::*! 🎉\n\nYour approval for *::plan_title::* has been registered. Our team will get started right away!",
                'invoice_due_reminder' =>
                    "Hi, *::client_name::*! 🧾\n\nA friendly reminder: invoice *::invoice_number::* of *::total::* is due on *::due_date::* (in ::days:: days).\n\nThank you!",
                'invoice_overdue' =>
                    "Hi, *::client_name::*. ⚠️\n\nInvoice *::invoice_number::* of *::total::* is *::days_overdue:: day(s) overdue*. Please arrange the payment. Thank you!",
                'content_approval_reminder' =>
                    "Hello, *::client_name::*! ⏰\n\nThe plan *::plan_title::* has been awaiting your approval for ::days:: days.\n\n🔗 ::approval_url::",
                'client_monthly_report' =>
                    "Hello, *::client_name::*! 📊\n\nYour performance report for *::month_label::* is ready.\n\n🔗 ::report_url::",
                'client_welcome' =>
                    "Welcome, *::client_name::*! 🎉\n\nWe're excited to work with you. Access your client portal here:\n\n🔗 ::portal_url::",
            ],
            'es' => [
                'plan_sent_for_approval' =>
                    "¡Hola, *::client_name::*! 👋\n\nTenemos un nuevo plan de contenido *::plan_title::* listo para tu revisión.\n\n🔗 ::approval_url::\n\n¡Gracias!",
                'plan_approved_confirmation' =>
                    "¡Excelente, *::client_name::*! 🎉\n\nTu aprobación de *::plan_title::* fue registrada. ¡Nuestro equipo comenzará de inmediato!",
                'invoice_due_reminder' =>
                    "¡Hola, *::client_name::*! 🧾\n\nRecordatorio: la factura *::invoice_number::* de *::total::* vence el *::due_date::* (en ::days:: días).\n\n¡Gracias!",
                'invoice_overdue' =>
                    "Hola, *::client_name::*. ⚠️\n\nLa factura *::invoice_number::* de *::total::* está *vencida hace ::days_overdue:: día(s)*. Por favor regulariza el pago. ¡Gracias!",
                'content_approval_reminder' =>
                    "¡Hola, *::client_name::*! ⏰\n\nEl plan *::plan_title::* espera tu aprobación desde hace ::days:: días.\n\n🔗 ::approval_url::",
                'client_monthly_report' =>
                    "¡Hola, *::client_name::*! 📊\n\nTu informe de desempeño de *::month_label::* está listo.\n\n🔗 ::report_url::",
                'client_welcome' =>
                    "¡Bienvenido, *::client_name::*! 🎉\n\nEstamos felices de trabajar contigo. Accede a tu portal de cliente aquí:\n\n🔗 ::portal_url::",
            ],
            default => [
                'plan_sent_for_approval' =>
                    "Olá, *::client_name::*! 👋\n\nTemos um novo plano de conteúdo *::plan_title::* pronto para a sua revisão.\n\n🔗 ::approval_url::\n\nObrigado!",
                'plan_approved_confirmation' =>
                    "Ótima notícia, *::client_name::*! 🎉\n\nSua aprovação do plano *::plan_title::* foi registrada. Nossa equipe já vai começar!",
                'invoice_due_reminder' =>
                    "Olá, *::client_name::*! 🧾\n\nLembrete amigável: a fatura *::invoice_number::* de *::total::* vence em *::due_date::* (em ::days:: dias).\n\nObrigado!",
                'invoice_overdue' =>
                    "Olá, *::client_name::*. ⚠️\n\nA fatura *::invoice_number::* de *::total::* está *vencida há ::days_overdue:: dia(s)*. Por favor, regularize o pagamento. Obrigado!",
                'content_approval_reminder' =>
                    "Olá, *::client_name::*! ⏰\n\nO plano *::plan_title::* aguarda sua aprovação há ::days:: dias.\n\n🔗 ::approval_url::",
                'client_monthly_report' =>
                    "Olá, *::client_name::*! 📊\n\nSeu relatório de desempenho de *::month_label::* está pronto.\n\n🔗 ::report_url::",
                'client_welcome' =>
                    "Bem-vindo(a), *::client_name::*! 🎉\n\nEstamos felizes em trabalhar com você. Acesse seu portal do cliente aqui:\n\n🔗 ::portal_url::",
            ],
        };
    }
}
