<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContentPlanService;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;

class ContentPlanController extends Controller
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
        $clientList = $this->accessibleClients((int) $agencyId);

        // Radar: clientes ativos sem plano na PRÓXIMA semana — o lembrete que
        // evita segunda-feira sem pauta. (A auto-criação cobre quem tem a
        // automação ligada; aqui aparece o que sobrou.)
        $nextMonday  = ContentPlanService::mondayOf(date('Y-m-d', strtotime('+7 days')));
        $withPlan    = $this->service->clientIdsWithPlanForWeek((int) $agencyId, $nextMonday);
        $radarPending = array_values(array_filter(
            $clientList,
            fn($c) => !in_array((int) $c['id'], $withPlan, true)
        ));

        return $this->view('content.index', compact('plans', 'clientList', 'filters', 'radarPending', 'nextMonday'));
    }

    /**
     * Clientes que o usuário pode escolher. Quem tem clients.view_all enxerga a
     * agência inteira; os demais, só os clientes com acesso explícito.
     */
    private function accessibleClients(int $agencyId): array
    {
        return Auth::can('clients.view_all')
            ? $this->clients->findByAgency($agencyId)
            : $this->clients->findByUserAccess((int) Auth::id(), $agencyId);
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('content.view');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $plan     = $this->service->getWithItems($planId, $agencyId);

        if (!$plan) return $this->view('errors.404', [], 404);

        $teamMembers = $this->users->findByAgency($agencyId);

        return $this->view('content.show', compact('plan', 'teamMembers'));
    }

    /**
     * Calendário do mês (PROD-04) — a visão que faltava.
     *
     * Ninguém planeja conteúdo em lista: planeja olhando o mês, onde buraco e
     * acúmulo ficam evidentes. Os dados já existiam; faltava a forma de ver.
     */
    public function calendar(Request $request): Response
    {
        Auth::requirePermission('content.view');

        $agencyId = (int) Auth::agencyId();

        // Mês pedido (YYYY-MM), com o mês atual como padrão. Valor inválido cai
        // no padrão em vez de quebrar a tela.
        $monthParam = (string) $request->query('month', '');
        $month      = preg_match('/^\d{4}-\d{2}$/', $monthParam) ? $monthParam : date('Y-m');

        $first = $month . '-01';
        $last  = date('Y-m-t', strtotime($first));

        $filters  = ['client_id' => $request->query('client_id')];
        $items    = $this->service->itemsBetween($agencyId, $first, $last, array_filter($filters));

        // Agrupa por dia — a view só desenha.
        $byDay = [];
        foreach ($items as $item) {
            $byDay[(string) $item['publish_date']][] = $item;
        }

        return $this->view('content.calendar', [
            'month'      => $month,
            'monthLabel' => $this->monthLabel($first),
            'prevMonth'  => date('Y-m', strtotime($first . ' -1 month')),
            'nextMonth'  => date('Y-m', strtotime($first . ' +1 month')),
            'firstDay'   => $first,
            'lastDay'    => $last,
            'byDay'      => $byDay,
            'clientList' => $this->accessibleClients($agencyId),
            'filters'    => $filters,
            'total'      => count($items),
        ]);
    }

    private function monthLabel(string $date): string
    {
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        return $meses[(int) date('n', strtotime($date))] . ' de ' . date('Y', strtotime($date));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $agencyId   = (int) Auth::agencyId();
        $clientList = $this->accessibleClients($agencyId);
        // Pré-seleção vinda do radar ("criar plano para o cliente X").
        $preselect  = (int) $request->query('client_id', 0);

        return $this->view('content.create', compact('clientList', 'preselect'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $agencyId = (int) Auth::agencyId();
        // Título vazio ganha o nome padrão "CLIENTE | dd/mm – dd/mm" no service.
        $input    = $request->only(['client_id', 'title', 'week_start', 'notes', 'apply_template']);

        if (empty($input['client_id']) || empty($input['week_start'])) {
            $this->withError('Preencha os campos obrigatórios.');
            return $this->redirect('/conteudo/criar');
        }

        $allowedIds = array_map(fn($c) => (int) $c['id'], $this->accessibleClients($agencyId));
        if (!in_array((int) $input['client_id'], $allowedIds, true)) {
            $this->withError('Cliente inválido.');
            return $this->redirect('/conteudo/criar');
        }

        $id = $this->service->create($input, $agencyId, (int) Auth::id());
        $this->withSuccess('Plano de conteúdo criado com sucesso!');
        return $this->redirect("/conteudo/{$id}");
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('content.edit');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $plan     = $this->service->get($planId, $agencyId);

        if (!$plan) return $this->view('errors.404', [], 404);

        $clientList = $this->accessibleClients($agencyId);
        return $this->view('content.edit', compact('plan', 'clientList'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('content.edit');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $input    = $request->only(['title', 'week_start', 'notes']);
        $this->service->update($planId, $agencyId, $input);

        $this->withSuccess('Plano atualizado.');
        return $this->redirect("/conteudo/{$planId}");
    }

    public function sendToApproval(Request $request): Response
    {
        Auth::requirePermission('content.send_to_approval');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->send($planId, $agencyId);

        if ($request->wantsJson()) {
            return Response::json(['success' => $ok]);
        }

        if ($ok) {
            $this->withSuccess('Plano enviado para aprovação!');
        } else {
            $this->withError('Não foi possível enviar o plano.');
        }
        return $this->redirect("/conteudo/{$planId}");
    }

    /**
     * Duplica o plano (PROD-05) — o atalho para não refazer o mês do zero.
     * A cópia nasce em rascunho, na semana seguinte, sem herdar aprovação.
     */
    public function duplicate(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();

        $result = $this->service->duplicate($planId, $agencyId, (int) Auth::id(), [
            'week_start' => $request->post('week_start') ?: null,
            'title'      => $request->post('title') ?: null,
        ]);

        if (!$result['success']) {
            $this->withError((string) ($result['error'] ?? 'Não foi possível duplicar o plano.'));
            return $this->redirect('/conteudo/' . $planId);
        }

        $this->withSuccess('Plano duplicado. Ajuste as datas e o conteúdo antes de enviar.');
        return $this->redirect('/conteudo/' . $result['id']);
    }

    /**
     * "Salvar como modelo do cliente": captura a estrutura do plano atual
     * (dia da semana, hora, plataforma, formato, responsável) como padrão —
     * substitui o modelo anterior, se houver.
     */
    public function saveTemplate(Request $request): Response
    {
        Auth::requirePermission('content.edit');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->saveTemplateFromPlan($planId, $agencyId, (int) Auth::id());

        if ($ok) {
            $this->withSuccess('Modelo semanal do cliente salvo. Novos planos deste cliente já nascem com esta grade.');
        } else {
            $this->withError('Não foi possível salvar o modelo — o plano precisa ter posts.');
        }
        return $this->redirect("/conteudo/{$planId}");
    }

    /** Consulta leve para o form de criação: o cliente tem modelo? Quantos posts? */
    public function clientTemplate(Request $request): Response
    {
        Auth::requirePermission('content.view');

        $clientId = (int) $request->param('clientId');
        $agencyId = (int) Auth::agencyId();
        $template = $this->service->getTemplateForClient($clientId, $agencyId);

        return Response::json([
            'exists' => $template !== null,
            'count'  => $template ? count($template['items']) : 0,
        ]);
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('content.delete');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->delete($planId, $agencyId);

        if ($request->wantsJson()) {
            return Response::json(['success' => $ok]);
        }

        if ($ok) {
            $this->withSuccess('Plano excluído.');
        } else {
            $this->withError('Não é possível excluir planos aprovados.');
        }
        return $this->redirect('/conteudo');
    }

    // ── Items ──────────────────────────────────────────────────────────────────

    public function storeItem(Request $request): Response
    {
        Auth::requirePermission('content.create');

        $planId   = (int) $request->param('planId');
        $agencyId = (int) Auth::agencyId();
        $input    = $request->only([
            'publish_date', 'publish_time', 'content_type', 'platform', 'title', 'theme',
            'caption', 'script', 'cta', 'drive_url', 'cover_url', 'images', 'assigned_to', 'sort_order',
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
            $this->withError($e->getMessage());
        }

        return $this->redirect("/conteudo/{$planId}");
    }

    public function updateItem(Request $request): Response
    {
        Auth::requirePermission('content.edit');

        $planId   = (int) $request->param('planId');
        $itemId   = (int) $request->param('itemId');
        $agencyId = (int) Auth::agencyId();
        $input    = $request->only([
            'publish_date', 'publish_time', 'content_type', 'platform', 'title', 'theme',
            'caption', 'script', 'cta', 'drive_url', 'cover_url', 'images', 'assigned_to', 'status', 'sort_order',
        ]);

        try {
            $ok = $this->service->updateItem($itemId, $agencyId, $input);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return Response::json(['success' => $ok]);
    }

    public function destroyItem(Request $request): Response
    {
        Auth::requirePermission('content.delete');

        $itemId   = (int) $request->param('itemId');
        $agencyId = (int) Auth::agencyId();
        $ok       = $this->service->deleteItem($itemId, $agencyId);

        return Response::json(['success' => $ok]);
    }

    public function reorderItems(Request $request): Response
    {
        Auth::requirePermission('content.edit');

        $planId = (int) $request->param('planId');
        $ids    = $request->json('ids', []);
        if (!is_array($ids)) return Response::json(['success' => false], 422);

        if (!$this->service->get($planId, (int) Auth::agencyId())) {
            return Response::json(['success' => false, 'error' => 'Plano não encontrado.'], 404);
        }

        $this->service->reorderItems($planId, $ids);
        return Response::json(['success' => true]);
    }
}
