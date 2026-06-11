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
            default                   => null,
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
        $this->repo->createNotification(compact('agency_id', 'user_id', 'type', 'title', 'body', 'action_url') + [
            'agency_id'  => $agencyId,
            'user_id'    => $userId,
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
            ],
            'es' => [
                'plan_sent_for_approval' =>
                    "¡Hola, *::client_name::*! 👋\n\nTenemos un nuevo plan de contenido *::plan_title::* listo para tu revisión.\n\n🔗 ::approval_url::\n\n¡Gracias!",
                'plan_approved_confirmation' =>
                    "¡Excelente, *::client_name::*! 🎉\n\nTu aprobación de *::plan_title::* fue registrada. ¡Nuestro equipo comenzará de inmediato!",
            ],
            default => [
                'plan_sent_for_approval' =>
                    "Olá, *::client_name::*! 👋\n\nTemos um novo plano de conteúdo *::plan_title::* pronto para a sua revisão.\n\n🔗 ::approval_url::\n\nObrigado!",
                'plan_approved_confirmation' =>
                    "Ótima notícia, *::client_name::*! 🎉\n\nSua aprovação do plano *::plan_title::* foi registrada. Nossa equipe já vai começar!",
            ],
        };
    }
}
