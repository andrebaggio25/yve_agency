<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClickUpIntegrationRepository;
use App\Services\ClickUpService;
use App\Support\Auth;

class ClickUpController extends Controller
{
    public function __construct(
        private readonly ClickUpIntegrationRepository $integrationRepo,
        private readonly ClickUpService               $clickup,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $agencyId    = (int) Auth::agencyId();
        $integration = $this->integrationRepo->findByAgency($agencyId);

        if ($integration && is_string($integration['status_map'] ?? null)) {
            $integration['status_map'] = json_decode($integration['status_map'], true) ?? [];
        }

        return $this->view('integrations.clickup', compact('integration'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $agencyId = (int) Auth::agencyId();

        $apiToken   = trim((string) $request->post('api_token', ''));
        $listId     = trim((string) $request->post('default_list_id', ''));
        $workspaceId= trim((string) $request->post('workspace_id', ''));

        if (empty($apiToken) || empty($listId)) {
            $this->withError('Token e Lista padrão são obrigatórios.');
            return $this->redirect('/integrations/clickup');
        }

        // Mapa de status
        $statusMap = [
            'todo'        => trim((string) $request->post('map_todo',        'to do')),
            'in_progress' => trim((string) $request->post('map_in_progress', 'in progress')),
            'review'      => trim((string) $request->post('map_review',      'review')),
            'done'        => trim((string) $request->post('map_done',        'complete')),
        ];

        // Preservar webhook_token se já existir
        $existing     = $this->integrationRepo->findByAgency($agencyId);
        $webhookToken = $existing['webhook_token'] ?? bin2hex(random_bytes(32));

        $this->integrationRepo->upsert($agencyId, [
            'api_token'       => $apiToken,
            'workspace_id'    => $workspaceId,
            'default_list_id' => $listId,
            'webhook_token'   => $webhookToken,
            'status_map'      => $statusMap,
        ]);

        // Registrar webhook no ClickUp se workspace_id informado
        if (!empty($workspaceId)) {
            $appUrl = env('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $this->clickup->registerWebhook($agencyId, $appUrl);
        }

        $this->withSuccess('Integração ClickUp salva com sucesso.');
        return $this->redirect('/integrations/clickup');
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $agencyId = (int) Auth::agencyId();
        $this->integrationRepo->deactivate($agencyId);
        $this->withSuccess('Integração ClickUp desativada.');
        return $this->redirect('/integrations/clickup');
    }
}
