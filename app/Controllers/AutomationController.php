<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Services\AutomationService;
use App\Support\Auth;

class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationService      $automations,
        private readonly ClientRepository       $clients,
        private readonly \App\Repositories\NotificationRepository $deliveries,
    ) {}

    /**
     * OBS-02 — histórico de entregas: o que foi enviado, pra quem, por qual
     * canal, com que resultado.
     *
     * Antes, responder "a cliente diz que não recebeu o lembrete" exigia
     * consultar o banco na mão. E automação que ninguém vê acontecer parece que
     * não existe — isto também é argumento de venda.
     */
    public function deliveries(Request $request): Response
    {
        Auth::requirePermission('automations.view');
        $agencyId = (int) Auth::agencyId();

        $filters = [
            'channel' => (string) $request->query('channel', ''),
            'status'  => (string) $request->query('status', ''),
        ];

        $deliveries = $this->deliveries->deliveriesByAgency($agencyId, array_filter($filters));
        $stats      = $this->deliveries->deliveryStats($agencyId);

        return $this->view('automations.deliveries', compact('deliveries', 'stats', 'filters'));
    }

    public function index(Request $request): Response
    {
        Auth::requirePermission('automations.view');
        $agencyId = (int) Auth::agencyId();

        $this->automations->ensureRulesForAgency($agencyId);
        $rules = $this->automations->rulesForAgency($agencyId);

        return $this->view('automations.index', compact('rules'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('automations.edit');
        $agencyId = (int) Auth::agencyId();
        $key      = (string) $request->param('key');

        if (!$this->automations->definition($key)) {
            $this->withError('Automação desconhecida.');
            return $this->redirect('/automations');
        }

        $active   = $request->post('status', 'inactive') === 'active';
        $time     = trim((string) $request->post('time', '')) ?: null;
        $day      = trim((string) $request->post('day', '')) ?: null;
        $channels = $request->post('channels', []);
        $channels = is_array($channels) ? array_values($channels) : null;

        $this->automations->configureAgencyRule($agencyId, $key, $active, $time, $day, $channels);

        $this->withSuccess('Automação atualizada.');
        return $this->redirect('/automations');
    }

    public function matrix(Request $request): Response
    {
        Auth::requirePermission('automations.view');
        $agencyId = (int) Auth::agencyId();

        $clients         = $this->clients->findByAgency($agencyId);
        $clientAutomations = $this->automations->clientAutomations();
        $matrix          = $this->automations->settingsMatrix($agencyId);

        return $this->view('automations.clients', compact('clients', 'clientAutomations', 'matrix'));
    }

    public function saveMatrix(Request $request): Response
    {
        Auth::requirePermission('automations.edit');
        $agencyId = (int) Auth::agencyId();

        $posted  = $request->post('enabled', []);
        $posted  = is_array($posted) ? $posted : [];

        $matrix = [];
        foreach ($this->clients->findByAgency($agencyId) as $c) {
            $id = (int) $c['id'];
            $matrix[$id] = $posted[$id] ?? ($posted[(string) $id] ?? []);
        }

        $this->automations->bulkSetMatrix($agencyId, $matrix);

        $this->withSuccess('Preferências por cliente salvas.');
        return $this->redirect('/automations/clients');
    }
}
