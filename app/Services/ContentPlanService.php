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
     * Plano na mesa da cliente, esperando decisão — os ÚNICOS status de onde
     * sai "aprovado" ou "em revisão" (BUG-05). 'pending_approval' é dado
     * legado/manual, aceito só na leitura; o envio grava 'sent'.
     *
     * Rascunho não entra porque a cliente nunca o viu, e 'revision' espera a
     * agência corrigir e **reenviar**: fechar o plano a partir dali faria o
     * sistema dizer que ela aprovou a versão que pediu para mudar.
     */
    private const AWAITING_CLIENT = ['sent', 'pending_approval'];

    /** Plano fechado que a equipe pode reabrir (BUG-08). */
    private const REOPENABLE = ['approved', 'rejected'];

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

    /**
     * Planos da tela de Aprovações (BUG-04): os da agência que já saíram do
     * rascunho, recortados pelos clientes que o usuário alcança — `null` = a
     * agência inteira (`clients.view_all`), como manda `Auth::reachableClientIds`.
     *
     * @param list<int>|null $clientIds
     * @return list<array<string,mixed>>
     */
    public function listForApproval(int $agencyId, ?array $clientIds): array
    {
        $filters = ['statuses' => ['sent', 'revision', 'approved', 'rejected']];

        if ($clientIds !== null) {
            $filters['client_ids'] = $clientIds;
        }

        return $this->repo->allByAgency($agencyId, $filters);
    }

    /**
     * Plano que a tela de Aprovações pode abrir ou mudar: da agência e fora do
     * rascunho — ninguém aprova o que a cliente não recebeu. `$withItems` traz
     * os posts com feedback, como o detalhe precisa.
     *
     * @return array<string,mixed>|null
     */
    public function planForApproval(int $planId, int $agencyId, bool $withItems = false): ?array
    {
        $plan = $withItems
            ? $this->getWithItems($planId, $agencyId)
            : $this->repo->findByIdFull($planId, $agencyId);

        return ($plan && $plan['status'] !== 'draft') ? $plan : null;
    }

    /** Post com o cliente dono — o controller usa para validar o acesso (SEC-11). */
    public function getItem(int $itemId, int $agencyId): ?array
    {
        return $this->repo->findItem($itemId, $agencyId);
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

    /**
     * Grava o plano. `week_moved` = a semana mudou e os posts foram com ela.
     *
     * @param array<string,mixed> $input
     * @return array{success:bool,week_moved:bool}
     * @throws \InvalidArgumentException semana de plano já aprovado pela cliente
     */
    public function update(int $id, int $agencyId, array $input): array
    {
        $fields    = [];
        $newMonday = null;

        // Obrigatórios: só gravam se vierem preenchidos.
        if (!empty($input['title'])) {
            $fields['title'] = trim((string) $input['title']);
        }

        // A semana encaixa na segunda; o domingo é sempre derivado.
        if (!empty($input['week_start'])) {
            $newMonday            = self::mondayOf(trim((string) $input['week_start']));
            $fields['week_start'] = $newMonday;
            $fields['week_end']   = self::sundayOf($newMonday);
        }

        // Opcionais: chave presente e vazia limpa o campo.
        if (array_key_exists('notes', $input)) {
            $value           = trim((string) $input['notes']);
            $fields['notes'] = $value === '' ? null : $value;
        }

        if (!$fields) return ['success' => false, 'week_moved' => false];

        // A semana antiga precisa ser lida ANTES da gravação: é a referência do
        // deslocamento dos posts.
        $plan = $newMonday !== null ? $this->get($id, $agencyId) : null;

        // Semana aprovada não muda de lugar: agora que trocar `week_start`
        // ARRASTA os posts, isto re-datava em silêncio tudo o que a cliente
        // fechou (status 'approved' e `approved_at` intactos), e o portal
        // passava a exibir como aprovada uma semana que ela nunca viu — a mesma
        // mentira que `assertPlanOpenForItems` impede em adicionar/excluir, só
        // maior. Título e notas internas continuam editáveis.
        if ($plan !== null && ($plan['status'] ?? '') === 'approved'
            && $newMonday !== (string) ($plan['week_start'] ?? '')) {
            throw new \InvalidArgumentException(t('content.plan_closed_week'));
        }

        $affected  = $this->repo->updatePlan($id, $agencyId, $fields);
        $weekMoved = 0;

        if ($affected > 0 && $newMonday !== null && $plan !== null) {
            $weekMoved = $this->shiftItemsToWeek($id, (string) ($plan['week_start'] ?? ''), $newMonday);
        }

        ActivityLogger::log('content_plan.updated', 'content', null, null, ['plan_id' => $id]);
        return ['success' => $affected > 0, 'week_moved' => $weekMoved > 0];
    }

    /**
     * Trocar a semana leva os posts junto, **no mesmo dia da semana**: o post
     * de quarta continua na quarta.
     *
     * Sem isto, mudar `week_start` deixava todo post com data fora de
     * `week_start..week_end`: a visão Semana os jogava no aviso "fora da
     * semana" e `assertDateInPlanWeek` passava a recusar qualquer edição deles
     * — o plano ficava intocável até reagendar post por post.
     *
     * O deslocamento é medido entre as SEGUNDAS (múltiplo de 7), então plano
     * legado com `week_start` fora da segunda também preserva o dia. Post sem
     * data continua sem data; data que já estava fora da semana antiga entra no
     * limite mais próximo da nova (mesmo clamp de `duplicate`).
     *
     * @return int quantos posts mudaram de data
     */
    private function shiftItemsToWeek(int $planId, string $oldWeekStart, string $newWeekStart): int
    {
        if ($oldWeekStart === '') return 0;

        $shiftDays = $this->daysBetween(self::mondayOf($oldWeekStart), $newWeekStart);
        if ($shiftDays === 0) return 0;

        $weekEnd = self::sundayOf($newWeekStart);
        $moved   = 0;

        foreach ($this->repo->getItems($planId) as $item) {
            $date = (string) ($item['publish_date'] ?? '');
            if ($date === '') continue;

            $newDate = max($newWeekStart, min($weekEnd, (string) $this->shiftDate($date, $shiftDays)));
            if ($newDate === $date) continue;

            $this->repo->updateItem((int) $item['id'], ['publish_date' => $newDate]);
            $moved++;
        }

        return $moved;
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
     * `$byAutomation` marca a cópia disparada pela aprovação da cliente no
     * portal (`maybeCreateNextPlan`): ali não há sessão nem ator humano, e isso
     * muda a linha de auditoria lá embaixo.
     *
     * @return array{success:bool,id?:int,error?:string}
     */
    public function duplicate(int $id, int $agencyId, int $userId, array $input = [], bool $byAutomation = false): array
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

        // A agência vai explícita porque duplicar também acontece **pelo
        // portal**, pela automação da aprovação, onde `Auth::agencyId()` é nulo
        // e a linha nasceria em `agency_id = 0` (SEC-15). E por ali não há ator:
        // `$userId` é o `created_by` do plano de origem, então creditar o gestor
        // punha no nome dele uma criação disparada pelo clique da cliente —
        // `user_id` nulo é a verdade, como nas outras linhas do portal.
        ActivityLogger::log(
            'content_plan.duplicated',
            'content',
            $byAutomation ? null : $userId,
            (int) $plan['client_id'],
            [
                'source_plan_id' => $id,
                'new_plan_id'    => $newId,
                'items'          => count($plan['items'] ?? []),
                'automated'      => $byAutomation,
            ],
            $agencyId,
        );

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

        $this->assertPlanOpenForItems($plan);
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

        // O plano é carregado uma vez: a data e o status do post são julgados
        // contra a semana dele.
        $plan = (!empty($input['publish_date']) || !empty($input['status']))
            ? $this->get((int) $item['content_plan_id'], $agencyId)
            : null;

        if (!empty($input['publish_date']) && $plan) {
            $this->assertDateInPlanWeek((string) $input['publish_date'], $plan);
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
            $this->assertItemStatusEditable((string) $input['status'], $plan);
            $fields['status'] = $input['status'];
        }

        $fields = array_merge($fields, $driveData);
        if (!$fields) return false;

        $affected = $this->repo->updateItem($itemId, $fields);
        ActivityLogger::log('content_item.updated', 'content', null, null, ['item_id' => $itemId]);
        return $affected > 0;
    }

    /**
     * Plano aprovado não ganha post novo nem perde post (BUG-05).
     *
     * A cliente fechou aquela semana: enfiar um post depois faz a tela dizer
     * que ela aprovou conteúdo que nunca viu, e excluir apaga o que ela
     * aprovou — nos dois casos o selo "Aprovado" passa a mentir.
     *
     * Reagendar (CONT-07) continua levando o post para outra semana, inclusive
     * saindo de semana aprovada: o post não desaparece, vai inteiro e a pedido
     * da cliente ("publica semana que vem"), com o movimento no `activity_logs`.
     * Quem bloqueia é a semana de DESTINO já aprovada.
     *
     * @param array<string,mixed> $plan
     * @throws \InvalidArgumentException
     */
    private function assertPlanOpenForItems(array $plan): void
    {
        if (($plan['status'] ?? '') === 'approved') {
            throw new \InvalidArgumentException(t('content.plan_closed_items'));
        }
    }

    /**
     * Edição livre não decide aprovação (BUG-05).
     *
     * 'approved' sai do fluxo de aprovação — do portal, onde a cliente decide,
     * ou da tela de Aprovações, onde alguém com `approvals.approve` decide por
     * ela deixando feedback, autor e data registrados. Pelo menu "Status" do
     * card era um selo "Aprovado" sem autor nem data, e isso bastava para
     * `maybeApprovePlanFromItems` fechar a semana no primeiro "Aprovar" da
     * cliente: o sistema passava a afirmar que ela aprovou posts que nunca viu.
     *
     * Em plano já aprovado o status do post não muda mais por aqui: o selo da
     * semana e o dos posts têm de continuar contando a mesma história, e devolver
     * um post para rascunho ali não tem volta (feedback de item em plano
     * aprovado responde 409 nas duas telas).
     *
     * @param array<string,mixed>|null $plan plano do post, quando carregado
     * @throws \InvalidArgumentException
     */
    private function assertItemStatusEditable(string $status, ?array $plan): void
    {
        if ($status === 'approved') {
            throw new \InvalidArgumentException(t('content.item_status_approval_only'));
        }

        if (($plan['status'] ?? '') === 'approved') {
            throw new \InvalidArgumentException(t('content.item_status_plan_closed'));
        }
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

    /** Plano do cliente na semana em que a data cai (para prever o destino do reagendamento). */
    public function planForWeek(int $clientId, int $agencyId, string $date): ?array
    {
        return $this->repo->findByClientWeek($clientId, $agencyId, self::mondayOf($date));
    }

    // ── Reagendar post (CONT-REAG) ─────────────────────────────────────────────

    /**
     * Move um post para outra data — **inclusive fora da semana do plano**.
     *
     * O caso real: a cliente aprovou o post desta semana e pediu para publicar
     * na próxima. Até aqui isso obrigava a recriar o post do zero no plano da
     * semana seguinte (plataforma, formato, legenda, roteiro, CTA, capa,
     * imagens, responsável, feedbacks) — trabalho manual e propenso a perder
     * conteúdo. Agora o post **muda de plano**, levando tudo consigo: é a mesma
     * linha, não uma cópia.
     *
     * Se a semana de destino ainda não tem plano, ele nasce aqui — em rascunho,
     * vazio, só com o post que chegou. Não aplicamos o modelo semanal do cliente
     * nesse caso: quem move um post não pediu uma grade nova de posts vazios.
     *
     * O status do post é preservado. Um post aprovado continua aprovado depois
     * de adiado — o que mudou foi a data, não a decisão da cliente. Semana
     * **de destino** já aprovada bloqueia a troca de plano (BUG-05): enfiar um
     * post num plano que a cliente fechou faria o sistema dizer que ela aprovou
     * algo que nunca viu. A semana de ORIGEM aprovada não bloqueia — é o caso
     * para o qual o CONT-07 existe, e aqui o post não desaparece como em
     * `deleteItem`: ele muda de data a pedido da cliente, inteiro, com o
     * movimento registrado no `activity_logs`.
     *
     * @return array{success:bool,plan_id?:int,created_plan?:bool,moved?:bool,week_start?:string,error?:string}
     */
    public function rescheduleItem(
        int $itemId,
        int $agencyId,
        string $newDate,
        ?string $newTime = null,
        ?int $userId = null,
        bool $timeProvided = false,
    ): array {
        $newDate = trim($newDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || !strtotime($newDate)) {
            return ['success' => false, 'error' => 'Data inválida.'];
        }

        $item = $this->repo->findItem($itemId, $agencyId);
        if (!$item) {
            return ['success' => false, 'error' => 'Post não encontrado.'];
        }

        $sourcePlan = $this->repo->findByIdFull((int) $item['content_plan_id'], $agencyId);
        if (!$sourcePlan) {
            return ['success' => false, 'error' => 'Plano de origem não encontrado.'];
        }

        $fields = ['publish_date' => $newDate];
        if ($timeProvided) {
            $time = trim((string) $newTime);
            if ($time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                return ['success' => false, 'error' => 'Horário inválido.'];
            }
            $fields['publish_time'] = $time === '' ? null : substr($time, 0, 5);
        }

        $clientId = (int) $sourcePlan['client_id'];

        // Continua dentro da semana do próprio plano: é só trocar a data.
        if (!empty($sourcePlan['week_start']) && !empty($sourcePlan['week_end'])
            && $newDate >= $sourcePlan['week_start'] && $newDate <= $sourcePlan['week_end']) {
            $this->repo->updateItem($itemId, $fields);
            ActivityLogger::log('content_item.rescheduled', 'content', $userId, $clientId, [
                'item_id' => $itemId,
                'plan_id' => (int) $sourcePlan['id'],
                'from'    => $item['publish_date'] ?? null,
                'to'      => $newDate,
                'moved'   => false,
            ]);

            return [
                'success'      => true,
                'plan_id'      => (int) $sourcePlan['id'],
                'created_plan' => false,
                'moved'        => false,
                'week_start'   => (string) $sourcePlan['week_start'],
            ];
        }

        $targetMonday = self::mondayOf($newDate);
        $targetPlan   = $this->repo->findByClientWeek($clientId, $agencyId, $targetMonday);

        // Plano legado com semana fora do padrão seg–dom pode cair em si mesmo.
        if ($targetPlan && (int) $targetPlan['id'] === (int) $sourcePlan['id']) {
            $this->repo->updateItem($itemId, $fields);
            return [
                'success'      => true,
                'plan_id'      => (int) $sourcePlan['id'],
                'created_plan' => false,
                'moved'        => false,
                'week_start'   => $targetMonday,
            ];
        }

        // Daqui para baixo o post TROCA de plano — inclusive saindo de semana
        // já aprovada, que é exatamente o pedido que o CONT-07 atende ("a
        // cliente aprovou e quer publicar semana que vem"). Bloquear a origem
        // aprovada deixava o plano fechado sem nenhuma saída e devolvia o
        // retrabalho manual de recriar o post; o post não se perde, muda de
        // data levando tudo e o movimento fica no `activity_logs`. Quem recusa
        // é a semana de DESTINO aprovada, logo abaixo.
        $createdPlan = false;

        if (!$targetPlan) {
            $targetId = $this->repo->createPlan([
                'agency_id'  => $agencyId,
                'client_id'  => $clientId,
                'title'      => self::defaultTitle((string) ($sourcePlan['client_name'] ?? ''), $targetMonday),
                'week_start' => $targetMonday,
                'week_end'   => self::sundayOf($targetMonday),
                'status'     => 'draft',
                'created_by' => $userId,
            ]);
            $createdPlan = true;

            ActivityLogger::log('content_plan.created_for_reschedule', 'content', $userId, $clientId, [
                'plan_id'        => $targetId,
                'source_plan_id' => (int) $sourcePlan['id'],
                'week_start'     => $targetMonday,
            ]);
        } else {
            if (($targetPlan['status'] ?? '') === 'approved') {
                $from = date('d/m', (int) strtotime($targetMonday));
                $to   = date('d/m', (int) strtotime(self::sundayOf($targetMonday)));
                return [
                    'success' => false,
                    'error'   => "A semana de destino ({$from} a {$to}) já foi aprovada. "
                               . 'Escolha outra data ou reabra a semana em Aprovações.',
                ];
            }
            $targetId = (int) $targetPlan['id'];
        }

        $fields['sort_order'] = $this->repo->maxSortOrder($targetId) + 1;
        $this->repo->moveItemToPlan($itemId, $targetId, $fields);

        ActivityLogger::log('content_item.rescheduled', 'content', $userId, $clientId, [
            'item_id'        => $itemId,
            'source_plan_id' => (int) $sourcePlan['id'],
            'plan_id'        => $targetId,
            'from'           => $item['publish_date'] ?? null,
            'to'             => $newDate,
            'created_plan'   => $createdPlan,
            'moved'          => true,
        ]);

        return [
            'success'      => true,
            'plan_id'      => $targetId,
            'created_plan' => $createdPlan,
            'moved'        => true,
            'week_start'   => $targetMonday,
        ];
    }

    /** @throws \InvalidArgumentException plano já aprovado pela cliente */
    public function deleteItem(int $itemId, int $agencyId): bool
    {
        $item = $this->repo->findItem($itemId, $agencyId);
        if (!$item) return false;

        $plan = $this->get((int) $item['content_plan_id'], $agencyId);
        if ($plan) $this->assertPlanOpenForItems($plan);

        $ok = $this->repo->deleteItem($itemId, $agencyId);
        if ($ok) ActivityLogger::log('content_item.deleted', 'content', null, null, ['item_id' => $itemId]);
        return $ok;
    }

    // ── Feedback / Approval ────────────────────────────────────────────────────

    /**
     * `$agencyId` só é necessário quando a chamada vem do portal: lá não há
     * sessão, e sem ele a trilha do feedback cai na agência 0 (SEC-15). Pela
     * tela interna o `ActivityLogger` resolve a agência pela sessão.
     */
    public function addFeedback(
        int $itemId, int $planId, int $clientId, ?int $userId, string $type, ?string $comment,
        ?int $timecodeSeconds = null, string $source = 'client', ?int $agencyId = null
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

        ActivityLogger::log('approval.feedback_added', 'approvals', null, $clientId, ['item_id' => $itemId, 'type' => $type], $agencyId);

        if ($type === 'approved') {
            $this->maybeApprovePlanFromItems($planId, $clientId, $source === 'agency' ? $userId : null);
        }

        return $id;
    }

    /**
     * Quando o último criativo pendente é aprovado, o plano inteiro passa a
     * aprovado — sem exigir que o cliente clique também em "Aprovar Tudo".
     */
    private function maybeApprovePlanFromItems(int $planId, int $clientId, ?int $approvedBy): void
    {
        $summary = $this->repo->getItemStatusSummary($planId);
        $total   = array_sum($summary);

        if ($total === 0 || ($summary['approved'] ?? 0) !== $total) return;

        // Quem decide se a transição vale é `approvePlan`: a via item a item
        // não pode fechar plano que não está esperando decisão da cliente
        // (rascunho, em revisão, rejeitado ou já aprovado) — era o atalho que
        // contornava o guard do portal.
        $this->approvePlan($planId, $clientId, $approvedBy);
    }

    /**
     * Reabre a semana fechada para a equipe corrigir e reenviar (BUG-08).
     *
     * O plano volta a 'revision', o mesmo estado de quando a cliente pede
     * alteração: dali em diante vale o fluxo normal, e só o reenvio o coloca de
     * novo na mesa dela. Os posts aprovados continuam aprovados, porque a decisão
     * sobre eles não mudou. Tarefas e rascunho da semana seguinte não se repetem
     * na nova aprovação: as duas automações são idempotentes por plano.
     *
     * O motivo vai para a cliente e fica nas notas do plano e na trilha.
     */
    public function reopen(int $planId, int $agencyId, string $reason): bool
    {
        $plan   = $this->get($planId, $agencyId);
        $reason = trim($reason);

        if (!$plan || $reason === '' || !in_array((string) $plan['status'], self::REOPENABLE, true)) {
            return false;
        }

        $current = is_string($plan['notes'] ?? null) ? trim($plan['notes']) : '';
        $header  = t('content.reopen_note_header', ['date' => date('d/m/Y H:i')]);

        $affected = $this->repo->updatePlan($planId, $agencyId, [
            'status'      => 'revision',
            'approved_at' => null,
            'approved_by' => null,
            'notes'       => ($current !== '' ? $current . "\n\n" : '') . $header . "\n" . $reason,
        ]);

        ActivityLogger::log('content_plan.reopened', 'approvals', null, (int) $plan['client_id'], [
            'plan_id'     => $planId,
            'from_status' => $plan['status'],
            'reason'      => $reason,
        ]);

        if ($affected > 0) {
            $this->notifications?->notifyEvent('plan.reopened', $agencyId, [
                'plan_id'      => $planId,
                'plan_title'   => $plan['title'],
                'reason'       => $reason,
                'client'       => $this->clientRepo?->findByIdAndAgency((int) $plan['client_id'], $agencyId),
                'approval_url' => self::portalPlanUrl($plan['client_portal_token'] ?? null, $planId),
            ]);
        }

        return $affected > 0;
    }

    /**
     * Fecha a semana: o plano vira 'approved' **e os posts pendentes também**.
     *
     * Aprovar o plano gravava só a linha do plano. Como as telas leem as duas
     * coisas, elas se contradiziam: selo "Aprovado" ao lado de "0/3 aprovados"
     * em Conteúdo e Aprovações, e cada post ainda "Pendente" no portal. Depois
     * do BUG-04/BUG-05 isso virava permanente — feedback de item em plano
     * aprovado responde 409 e `approvePlan` recusa reaprovar, então não havia
     * como reconciliar pela tela.
     *
     * Pendente = 'draft', o post que ninguém decidiu. O que está em revisão ou
     * rejeitado NÃO é sobrescrito: o pedido de alteração é decisão registrada
     * de quem viu o post, e apagá-lo mandava a capa reprovada para o ar sem
     * ninguém notar (a cliente também não consegue repetir o pedido depois,
     * porque feedback de item em plano aprovado responde 409). O plano fica
     * "Aprovado — 2/3", que é a verdade: semana fechada, um post a refazer.
     *
     * `$approvedBy` é quem da equipe aprovou em nome do cliente; nulo quando foi
     * o próprio cliente, no portal. O sistema não pode afirmar que ele aprovou
     * o que a equipe marcou. A coluna só entra na gravação quando há valor: até
     * a migration rodar no deploy, a aprovação do portal segue funcionando.
     */
    public function approvePlan(int $planId, int $clientId, ?int $approvedBy = null): bool
    {
        $plan = $this->repo->findByIdForClient($planId, $clientId);
        if (!$plan) return false;

        // Só o plano que está na mesa da cliente vira aprovado. Sem isto, um
        // "Aprovar Tudo" repetido (ou numa aba velha) regravava `approved_at`,
        // re-disparava a confirmação de aprovação para a cliente e re-rodava as
        // automações do plano; e plano em revisão virava aprovado sem reenvio.
        if (!in_array((string) $plan['status'], self::AWAITING_CLIENT, true)) return false;

        $agencyId = (int) $plan['agency_id'];
        $fields = ['status' => 'approved', 'approved_at' => date('Y-m-d H:i:s')];
        if ($approvedBy !== null) {
            $fields['approved_by'] = $approvedBy;
        }

        $affected = $this->repo->updatePlan($planId, $agencyId, $fields);
        $itemsApproved = $affected > 0 ? $this->repo->approvePendingItems($planId) : 0;

        // A agência vai explícita: aprovar vem do portal, onde não há sessão —
        // sem ela a linha nasce em `agency_id = 0` e a aprovação da semana
        // desaparece da trilha da agência dona (SEC-15).
        ActivityLogger::log('content_plan.approved', 'approvals', null, $clientId, [
            'plan_id'        => $planId,
            'items_approved' => $itemsApproved,
            'approved_by'    => $approvedBy,
        ], $agencyId);

        if ($affected > 0) {
            $client = $this->clientRepo?->findByIdAndAgency($clientId, $agencyId);
            $this->notifications?->notifyEvent('plan.approved', $agencyId, [
                'plan_id'             => $planId,
                'plan_title'          => $plan['title'],
                'client'              => $client,
                'responsible_user_id' => $plan['created_by'] ?? null,
                'approved_by'         => $approvedBy,
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
            $result = $this->duplicate($planId, $agencyId, $createdBy, ['week_start' => $nextMonday], byAutomation: true);
            $newId  = (int) ($result['id'] ?? 0);
            if (!$newId) return;
        }

        $this->automations->markRan($agencyId, $clientId, $key, $dedupe, 'done', 'inapp', "Plano {$newId} criado");
        // Mesma razão de `approvePlan`: quem dispara é a aprovação da cliente no
        // portal, sem sessão — sem a agência explícita o plano da semana
        // seguinte nascia na agência 0, fora da trilha de quem é dono dele.
        ActivityLogger::log('content_plan.auto_created', 'content', null, $clientId, [
            'source_plan_id' => $planId,
            'new_plan_id'    => $newId,
        ], $agencyId);

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

        // Mesma régua da aprovação: pedir revisão é a resposta ao plano que
        // está na mesa da cliente. Plano já aprovado não volta para revisão por
        // POST solto — reabrir semana fechada re-notificava a cliente e deixava
        // `approved_at` preenchido num plano "em revisão".
        if (!in_array((string) $plan['status'], self::AWAITING_CLIENT, true)) return false;

        $agencyId = (int) $plan['agency_id'];
        $note     = trim($note);
        $fields   = ['status' => 'revision'];

        // `notes` é o MESMO campo do "Observações para a equipe" do formulário
        // do plano. Gravar a nota de revisão em cima dele apagava o que o gestor
        // escreveu — e com a nota vazia (o modal não exige texto) apagava sem
        // pôr nada no lugar. Agora acrescenta, datada, e nota vazia não toca no
        // campo.
        if ($note !== '') {
            $current = is_string($plan['notes'] ?? null) ? trim($plan['notes']) : '';
            $header  = t('content.revision_note_header', ['date' => date('d/m/Y H:i')]);

            $fields['notes'] = ($current !== '' ? $current . "\n\n" : '') . $header . "\n" . $note;
        }

        $affected = $this->repo->updatePlan($planId, $agencyId, $fields);
        // Mesma razão da aprovação: sem a agência explícita o pedido do portal
        // cai na agência 0.
        ActivityLogger::log('content_plan.revision_requested', 'approvals', null, $clientId, ['plan_id' => $planId], $agencyId);

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

    /**
     * Classes do quadro do player de vídeo embutido (Drive/YouTube).
     * Reels/Story são 9:16 — num quadro fixo 16:9 o player corta o vídeo
     * vertical no mobile. A largura máxima segura o quadro 9:16 para ele
     * não estourar a altura da tela no desktop; no celular vira full-width.
     * 'reels' minúsculo cobre dado legado que o portal já aceita.
     *
     * $fileType: link genérico do Drive (/file/d/…) não revela o tipo e vira
     * 'file' — nesse caso a proporção segue o tipo do post. Só arquivos
     * claramente não-vídeo (documento, planilha, pasta…) forçam 16:9.
     */
    public static function videoFrameClass(?string $contentType, ?string $fileType = null): string
    {
        if ($fileType !== null && !in_array($fileType, ['video', 'file'], true)) {
            return 'aspect-video';
        }

        $vertical = in_array($contentType, ['Reels / Vídeo', 'reels', 'Story'], true);

        return $vertical
            ? 'aspect-[9/16] w-full max-w-[22rem] mx-auto'
            : 'aspect-video';
    }
}
