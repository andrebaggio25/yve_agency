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

class PortalController extends Controller
{
    public function __construct(
        private readonly ClientRepository        $clientRepo,
        private readonly ContentPlanRepository   $planRepo,
        private readonly InvoiceRepository       $invoiceRepo,
        private readonly ContractRepository      $contractRepo,
        private readonly AdAccountRepository     $adAccountRepo,
        private readonly AdMetricsRepository     $adMetricsRepo,
        private readonly OrganicAccountRepository   $organicAccountRepo,
        private readonly OrganicMetricsRepository   $organicMetricsRepo,
        private readonly ContentPlanService      $planService,
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
            'plans_pending'  => count(array_filter($plans,    fn($p) => $p['status'] === 'pending_approval')),
            'plans_approved' => count(array_filter($plans,    fn($p) => $p['status'] === 'approved')),
            'invoices_open'  => count(array_filter($invoices, fn($i) => $i['status'] === 'sent')),
            'invoices_paid'  => count(array_filter($invoices, fn($i) => $i['status'] === 'paid')),
        ];

        return $this->view('portal.index', compact(
            'client', 'token', 'plans', 'invoices', 'stats',
            'adsSummary', 'organicSummary', 'since', 'until'
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

        return $this->view('portal.plan_show', compact('client', 'token', 'plan', 'items'));
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

        if ($plan['status'] === 'pending_approval') {
            $this->planService->approvePlan($planId, (int) $client['id']);
        }

        $this->withSuccess('Plano aprovado!');
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
        if ($plan['status'] === 'pending_approval') {
            $this->planService->requestRevision($planId, (int) $client['id'], $comment);
        }

        $this->withSuccess('Revisão solicitada.');
        return $this->redirect("/portal/{$token}/planos/{$planId}");
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
