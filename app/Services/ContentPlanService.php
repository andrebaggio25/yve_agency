<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Repositories\ContentPlanRepository;
use App\Repositories\TaskRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;

class ContentPlanService
{
    /** Status válidos de um criativo. */
    public const ITEM_STATUSES = ['draft', 'revision', 'approved', 'rejected'];

    public function __construct(
        private readonly ContentPlanRepository $repo,
        private readonly GoogleDriveService    $drive,
        private readonly ?ClientRepository     $clientRepo = null,
        private readonly ?NotificationService  $notifications = null,
        private readonly ?AutomationService    $automations = null,
        private readonly ?TaskRepository       $taskRepo = null,
    ) {}

    // ── Plans ──────────────────────────────────────────────────────────────────

    public function list(int $agencyId, array $filters = []): array
    {
        return $this->repo->allByAgency($agencyId, $filters);
    }

    public function get(int $id, int $agencyId): ?array
    {
        return $this->repo->findByIdFull($id, $agencyId);
    }

    public function getWithItems(int $id, int $agencyId): ?array
    {
        $plan = $this->repo->findByIdFull($id, $agencyId);
        if (!$plan) return null;

        $items = $this->repo->getItems($id);
        foreach ($items as &$item) {
            $item['feedbacks']    = $this->repo->getFeedbacks((int) $item['id']);
            $item['drive_parsed'] = $item['drive_url'] ? $this->drive->parse($item['drive_url']) : null;
            $item['images_list']  = is_string($item['images'] ?? null) ? (json_decode($item['images'], true) ?? []) : ($item['images'] ?? []);
        }
        unset($item);

        $plan['items']          = $items;
        $plan['status_summary'] = $this->repo->getItemStatusSummary($id);
        return $plan;
    }

    public function create(array $input, int $agencyId, int $userId): int
    {
        $id = $this->repo->createPlan([
            'agency_id'  => $agencyId,
            'client_id'  => (int) $input['client_id'],
            'title'      => $input['title'],
            'week_start' => $input['week_start'],
            'week_end'   => $input['week_end'] ?? $this->endOfWeek($input['week_start']),
            'status'     => 'draft',
            'created_by' => $userId,
            'notes'      => $input['notes'] ?? null,
        ]);

        ActivityLogger::log('content_plan.created', 'content', null, (int) $input['client_id'], ['plan_id' => $id]);
        return $id;
    }

    public function update(int $id, int $agencyId, array $input): bool
    {
        $fields = [];

        // Obrigatórios: só gravam se vierem preenchidos.
        foreach (['title', 'week_start'] as $key) {
            if (!empty($input[$key])) $fields[$key] = trim((string) $input[$key]);
        }

        // Opcionais: chave presente e vazia limpa o campo.
        foreach (['week_end', 'notes'] as $key) {
            if (!array_key_exists($key, $input)) continue;
            $value        = trim((string) $input[$key]);
            $fields[$key] = $value === '' ? null : $value;
        }

        if (!$fields) return false;

        $affected = $this->repo->updatePlan($id, $agencyId, $fields);
        ActivityLogger::log('content_plan.updated', 'content', null, null, ['plan_id' => $id]);
        return $affected > 0;
    }

