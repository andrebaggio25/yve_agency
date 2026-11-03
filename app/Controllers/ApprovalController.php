<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Services\ContentPlanService;
use App\Support\Auth;

class ApprovalController extends Controller
{
    /**
     * Tipo de feedback → permissão que ele exige.
     *
     * Antes o código montava `approvals.{$type}`, o que pedia
     * `approvals.approved|changes_requested|rejected` — três slugs que não
     * existem em `config/permissions.php`. Ou seja: nenhum papel aprovava item
     * pela tela interna, nem o perfil Cliente — Aprovador.
     */
    private const FEEDBACK_PERMISSION = [
        'approved'          => 'approvals.approve',
        'changes_requested' => 'approvals.comment',
        'rejected'          => 'approvals.reject',
        'comment'           => 'approvals.comment',
    ];

    public function __construct(
        private readonly ContentPlanService $service,
        private readonly ClientRepository   $clients,
        private readonly UserRepository     $users,
    ) {}

    /** List plans awaiting approval */
    public function index(Request $request): Response
    {
        Auth::requirePermission('approvals.view');

        $reachable = Auth::reachableClientIds();
        $plans     = $this->service->listForApproval((int) Auth::agencyId(), $reachable);

        // Perfil sem alcance de cliente veria "Tudo em dia!" para sempre — a
        // tela passa a explicar o que pedir ao administrador (como em Conteúdo).
        $noClientReach = $reachable === [];

        return $this->view('approvals.index', compact('plans', 'noClientReach'));
    }

    /** Show a single plan for approval */
    public function show(Request $request): Response
    {
        Auth::requirePermission('approvals.view');

        $agencyId = (int) Auth::agencyId();
        $plan     = $this->reachablePlan((int) $request->param('planId'), $agencyId, withItems: true);

        if (!$plan) {
            return $this->view('errors.404', [], 404);
        }

        // A tela de aprovação fala o idioma do cliente (clients.language).
        $clientRow = $this->clients->findByIdAndAgency((int) $plan['client_id'], $agencyId);
        if ($clientRow && !empty($clientRow['language'])) {
            Lang::setLocale($clientRow['language']);
        }

        // Sem JOIN na query da tela: até a migration rodar no deploy, a coluna
        // `approved_by` não existe e esta tela precisa continuar abrindo.
        $approver       = !empty($plan['approved_by']) ? $this->users->findByIdAndAgency((int) $plan['approved_by'], $agencyId) : null;
        $approvedByName = $approver['name'] ?? null;

        return $this->view('approvals.show', compact('plan', 'approvedByName'));
    }

    /** Submit feedback on a single item */
    public function feedback(Request $request): Response
    {
        // Aprovar item não exige a permissão de comentar (e vice-versa): o
        // tipo do feedback decide, logo abaixo.
        Auth::requireAnyPermission('approvals.comment', 'approvals.approve', 'approvals.reject');

        $agencyId = (int) Auth::agencyId();
        $planId   = (int) $request->param('planId');
        $itemId   = (int) $request->param('itemId');

        $plan = $this->reachablePlan($planId, $agencyId);
        if (!$plan) {
            return Response::json(['success' => false, 'error' => t('approval.plan_not_found')], 404);
        }

        // Plano fechado não recebe mais feedback de item. A view já esconde o
        // formulário quando o plano está aprovado, mas só a view: uma aba
        // aberta de antes da aprovação (ou um "Aprovar Tudo" de um colega no
        // meio) punha um post em revisão dentro de um plano "Aprovado" — a
        // tela mostrava "0/1 aprovados" ao lado do selo de aprovado e o
        // cliente via alteração pedida num plano que ele já tinha aprovado.
        if ($plan['status'] === 'approved') {
            return Response::json(['success' => false, 'error' => t('approval.plan_closed')], 409);
        }

        $item = $this->service->getItem($itemId, $agencyId);
        if (!$item || (int) $item['content_plan_id'] !== $planId) {
            return Response::json(['success' => false, 'error' => t('approval.item_not_found')], 404);
        }

        // Sem cast: `feedback_type[]=x` no POST viraria "Array" com warning.
        $type    = $request->input('feedback_type', 'comment');
        $comment = $request->input('comment');

        if (!is_string($type)) {
            return Response::json(['success' => false, 'error' => t('approval.invalid_type')], 422);
        }

        if (!isset(self::FEEDBACK_PERMISSION[$type])) {
            return Response::json(['success' => false, 'error' => t('approval.invalid_type')], 422);
        }

        if (!Auth::isPlatformAdmin() && !Auth::can(self::FEEDBACK_PERMISSION[$type])) {
            return Response::json(['success' => false, 'error' => t('approval.no_permission')], 403);
        }

        // `source: 'agency'` — quem responde aqui é a equipe, não o cliente.
        // Gravando 'client' (o padrão do método), a anotação interna ia para o
        // portal assinada com o nome do próprio cliente: o `client_id` fica
        // preenchido e o template do portal lia `client_name` primeiro. Agora o
        // portal lista só o que veio dele (`getFeedbacks(clientVisibleOnly:)`).
        $fbId = $this->service->addFeedback(
            $itemId,
            $planId,
            (int) $plan['client_id'],
            (int) Auth::id(),
            $type,
            is_string($comment) ? $comment : null,
            source: 'agency',
        );

        return Response::json(['success' => true, 'feedback_id' => $fbId]);
    }

