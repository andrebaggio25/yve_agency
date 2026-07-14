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

    /**
     * Link PÚBLICO de aprovação (portal do cliente). O cliente não tem login —
     * mandar a rota interna /aprovacoes/{id} gera um link que ele não abre.
     */
    public static function portalPlanUrl(?string $portalToken, int $planId): ?string
    {
        if (!$portalToken) return null;
        return rtrim((string) env('APP_URL', ''), '/') . "/portal/{$portalToken}/planos/{$planId}";
    }

    // ── Semana seg–dom ─────────────────────────────────────────────────────────
    //
    // A agência envia planificação SEMPRE por semana fechada, de segunda a
    // domingo. Qualquer data escolhida "encaixa" na segunda-feira daquela
    // semana; o domingo é derivado — nunca editado.

    /** Segunda-feira da semana em que a data cai (a própria, se já for segunda). */
    public static function mondayOf(string $date): string
    {
        $ts = strtotime($date) ?: time();
        return date('Y-m-d', strtotime('-' . ((int) date('N', $ts) - 1) . ' days', $ts));
    }

    /** Domingo da mesma semana (segunda + 6). */
    public static function sundayOf(string $date): string
    {
        return date('Y-m-d', strtotime(self::mondayOf($date) . ' +6 days'));
    }

    /** Nome padrão do plano: "CLIENTE X | 12/01 – 18/01" (seg–dom). */
    public static function defaultTitle(string $clientName, string $weekStart): string
    {
        $monday = self::mondayOf($weekStart);
        $period = date('d/m', (int) strtotime($monday)) . ' – ' . date('d/m', (int) strtotime($monday . ' +6 days'));
        $name   = trim($clientName);

        return mb_substr($name === '' ? "Semana {$period}" : mb_strtoupper($name) . " | {$period}", 0, 255);
    }

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

    /** Itens com publicação no intervalo (PROD-04 — calendário). */
    public function itemsBetween(int $agencyId, string $from, string $to, array $filters = []): array
    {
        return $this->repo->itemsBetween($agencyId, $from, $to, $filters);
    }

    public function get(int $id, int $agencyId): ?array
    {
        return $this->repo->findByIdFull($id, $agencyId);
    }

    /** Clientes que já têm plano na semana (radar de pauta na listagem). */
    public function clientIdsWithPlanForWeek(int $agencyId, string $weekStart): array
    {
        return $this->repo->clientIdsWithPlanForWeek($agencyId, $weekStart);
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
        $clientId  = (int) $input['client_id'];
        $weekStart = self::mondayOf((string) ($input['week_start'] ?: date('Y-m-d')));

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $client = $this->clientRepo?->findByIdAndAgency($clientId, $agencyId);
            $title  = self::defaultTitle((string) ($client['name'] ?? ''), $weekStart);
        }

        $id = $this->repo->createPlan([
            'agency_id'  => $agencyId,
            'client_id'  => $clientId,
            'title'      => $title,
            'week_start' => $weekStart,
            'week_end'   => self::sundayOf($weekStart),
            'status'     => 'draft',
            'created_by' => $userId,
            'notes'      => $input['notes'] ?? null,
        ]);

        // O modelo semanal do cliente pré-monta a grade (dias, horários,
        // plataformas, formatos, responsáveis) — o conteúdo nasce vazio.
        if (!empty($input['apply_template'])) {
            $template = $this->repo->findTemplateByClient($clientId, $agencyId);
            if ($template && !empty($template['items'])) {
                $this->applyTemplateItems($id, $clientId, $weekStart, $template['items']);
            }
        }

        ActivityLogger::log('content_plan.created', 'content', null, $clientId, ['plan_id' => $id]);
        return $id;
    }

    // ── Modelo semanal por cliente ─────────────────────────────────────────────

    /**
     * Captura a ESTRUTURA do plano como modelo do cliente: dia da semana
     * (relativo à data real do post), hora, plataforma, formato e responsável.
     * Conteúdo (legenda, mídia, títulos) fica de fora por definição — modelo
     * é grade, não post.
     */
    public function saveTemplateFromPlan(int $planId, int $agencyId, ?int $userId): bool
    {
        $plan = $this->get($planId, $agencyId);
        if (!$plan) return false;

        $items = [];
        foreach ($this->repo->getItems($planId) as $item) {
            $items[] = [
                'weekday'      => !empty($item['publish_date']) ? (int) date('N', (int) strtotime((string) $item['publish_date'])) : null,
                'publish_time' => $item['publish_time'] ? substr((string) $item['publish_time'], 0, 5) : null,
                'platform'     => $item['platform'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'assigned_to'  => !empty($item['assigned_to']) ? (int) $item['assigned_to'] : null,
                'sort_order'   => (int) ($item['sort_order'] ?? 0),
            ];
        }
        if (!$items) return false;

        $this->repo->saveTemplate((int) $plan['client_id'], $agencyId, $items, $userId);
        ActivityLogger::log('content_template.saved', 'content', $userId, (int) $plan['client_id'], [
            'plan_id' => $planId,
            'items'   => count($items),
        ]);

        return true;
    }

    /** Modelo do cliente (ou null), com itens decodificados. */
    public function getTemplateForClient(int $clientId, int $agencyId): ?array
    {
        return $this->repo->findTemplateByClient($clientId, $agencyId);
    }

    /** Cria os itens do modelo dentro do plano: weekday 1..7 → data da semana. */
    private function applyTemplateItems(int $planId, int $clientId, string $weekStart, array $templateItems): void
    {
        foreach ($templateItems as $tpl) {
            $weekday = isset($tpl['weekday']) ? (int) $tpl['weekday'] : 0;
            $date    = ($weekday >= 1 && $weekday <= 7)
                ? date('Y-m-d', (int) strtotime($weekStart . ' +' . ($weekday - 1) . ' days'))
                : null;

            $this->repo->createItem([
                'content_plan_id' => $planId,
                'client_id'       => $clientId,
                'publish_date'    => $date,
                'publish_time'    => $tpl['publish_time'] ?? null,
                'platform'        => $tpl['platform'] ?? null,
                'content_type'    => $tpl['content_type'] ?? null,
                'assigned_to'     => !empty($tpl['assigned_to']) ? (int) $tpl['assigned_to'] : null,
                'sort_order'      => (int) ($tpl['sort_order'] ?? 0),
                'status'          => 'draft',
            ]);
        }
    }

    public function update(int $id, int $agencyId, array $input): bool
    {
        $fields = [];

        // Obrigatórios: só gravam se vierem preenchidos.
        if (!empty($input['title'])) {
            $fields['title'] = trim((string) $input['title']);
        }

        // A semana encaixa na segunda; o domingo é sempre derivado.
        if (!empty($input['week_start'])) {
            $fields['week_start'] = self::mondayOf(trim((string) $input['week_start']));
            $fields['week_end']   = self::sundayOf($fields['week_start']);
        }

        // Opcionais: chave presente e vazia limpa o campo.
        if (array_key_exists('notes', $input)) {
            $value           = trim((string) $input['notes']);
            $fields['notes'] = $value === '' ? null : $value;
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
                'approval_url' => self::portalPlanUrl($plan['client_portal_token'] ?? null, $id),
            ]);
        }

        return $affected > 0;
    }

    public function reorderItems(int $planId, array $ids): void
    {
        $this->repo->reorderItems($planId, $ids);
    }

    /**
     * Duplica a ESTRUTURA de um plano (PROD-05).
     *
     * Copia a grade de trabalho — quando, onde, que formato, quem faz — e
     * **não o conteúdo do post**: legenda, roteiro, CTA, tema, título, capa,
     * imagens e links do Drive nascem vazios. O post de cada mês é único;
     * herdar o texto do anterior só produziria material errado esperando para
     * ser publicado por engano.
     *
     * A cópia nasce em **rascunho**, sem herdar aprovação, feedback ou datas de
     * envio — dizer que a cliente aprovou um plano que ela nunca viu seria
     * mentira do sistema.
     *
     * @return array{success:bool,id?:int,error?:string}
     */
    public function duplicate(int $id, int $agencyId, int $userId, array $input = []): array
    {
        $plan = $this->getWithItems($id, $agencyId);
        if (!$plan) {
            return ['success' => false, 'error' => 'Plano não encontrado.'];
        }

        $weekStart = self::mondayOf((string) ($input['week_start'] ?? $this->nextWeek((string) $plan['week_start'])));
        $weekEnd   = self::sundayOf($weekStart);
        $title     = trim((string) ($input['title'] ?? ''))
            ?: self::defaultTitle((string) ($plan['client_name'] ?? ''), $weekStart);

        // Deslocamento entre SEGUNDAS (múltiplo de 7): o post que caía na
        // quarta continua caindo na quarta da nova semana.
        $shiftDays = $this->daysBetween(self::mondayOf((string) $plan['week_start']), $weekStart);

        $newId = $this->repo->createPlan([
            'agency_id'  => $agencyId,
            'client_id'  => (int) $plan['client_id'],
            'title'      => $title,
            'week_start' => $weekStart,
            'week_end'   => $weekEnd,
            'status'     => 'draft',   // nunca herda aprovação
            'created_by' => $userId,
            'notes'      => $plan['notes'] ?? null,
        ]);

        // Copia a ESTRUTURA, não o post.
        //
        // O que vem junto: quando publicar (data deslocada, hora), onde
        // (plataforma), que formato (tipo), quem faz (responsável) e a ordem —
        // é a grade de trabalho, e é isso que se repete de um mês para o outro.
        //
        // O que NÃO vem: legenda, roteiro, CTA, tema, título, capa, imagens e
        // links do Drive. O conteúdo em si é único de cada mês; herdar o texto
        // do post anterior só criaria material errado esperando para ser
        // publicado por engano.
        foreach ($plan['items'] ?? [] as $item) {
            // O clamp protege itens legados que estavam fora da semana do
            // plano de origem — na cópia, todos caem dentro de seg–dom.
            $newDate = $this->shiftDate($item['publish_date'] ?? null, $shiftDays);
            if ($newDate !== null) {
                $newDate = max($weekStart, min($weekEnd, $newDate));
            }

            $this->repo->createItem([
                'content_plan_id' => $newId,
                'client_id'       => (int) $plan['client_id'],
                'publish_date'    => $newDate,
                'publish_time'    => $item['publish_time'] ?? null,
                'platform'        => $item['platform'] ?? null,
                'content_type'    => $item['content_type'] ?? null,
                'assigned_to'     => $item['assigned_to'] ?? null,
                'sort_order'      => (int) ($item['sort_order'] ?? 0),
                'status'          => 'draft',
            ]);
        }

        ActivityLogger::log('content_plan.duplicated', 'content', $userId, (int) $plan['client_id'], [
            'source_plan_id' => $id,
            'new_plan_id'    => $newId,
            'items'          => count($plan['items'] ?? []),
        ]);

        return ['success' => true, 'id' => $newId];
    }

    /** Semana seguinte à do plano de origem (padrão ao duplicar). */
    private function nextWeek(?string $weekStart): string
    {
        $base = $weekStart ?: date('Y-m-d');
        return date('Y-m-d', strtotime($base . ' +7 days'));
    }

    private function daysBetween(?string $from, string $to): int
    {
        if (!$from) {
            return 0;
        }
        return (int) round((strtotime($to) - strtotime($from)) / 86400);
    }

    private function shiftDate(?string $date, int $days): ?string
    {
        if (!$date) {
            return null;
        }
        return date('Y-m-d', strtotime($date . " {$days} days"));
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

        $this->assertDateInPlanWeek($input['publish_date'] ?? null, $plan);

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

        if (!empty($input['publish_date'])) {
            $plan = $this->get((int) $item['content_plan_id'], $agencyId);
            if ($plan) {
                $this->assertDateInPlanWeek((string) $input['publish_date'], $plan);
            }
        }

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

    /**
     * O post tem de cair dentro da semana do plano. Compara com os bounds
     * ARMAZENADOS (não normalizados): plano legado com semana fora do padrão
     * seg–dom continua editável dentro do próprio intervalo.
     *
     * @throws \InvalidArgumentException
     */
    private function assertDateInPlanWeek(?string $date, array $plan): void
    {
        if (!$date || empty($plan['week_start']) || empty($plan['week_end'])) return;

        if ($date < $plan['week_start'] || $date > $plan['week_end']) {
            $from = date('d/m', (int) strtotime((string) $plan['week_start']));
            $to   = date('d/m', (int) strtotime((string) $plan['week_end']));
            throw new \InvalidArgumentException("A data precisa estar dentro da semana do plano ({$from} a {$to}).");
        }
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
            $this->maybeCreateNextPlan($agencyId, $clientId, $planId, $plan);
        }

        return $affected > 0;
    }

    /**
     * Automação content.approved_create_next_plan: aprovou a semana, nasce o
     * rascunho da seguinte — pelo modelo semanal do cliente ou, sem modelo,
     * pela estrutura do plano aprovado. Idempotente por plano, e a criação
     * manual antecipada vence: semana ocupada não ganha duplicata.
     */
    private function maybeCreateNextPlan(int $agencyId, int $clientId, int $planId, array $plan): void
    {
        if (!$this->automations || empty($plan['week_start'])) return;
        if (!$this->automations->isEnabledForClient($agencyId, $clientId, 'content.approved_create_next_plan')) return;

        $key    = 'content.approved_create_next_plan';
        $dedupe = "plan:{$planId}:next";
        if (!$this->automations->shouldRun($key, $dedupe)) return;

        $nextMonday = self::mondayOf(date('Y-m-d', (int) strtotime($plan['week_start'] . ' +7 days')));

        if ($this->repo->existsForClientWeek($clientId, $nextMonday)) {
            $this->automations->markRan($agencyId, $clientId, $key, $dedupe, 'skipped', null, 'Semana seguinte já tem plano');
            return;
        }

        $createdBy = (int) ($plan['created_by'] ?? 0);
        $template  = $this->repo->findTemplateByClient($clientId, $agencyId);

        if ($template && !empty($template['items'])) {
            $newId = $this->repo->createPlan([
                'agency_id'  => $agencyId,
                'client_id'  => $clientId,
                'title'      => self::defaultTitle((string) ($plan['client_name'] ?? ''), $nextMonday),
                'week_start' => $nextMonday,
                'week_end'   => self::sundayOf($nextMonday),
                'status'     => 'draft',
                'created_by' => $createdBy ?: null,
            ]);
            $this->applyTemplateItems($newId, $clientId, $nextMonday, $template['items']);
        } else {
            $result = $this->duplicate($planId, $agencyId, $createdBy, ['week_start' => $nextMonday]);
            $newId  = (int) ($result['id'] ?? 0);
            if (!$newId) return;
        }

        $this->automations->markRan($agencyId, $clientId, $key, $dedupe, 'done', 'inapp', "Plano {$newId} criado");
        ActivityLogger::log('content_plan.auto_created', 'content', null, $clientId, [
            'source_plan_id' => $planId,
            'new_plan_id'    => $newId,
        ]);

        $this->notifications?->notifyEvent('plan.auto_created', $agencyId, [
            'plan_id'     => $newId,
            'plan_title'  => self::defaultTitle((string) ($plan['client_name'] ?? ''), $nextMonday),
            'client_name' => $plan['client_name'] ?? '',
        ]);
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
