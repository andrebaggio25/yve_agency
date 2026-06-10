<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ContentPlanRepository;
use App\Services\ContentPlanService;
use App\Support\Auth;

class ApprovalController
{
    public function __construct(
        private readonly ContentPlanService    $service,
        private readonly ContentPlanRepository $repo,
    ) {}

    /** Client-facing: list plans awaiting approval */
    public function index(Request $request): Response
    {
        Auth::requirePermission('approvals.view');

        $clientIds = $_SESSION['client_ids'] ?? [];
        $agencyId  = (int) Auth::agencyId();
        $plans     = [];

        foreach ($clientIds as $clientId) {
            $clientPlans = $this->repo->allByClient((int) $clientId, $agencyId);
            $plans       = array_merge($plans, $clientPlans);
        }

        // Sort by week_start desc
        usort($plans, fn($a, $b) => strcmp($b['week_start'], $a['week_start']));

        return Response::view('approvals/index', compact('plans'));
    }

    /** Client-facing: show a single plan for approval */
    public function show(Request $request, int $planId): Response
    {
        Auth::requirePermission('approvals.view');

        $clientIds = $_SESSION['client_ids'] ?? [];
        $agencyId  = (int) Auth::agencyId();

        // Find which client this plan belongs to
        $plan = null;
        foreach ($clientIds as $clientId) {
            $found = $this->repo->findByIdForClient($planId, (int) $clientId);
            if ($found) { $plan = $found; break; }
        }

        if (!$plan) return Response::notFound('Plano não encontrado.');

        $items = $this->repo->getItems($planId);
        foreach ($items as &$item) {
            $item['feedbacks'] = $this->repo->getFeedbacks((int) $item['id']);
        }
        unset($item);

        $plan['items'] = $items;

        return Response::view('approvals/show', compact('plan'));
    }

    /** Submit feedback on a single item */
    public function feedback(Request $request, int $planId, int $itemId): Response
    {
        Auth::requirePermission('approvals.comment');

        $clientIds = $_SESSION['client_ids'] ?? [];
        $userId    = (int) Auth::id();
        $agencyId  = (int) Auth::agencyId();

        $clientId = null;
        foreach ($clientIds as $cid) {
            $found = $this->repo->findByIdForClient($planId, (int) $cid);
            if ($found) { $clientId = (int) $cid; break; }
        }

        if (!$clientId) {
            return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }

        $type    = $request->input('feedback_type', 'comment');
        $comment = $request->input('comment');

        $allowed = ['approved', 'changes_requested', 'rejected', 'comment'];
        if (!in_array($type, $allowed, true)) {
            return Response::json(['success' => false, 'error' => 'Tipo inválido.'], 422);
        }

        if ($type !== 'comment' && !Auth::can("approvals.{$type}")) {
            return Response::json(['success' => false, 'error' => 'Sem permissão.'], 403);
        }

        $fbId = $this->service->addFeedback($itemId, $planId, $clientId, $userId, $type, $comment);

        return Response::json(['success' => true, 'feedback_id' => $fbId]);
    }

    /** Approve entire plan */
    public function approvePlan(Request $request, int $planId): Response
    {
        Auth::requirePermission('approvals.approve');

        $clientIds = $_SESSION['client_ids'] ?? [];
        foreach ($clientIds as $clientId) {
            $ok = $this->service->approvePlan($planId, (int) $clientId);
            if ($ok) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => true]);
                }
                flash('success', 'Plano aprovado com sucesso!');
                return Response::redirect("/aprovacoes/{$planId}");
            }
        }

        return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
    }

    /** Request revision on entire plan */
    public function requestRevision(Request $request, int $planId): Response
    {
        Auth::requirePermission('approvals.approve');

        $clientIds = $_SESSION['client_ids'] ?? [];
        $note      = $request->input('note', '');

        foreach ($clientIds as $clientId) {
            $ok = $this->service->requestRevision($planId, (int) $clientId, $note);
            if ($ok) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => true]);
                }
                flash('success', 'Solicitação de revisão enviada.');
                return Response::redirect("/aprovacoes/{$planId}");
            }
        }

        return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
    }
}
