<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\PortalAuth;
use App\Repositories\ClientRepository;
use App\Repositories\ContentPlanRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\ContractRepository;
use App\Repositories\AdAccountRepository;
use App\Repositories\AdMetricsRepository;
use App\Repositories\OrganicAccountRepository;
use App\Repositories\OrganicMetricsRepository;
use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;

/**
 * Portal do cliente: dashboard, planos de conteúdo, aprovação/feedback,
 * faturas e contratos. O envio de conteúdos (Drive) vive no
 * `PortalDriveController` — extraído daqui no ARCH-03.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly ClientRepository          $clientRepo,
        private readonly ContentPlanRepository     $planRepo,
        private readonly InvoiceRepository         $invoiceRepo,
        private readonly ContractRepository        $contractRepo,
        private readonly AdAccountRepository       $adAccountRepo,
        private readonly AdMetricsRepository       $adMetricsRepo,
        private readonly OrganicAccountRepository  $organicAccountRepo,
        private readonly OrganicMetricsRepository  $organicMetricsRepo,
        private readonly ContentPlanService        $planService,
        private readonly GoogleDriveService        $drive,
    ) {}

    // ---------------------------------------------------------------- dashboard

    public function index(Request $request): Response
    {
        $client  = PortalAuth::client();
        $token   = PortalAuth::token();

        $clientId  = (int) $client['id'];
        $agencyId  = (int) $client['agency_id'];

        $plans    = $this->planRepo->allByClient($clientId, $agencyId);
        $invoices = $this->invoiceRepo->findByClient($clientId);

        // Ads metrics for this client (last 30 days)
        $since = date('Y-m-d', strtotime('-30 days'));
        $until = date('Y-m-d');
        $adAccounts   = $this->adAccountRepo->findByClient($clientId, $agencyId);
        $adsSummary   = [];
        foreach ($adAccounts as $acc) {
            $s = $this->adMetricsRepo->summaryForAccount((int) $acc['id'], $since, $until);
            foreach ($s as $k => $v) {
                $adsSummary[$k] = ($adsSummary[$k] ?? 0) + (float) $v;
            }
        }

        // Organic metrics for this client (last 30 days)
        $organicAccounts = $this->organicAccountRepo->findByClient($clientId, $agencyId);
        $organicSummary  = [];
        foreach ($organicAccounts as $acc) {
            $s = $this->organicMetricsRepo->summaryForAccount((int) $acc['id'], $since, $until);
            foreach ($s as $k => $v) {
                $organicSummary[$k] = ($organicSummary[$k] ?? 0) + (float) $v;
            }
        }

        $stats = [
            'plans_pending'  => count(array_filter($plans,    fn($p) => in_array($p['status'], ['sent', 'pending_approval'], true))),
            'plans_approved' => count(array_filter($plans,    fn($p) => $p['status'] === 'approved')),
            'invoices_open'  => count(array_filter($invoices, fn($i) => $i['status'] === 'sent')),
            'invoices_paid'  => count(array_filter($invoices, fn($i) => $i['status'] === 'paid')),
        ];

        // "Sua semana": os posts da semana corrente (seg–dom), nunca de rascunho.
        $weekMonday = ContentPlanService::mondayOf(date('Y-m-d'));
        $weekSunday = ContentPlanService::sundayOf($weekMonday);
        $weekItems  = $this->planRepo->itemsBetweenForClient($clientId, $agencyId, $weekMonday, $weekSunday);

        return $this->view('portal.index', compact(
            'client', 'token', 'plans', 'invoices', 'stats',
            'adsSummary', 'organicSummary', 'since', 'until',
            'weekMonday', 'weekSunday', 'weekItems'
        ));
    }

    // ---------------------------------------------------------- content plans

    public function plans(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $plans  = $this->planRepo->allByClient((int) $client['id'], (int) $client['agency_id']);

        return $this->view('portal.plans', compact('client', 'token', 'plans'));
    }

    /**
     * Calendário mensal de consulta: o cliente vê o mês inteiro, clica no
     * criativo e cai na planificação semanal dele. Somente leitura, e nunca
     * mostra rascunho (o filtro está no repositório).
     */
    public function plansCalendar(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();

        // Mês pedido (YYYY-MM); valor inválido cai no mês atual.
        $monthParam = (string) $request->query('month', '');
        $month      = preg_match('/^\d{4}-\d{2}$/', $monthParam) ? $monthParam : date('Y-m');

        $first = $month . '-01';
        $last  = date('Y-m-t', strtotime($first));

        $items = $this->planRepo->itemsBetweenForClient((int) $client['id'], (int) $client['agency_id'], $first, $last);

        $byDay = [];
        foreach ($items as $item) {
            $byDay[(string) $item['publish_date']][] = $item;
        }

        return $this->view('portal.plans_calendar', [
            'client'    => $client,
            'token'     => $token,
            'month'     => $month,
            'firstDay'  => $first,
            'lastDay'   => $last,
            'byDay'     => $byDay,
            'total'     => count($items),
            'prevMonth' => date('Y-m', strtotime($first . ' -1 month')),
            'nextMonth' => date('Y-m', strtotime($first . ' +1 month')),
        ]);
    }

    public function planShow(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan  = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        $items = $this->planRepo->getItems($planId);
        foreach ($items as &$item) {
            $item['drive_parsed'] = !empty($item['drive_url']) ? $this->drive->parse($item['drive_url']) : null;
            $item['feedbacks']    = $this->planRepo->getFeedbacks((int) $item['id']);
            $item['images_list']  = is_string($item['images'] ?? null) ? (json_decode($item['images'], true) ?? []) : ($item['images'] ?? []);
        }
        unset($item);

        // Navegação semana ← → entre planos visíveis ao cliente.
        $prevPlan = $nextPlan = null;
        if (!empty($plan['week_start'])) {
            $prevPlan = $this->planRepo->findAdjacentForClient((int) $client['id'], (string) $plan['week_start'], 'prev');
            $nextPlan = $this->planRepo->findAdjacentForClient((int) $client['id'], (string) $plan['week_start'], 'next');
        }

        return $this->view('portal.plan_show', compact('client', 'token', 'plan', 'items', 'prevPlan', 'nextPlan'));
    }

    public function planApprove(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        // O envio grava status 'sent'; 'pending_approval' fica aceito por
        // compatibilidade de leitura (dado legado/manual).
        if (in_array($plan['status'], ['sent', 'pending_approval'], true)) {
            $this->planService->approvePlan($planId, (int) $client['id']);
            $this->withSuccess(t('portal.plan.approved_ok'));
        } else {
            $this->withError(t('portal.plan.not_awaiting'));
        }

        return $this->redirect("/portal/{$token}/planos/{$planId}");
    }

    public function planRevision(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        $comment = trim((string) $request->post('comment', ''));
        if (in_array($plan['status'], ['sent', 'pending_approval'], true)) {
            $this->planService->requestRevision($planId, (int) $client['id'], $comment);
            $this->withSuccess(t('portal.plan.revision_ok'));
        } else {
            $this->withError(t('portal.plan.not_awaiting'));
        }

        return $this->redirect("/portal/{$token}/planos/{$planId}");
    }

    public function itemFeedback(Request $request): Response
    {
        $client  = PortalAuth::client();
        $planId  = (int) $request->param('planId');
        $itemId  = (int) $request->param('itemId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::json(['error' => 'Plano não encontrado'], 404);
        }

        $item = $this->planRepo->findItemForClient($itemId, (int) $client['id']);
        if (!$item || (int) $item['content_plan_id'] !== $planId) {
            return Response::json(['error' => 'Item não encontrado'], 404);
        }

        $type    = $request->input('feedback_type', 'comment');
        $comment = trim((string) $request->input('comment', ''));
        $tcRaw   = $request->input('timecode', '');

        $allowed = ['approved', 'changes_requested', 'comment'];
        if (!in_array($type, $allowed, true)) {
            return Response::json(['error' => 'Tipo inválido'], 422);
        }

        // Parse timecode "MM:SS" → seconds
        $timecodeSeconds = null;
        if ($tcRaw !== '' && preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $tcRaw), $m)) {
            $timecodeSeconds = (int)$m[1] * 60 + (int)$m[2];
        }

        $feedbackId = $this->planService->addFeedback(
            $itemId, $planId, (int) $client['id'], null,
            $type, $comment ?: null, $timecodeSeconds, 'client'
        );

        $author   = $client['name'] ?? 'Cliente';
        $timecode = $timecodeSeconds !== null
            ? sprintf('%d:%02d', intdiv($timecodeSeconds, 60), $timecodeSeconds % 60)
            : null;

        $typeLabels = [
            'approved'          => 'Aprovado',
            'changes_requested' => 'Alteração solicitada',
            'comment'           => 'Comentário',
        ];

        return Response::json([
            'success'  => true,
            'feedback' => [
                'id'            => $feedbackId,
                'feedback_type' => $type,
                'type_label'    => $typeLabels[$type],
                'comment'       => $comment ?: null,
                'client_name'   => $author,
                'user_name'     => null,
                'source'        => 'client',
                'timecode'      => $timecode,
                'created_at'    => date('Y-m-d H:i:s'),
            ],
        ]);
    }


    // ------------------------------------------------------------ invoices

    public function invoices(Request $request): Response
    {
        $client   = PortalAuth::client();
        $token    = PortalAuth::token();
        $invoices = $this->invoiceRepo->findByClient((int) $client['id']);

        return $this->view('portal.invoices', compact('client', 'token', 'invoices'));
    }

    // ------------------------------------------------------------ contracts

    public function contracts(Request $request): Response
    {
        $client    = PortalAuth::client();
        $token     = PortalAuth::token();
        $contracts = $this->contractRepo->findByClient((int) $client['id']);

        return $this->view('portal.contracts', compact('client', 'token', 'contracts'));
    }

    // ------------------------------------------------ agency admin: manage portal

    public function adminRegenerateToken(Request $request): Response
    {
        Auth::requirePermission('clients.view');
        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());

        if (!$client) return Response::view('errors.404', [], 404);

        $this->clientRepo->regeneratePortalToken($clientId);
        $this->withSuccess('Link do portal regenerado.');
        return $this->redirect('/clientes/' . $clientId);
    }

    public function adminTogglePortal(Request $request): Response
    {
        Auth::requirePermission('clients.view');
        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());

        if (!$client) return Response::view('errors.404', [], 404);

        $enabled = !(bool) $client['portal_enabled'];
        $this->clientRepo->setPortalEnabled($clientId, $enabled);
        $this->withSuccess($enabled ? 'Portal ativado.' : 'Portal desativado.');
        return $this->redirect('/clientes/' . $clientId);
    }
}