    public function send(int $id, int $agencyId): bool
    {
        $plan = $this->get($id, $agencyId);
        if (!$plan || !in_array($plan['status'], ['draft', 'revision'], true)) return false;

        $affected = $this->repo->updatePlan($id, $agencyId, [
            'status'  => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLogger::log('content_plan.sent', 'content', null, null, ['plan_id' => $id]);

        if ($affected > 0) {
            $client = $this->clientRepo?->findByIdAndAgency((int) $plan['client_id'], $agencyId);
            $this->notifications?->notifyEvent('plan.sent_for_approval', $agencyId, [
                'plan_id'    => $id,
                'plan_title' => $plan['title'],
                'client_id'  => $plan['client_id'],
                'client'     => $client,
                'approval_url' => env('APP_URL') . "/aprovacoes/{$id}",
            ]);
        }

        return $affected > 0;
    }

    public function reorderItems(int $planId, array $ids): void
    {
        $this->repo->reorderItems($planId, $ids);
    }

    public function delete(int $id, int $agencyId): bool
    {
        $plan = $this->get($id, $agencyId);
        if (!$plan || $plan['status'] === 'approved') return false;

        $affected = $this->repo->deletePlan($id, $agencyId);
        ActivityLogger::log('content_plan.deleted', 'content', null, null, ['plan_id' => $id]);
        return $affected > 0;
    }

    // ── Items ──────────────────────────────────────────────────────────────────

    public function addItem(int $planId, int $agencyId, array $input): int
    {
        $plan = $this->get($planId, $agencyId);
        if (!$plan) throw new \InvalidArgumentException('Plano não encontrado.');

        $driveData = [];
        if (!empty($input['drive_url'])) {
            $parsed    = $this->drive->parse($input['drive_url']);
            $driveData = [
                'drive_file_id'   => $parsed['valid'] ? $parsed['file_id']   : null,
                'drive_file_type' => $parsed['valid'] ? $parsed['file_type']  : null,
            ];
        }

        $id = $this->repo->createItem(array_merge([
            'content_plan_id' => $planId,
            'client_id'       => (int) $plan['client_id'],
            'publish_date'    => $input['publish_date']  ?? null,
            'publish_time'    => $input['publish_time']  ?? null,
            'content_type'    => $input['content_type']  ?? null,
            'platform'        => $input['platform']      ?? null,
            'title'           => $input['title']         ?? null,
            'theme'           => $input['theme']         ?? null,
            'caption'         => $input['caption']       ?? null,
            'script'          => $input['script']        ?? null,
            'cta'             => $input['cta']           ?? null,
            'drive_url'       => $input['drive_url']     ?? null,
            'cover_url'       => $input['cover_url']     ?? null,
            'images'          => !empty($input['images']) ? json_encode(array_values(array_filter((array) $input['images']))) : null,
            'assigned_to'     => !empty($input['assigned_to']) ? (int) $input['assigned_to'] : null,
            'status'          => 'draft',
            'sort_order'      => $input['sort_order']    ?? 0,
        ], $driveData));

        ActivityLogger::log('content_item.created', 'content', null, null, ['item_id' => $id, 'plan_id' => $planId]);
        return $id;
    }

    public function updateItem(int $itemId, int $agencyId, array $input): bool
    {
        $item = $this->repo->findItem($itemId, $agencyId);
        if (!$item) return false;

        $driveData = [];
        if (array_key_exists('drive_url', $input)) {
            if (!empty($input['drive_url'])) {
                $parsed    = $this->drive->parse($input['drive_url']);
                $driveData = [
                    'drive_file_id'   => $parsed['valid'] ? $parsed['file_id']   : null,
                    'drive_file_type' => $parsed['valid'] ? $parsed['file_type']  : null,
                ];
            } else {
                $driveData = ['drive_file_id' => null, 'drive_file_type' => null];
            }
        }

        // Chave presente = intenção de gravar; string vazia = limpar o campo.
        // Só as chaves enviadas são tocadas, então um form parcial não apaga o resto.
        $fields = [];

        foreach (['publish_date', 'publish_time', 'content_type', 'platform', 'title',
                  'theme', 'caption', 'script', 'cta', 'drive_url', 'cover_url'] as $key) {
            if (!array_key_exists($key, $input)) continue;
            $value         = trim((string) $input[$key]);
            $fields[$key]  = $value === '' ? null : $value;
        }

        if (array_key_exists('images', $input)) {
            $images           = array_values(array_filter(array_map('trim', (array) $input['images'])));
            $fields['images'] = $images ? json_encode($images) : null;
        }

        if (array_key_exists('assigned_to', $input)) {
            $fields['assigned_to'] = empty($input['assigned_to']) ? null : (int) $input['assigned_to'];
        }

        if (array_key_exists('sort_order', $input)) {
            $fields['sort_order'] = (int) $input['sort_order'];
        }

        // O status do item é dirigido pelo fluxo de aprovação, não por edição livre.
        if (!empty($input['status']) && in_array($input['status'], self::ITEM_STATUSES, true)) {
            $fields['status'] = $input['status'];
        }

        $fields = array_merge($fields, $driveData);
        if (!$fields) return false;

        $affected = $this->repo->updateItem($itemId, $fields);
        ActivityLogger::log('content_item.updated', 'content', null, null, ['item_id' => $itemId]);
        return $affected > 0;
    }

    public function deleteItem(int $itemId, int $agencyId): bool
    {
        $ok = $this->repo->deleteItem($itemId, $agencyId);
        if ($ok) ActivityLogger::log('content_item.deleted', 'content', null, null, ['item_id' => $itemId]);
        return $ok;
    }

    // ── Feedback / Approval ────────────────────────────────────────────────────

    public function addFeedback(
        int $itemId, int $planId, int $clientId, ?int $userId, string $type, ?string $comment,
        ?int $timecodeSeconds = null, string $source = 'client'
    ): int {
        $id = $this->repo->addFeedback([
            'content_plan_item_id' => $itemId,
            'content_plan_id'      => $planId,
            'client_id'            => $clientId,
            'user_id'              => $userId,
            'feedback_type'        => $type,
            'comment'              => $comment,
            'timecode_seconds'     => $timecodeSeconds,
            'source'               => $source,
        ]);

        // Agency plain comments do not change item status
        if (!($source === 'agency' && $type === 'comment')) {
            $statusMap = [
                'approved'          => 'approved',
                'changes_requested' => 'revision',
                'rejected'          => 'rejected',
            ];
            if (isset($statusMap[$type])) {
                $this->repo->updateItem($itemId, ['status' => $statusMap[$type]]);
            }
        }

        ActivityLogger::log('approval.feedback_added', 'approvals', null, $clientId, ['item_id' => $itemId, 'type' => $type]);

        if ($type === 'approved') {
            $this->maybeApprovePlanFromItems($planId, $clientId);
        }

        return $id;
    }

    /**
     * Quando o último criativo pendente é aprovado, o plano inteiro passa a
     * aprovado — sem exigir que o cliente clique também em "Aprovar Tudo".
     */
    private function maybeApprovePlanFromItems(int $planId, int $clientId): void
    {
        $summary = $this->repo->getItemStatusSummary($planId);
        $total   = array_sum($summary);

        if ($total === 0 || ($summary['approved'] ?? 0) !== $total) return;

        $plan = $this->repo->findByIdForClient($planId, $clientId);
        if (!$plan || $plan['status'] === 'approved') return;

        $this->approvePlan($planId, $clientId);
    }

    public function approvePlan(int $planId, int $clientId): bool
    {
        $plan = $this->repo->findByIdForClient($planId, $clientId);
        if (!$plan) return false;

        $agencyId = (int) $plan['agency_id'];
        $affected = $this->repo->updatePlan($planId, $agencyId, [
            'status'      => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLogger::log('content_plan.approved', 'approvals', null, $clientId, ['plan_id' => $planId]);

        if ($affected > 0) {
            $client = $this->clientRepo?->findByIdAndAgency($clientId, $agencyId);
            $this->notifications?->notifyEvent('plan.approved', $agencyId, [
                'plan_id'             => $planId,
                'plan_title'          => $plan['title'],
                'client'              => $client,
                'responsible_user_id' => $plan['created_by'] ?? null,
            ]);
            $this->maybeCreateProductionTasks($agencyId, $clientId, $planId, $plan);
        }

        return $affected > 0;
    }

    /**
     * Automação content.approved_create_tasks: ao aprovar, cria as tarefas de
     * produção (uma por item do plano) para a equipe. Gate por agência + idempotência.
     */
    private function maybeCreateProductionTasks(int $agencyId, int $clientId, int $planId, array $plan): void
    {
        if (!$this->automations || !$this->taskRepo) return;
        if (!$this->automations->isEnabledForClient($agencyId, $clientId, 'content.approved_create_tasks')) return;

        $dedupe = "plan:{$planId}:tasks";
        if (!$this->automations->shouldRun('content.approved_create_tasks', $dedupe)) return;

        $createdBy = (int) ($plan['created_by'] ?? 0);
        $items     = $this->repo->getItems($planId);

        if ($items) {
            foreach ($items as $item) {
                $this->taskRepo->create([
                    'agency_id'   => $agencyId,
                    'client_id'   => $clientId,
                    'assigned_to' => !empty($item['assigned_to']) ? (int) $item['assigned_to'] : null,
                    'created_by'  => $createdBy,
                    'title'       => 'Produzir: ' . ($item['title'] ?: ($item['content_type'] ?? 'conteúdo')),
                    'description' => $item['caption'] ?? null,
                    'status'      => 'todo',
                    'priority'    => 'medium',
                    'due_date'    => $item['publish_date'] ?? null,
                ]);
            }
        } else {
            $this->taskRepo->create([
                'agency_id'   => $agencyId,
                'client_id'   => $clientId,
                'assigned_to' => null,
                'created_by'  => $createdBy,
                'title'       => 'Produzir conteúdo: ' . ($plan['title'] ?? ''),
                'description' => null,
                'status'      => 'todo',
                'priority'    => 'medium',
                'due_date'    => null,
            ]);
        }

        $this->automations->markRan($agencyId, $clientId, 'content.approved_create_tasks', $dedupe);
    }

    public function requestRevision(int $planId, int $clientId, string $note): bool
    {
        $plan = $this->repo->findByIdForClient($planId, $clientId);
        if (!$plan) return false;

        $agencyId = (int) $plan['agency_id'];
        $affected = $this->repo->updatePlan($planId, $agencyId, [
            'status' => 'revision',
            'notes'  => $note,
        ]);
        ActivityLogger::log('content_plan.revision_requested', 'approvals', null, $clientId, ['plan_id' => $planId]);

        if ($affected > 0) {
            $this->notifications?->notifyEvent('plan.revision_requested', $agencyId, [
                'plan_id'    => $planId,
                'plan_title' => $plan['title'],
                'note'       => $note,
            ]);
        }

        return $affected > 0;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function endOfWeek(string $weekStart): string
    {
        return date('Y-m-d', strtotime($weekStart . ' +6 days'));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft'    => 'Rascunho',
            'sent'     => 'Enviado',
            'revision' => 'Em Revisão',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            default    => ucfirst($status),
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'draft'    => 'gray',
            'sent'     => 'blue',
            'revision' => 'amber',
            'approved' => 'emerald',
            'rejected' => 'rose',
            default    => 'gray',
        };
    }

    public static function itemStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'    => 'Rascunho',
            'revision' => 'Revisão',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            default    => ucfirst($status),
        };
    }

    /** Proporção nativa do Instagram para o criativo: capa de Reels/Story é 9:16, o resto é 3:4. */
    public static function previewRatio(?string $contentType): string
    {
        return match ($contentType) {
            'Reels / Vídeo', 'Story' => '9/16',
            default                  => '3/4',
        };
    }

    /**
     * Classes do quadro de preview. As larguras máximas são escolhidas para as
     * duas proporções renderizarem com a mesma altura (~427px) no desktop.
     */
    public static function previewFrameClass(?string $contentType): string
    {
        return self::previewRatio($contentType) === '9/16'
            ? 'aspect-[9/16] max-w-[15rem]'
            : 'aspect-[3/4] max-w-[20rem]';
    }
}
