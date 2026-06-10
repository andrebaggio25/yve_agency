<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ContentPlanService;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;

class ContentPlanController
{
    public function __construct(
        private readonly ContentPlanService $service,
        private readonly ClientRepository   $clients,
        private readonly UserRepository     $users,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('content.view');

        $agencyId = Auth::agencyId();
        $filters  = [
            'client_id'  => $request->query('client_id'),
            'status'     => $request->query('status'),
            'week_start' => $request->query('week_start'),
        ];

        $plans      = $this->service->list((int) $agencyId, $filters);
        $clientList = Auth::can('clients.view_all')
            ? $this->clients->findByAgency((int) $agencyId)
            : $this->clients->findByUserAccess((int) Auth::id(), (int) $agencyId);

        return Response::view('content/index', compact('plans', 'clientList', 'filters'));
    }

    public function show(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.view');

        $agencyId = (int) Auth::agencyId();
        $plan     = $this->service->getWithItems($planId, $agencyId);

        if (!$plan) return Response::notFound('Plano não encontrado.');

        $teamMembers = $this->users->findByAgency($agencyId);

        return Response::view('content/show', compact('plan', 'teamMembers'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $agencyId   = (int) Auth::agencyId();
        $clientList = $this->clients->findByUserAccess((int) Auth::id(), $agencyId);

        return Response::view('content/create', compact('clientList'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $agencyId = (int) Auth::agencyId();
        $input    = $request->only(['client_id', 'title', 'week_start', 'week_end', 'notes']);

        if (empty($input['client_id']) || empty($input['title']) || empty($input['week_start'])) {
            flash('error', 'Preencha os campos obrigatórios.');
            return Response::redirect('/conteudo/criar');
        }

        $id = $this->service->create($input, $agencyId, (int) Auth::id());
        flash('success', 'Plano de conteúdo criado com sucesso!');
        return Response::redirect("/conteudo/{$id}");
    }

    public function edit(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.edit');

        $agencyId = (int) Auth::agencyId();
        $plan     = $this->service->get($planId, $agencyId);
        if (!$plan) return Response::notFound('Plano não encontrado.');

        $clientList = $this->clients->findByUserAccess((int) Auth::id(), $agencyId);
        return Response::view('content/edit', compact('plan', 'clientList'));
    }

    public function update(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.edit');

        $agencyId = (int) Auth::agencyId();
        $input    = $request->only(['title', 'week_start', 'week_end', 'notes']);
        $this->service->update($planId, $agencyId, $input);

        flash('success', 'Plano atualizado.');
        return Response::redirect("/conteudo/{$planId}");
    }

    public function sendToApproval(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.send_to_approval');

        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->send($planId, $agencyId);

        if ($request->wantsJson()) {
            return Response::json(['success' => $ok]);
        }

        flash($ok ? 'success' : 'error', $ok ? 'Plano enviado para aprovação!' : 'Não foi possível enviar o plano.');
        return Response::redirect("/conteudo/{$planId}");
    }

    public function destroy(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.delete');

        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->delete($planId, $agencyId);

        if ($request->wantsJson()) {
            return Response::json(['success' => $ok]);
        }

        flash($ok ? 'success' : 'error', $ok ? 'Plano excluído.' : 'Não é possível excluir planos aprovados.');
        return Response::redirect('/conteudo');
    }

    // ── Items ──────────────────────────────────────────────────────────────────

    public function storeItem(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.create');

        $agencyId = (int) Auth::agencyId();
        $input    = $request->only([
            'publish_date', 'publish_time', 'content_type', 'title', 'theme',
            'caption', 'script', 'cta', 'drive_url', 'assigned_to', 'sort_order',
        ]);

        try {
            $id = $this->service->addItem($planId, $agencyId, $input);
            if ($request->wantsJson()) {
                return Response::json(['success' => true, 'id' => $id]);
            }
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }

        return Response::redirect("/conteudo/{$planId}");
    }

    public function updateItem(Request $request, int $planId, int $itemId): Response
    {
        Auth::requirePermission('content.edit');

        $agencyId = (int) Auth::agencyId();
        $input    = $request->only([
            'publish_date', 'publish_time', 'content_type', 'title', 'theme',
            'caption', 'script', 'cta', 'drive_url', 'assigned_to', 'status', 'sort_order',
        ]);

        $ok = $this->service->updateItem($itemId, $agencyId, $input);

        return Response::json(['success' => $ok]);
    }

    public function destroyItem(Request $request, int $planId, int $itemId): Response
    {
        Auth::requirePermission('content.delete');

        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->deleteItem($itemId, $agencyId);

        return Response::json(['success' => $ok]);
    }

    public function reorderItems(Request $request, int $planId): Response
    {
        Auth::requirePermission('content.edit');

        $ids = $request->json('ids', []);
        if (!is_array($ids)) return Response::json(['success' => false], 422);

        $this->service->reorderItems($planId, $ids);
        return Response::json(['success' => true]);
    }
}