    /** Approve entire plan */
    public function approvePlan(Request $request): Response
    {
        Auth::requirePermission('approvals.approve');

        $planId = (int) $request->param('planId');
        $plan   = $this->reachablePlan($planId, (int) Auth::agencyId());

        if (!$plan) {
            return $this->planNotFound($request);
        }

        if (!$this->service->approvePlan($planId, (int) $plan['client_id'], (int) Auth::id())) {
            return $this->actionFailed($request, $planId);
        }

        if ($request->wantsJson()) {
            return Response::json(['success' => true]);
        }

        $this->withSuccess(t('approval.plan_approved'));
        return $this->redirect("/aprovacoes/{$planId}");
    }

    /** Request revision on entire plan */
    public function requestRevision(Request $request): Response
    {
        Auth::requirePermission('approvals.approve');

        $planId = (int) $request->param('planId');
        $plan   = $this->reachablePlan($planId, (int) Auth::agencyId());

        if (!$plan) {
            return $this->planNotFound($request);
        }

        $note = $request->input('note', '');

        if (!$this->service->requestRevision($planId, (int) $plan['client_id'], is_string($note) ? $note : '')) {
            return $this->actionFailed($request, $planId);
        }

        if ($request->wantsJson()) {
            return Response::json(['success' => true]);
        }

        $this->withSuccess(t('approval.revision_sent'));
        return $this->redirect("/aprovacoes/{$planId}");
    }

    /** Reopen a closed plan so the team can fix it and send it again */
    public function reopenPlan(Request $request): Response
    {
        Auth::requirePermission('approvals.approve');

        $planId = (int) $request->param('planId');
        $plan   = $this->reachablePlan($planId, (int) Auth::agencyId());

        if (!$plan) {
            return $this->planNotFound($request);
        }

        $reason = $request->input('reason', '');
        $reason = is_string($reason) ? trim($reason) : '';

        if ($reason === '') {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'error' => t('approval.reopen_reason_required')], 422);
            }

            return $this->withError(t('approval.reopen_reason_required'))->redirect("/aprovacoes/{$planId}");
        }

        if (!$this->service->reopen($planId, (int) Auth::agencyId(), $reason)) {
            return $this->actionFailed($request, $planId);
        }

        if ($request->wantsJson()) {
            return Response::json(['success' => true]);
        }

        $this->withSuccess(t('approval.reopened'));
        return $this->redirect("/aprovacoes/{$planId}");
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Plano dentro da agência **e** do alcance do usuário (SEC-11), já enviado.
     *
     * Antes isto varria `$_SESSION['client_ids']`, que só guarda vínculo
     * explícito de `client_user_access`: quem alcança a carteira por
     * `clients.view_all` (social media, designer, gestor de tráfego) via
     * "Tudo em dia!" com plano parado esperando e levava 403 ao aprovar.
     * Fora do alcance responde 404, a mesma régua da tela de Conteúdo.
     *
     * @return array<string,mixed>|null
     */
    private function reachablePlan(int $planId, int $agencyId, bool $withItems = false): ?array
    {
        $plan = $this->service->planForApproval($planId, $agencyId, $withItems);

        return $plan && Auth::canAccessClient((int) $plan['client_id']) ? $plan : null;
    }

    /** Negativa padrão: JSON no fetch, 404 na tela. */
    private function planNotFound(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['success' => false, 'error' => t('approval.plan_not_found')], 404);
        }

        return $this->view('errors.404', [], 404);
    }

    /**
     * O plano existe e é alcançável, mas a transição não valeu. Sem `back()`:
     * redirecionar pelo Referer é header de cliente em decisão de navegação.
     */
    private function actionFailed(Request $request, int $planId): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['success' => false, 'error' => t('approval.action_failed')], 422);
        }

        return $this->withError(t('approval.action_failed'))->redirect("/aprovacoes/{$planId}");
    }
}
