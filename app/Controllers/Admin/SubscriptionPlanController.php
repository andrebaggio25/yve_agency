<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SubscriptionRepository;
use App\Services\BillingService;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository $repo,
        private readonly BillingService         $billing,
    ) {}

    public function plans(Request $request): Response
    {
        $plans         = $this->repo->allPlans();
        $subscriptions = $this->repo->allSubscriptions();

        $subsByPlan = [];
        foreach ($subscriptions as $s) {
            $subsByPlan[$s['plan_id']] = ($subsByPlan[$s['plan_id']] ?? 0) + 1;
        }

        return $this->view('admin.billing.plans', compact('plans', 'subsByPlan'));
    }

    public function createPlan(Request $request): Response
    {
        return $this->view('admin.billing.plan_form', ['plan' => null]);
    }

    public function storePlan(Request $request): Response
    {
        $this->repo->createPlan($this->planData($request));
        $this->withSuccess('Plano criado.');
        return $this->redirect('/admin/planos');
    }

    public function editPlan(Request $request): Response
    {
        $plan = $this->repo->findPlanById((int) $request->param('id'));
        if (!$plan) return Response::view('errors.404', [], 404);

        if (is_string($plan['features'])) {
            $plan['features'] = json_decode($plan['features'], true) ?? [];
        }

        return $this->view('admin.billing.plan_form', compact('plan'));
    }

    public function updatePlan(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->repo->updatePlan($id, $this->planData($request));
        $this->withSuccess('Plano atualizado.');
        return $this->redirect('/admin/planos');
    }

    public function subscriptions(Request $request): Response
    {
        $tenants = $this->repo->allAgenciesWithSubscription();
        $plans   = $this->repo->allPlans();

        return $this->view('admin.billing.subscriptions', compact('tenants', 'plans'));
    }

    public function editSubscription(Request $request): Response
    {
        $agencyId = (int) $request->param('agencyId');
        $existing = $this->repo->findActiveByAgency($agencyId);
        $plans    = $this->repo->allPlans();

        $agency = $this->findAgency($agencyId);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/assinaturas');
        }

        return $this->view('admin.billing.subscription_form', compact('agency', 'existing', 'plans'));
    }

    public function updateSubscription(Request $request): Response
    {
        $agencyId     = (int) $request->param('agencyId');
        $planId       = (int) $request->post('plan_id', 0);
        $billingCycle = $request->post('billing_cycle', 'monthly');
        $status       = $request->post('status', 'active');

        if ($planId) {
            $this->billing->assignPlan($agencyId, $planId, $billingCycle, $status);
            $this->withSuccess('Assinatura atualizada.');
        }

        return $this->redirect('/admin/assinaturas');
    }

    private function findAgency(int $id): ?array
    {
        $pdo  = \App\Core\Database::connection();
        $stmt = $pdo->prepare("SELECT id, name FROM agencies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function planData(Request $request): array
    {
        $features = array_filter(array_map('trim', explode("\n", $request->post('features', ''))));

        return [
            'name'                 => trim((string) $request->post('name', '')),
            'slug'                 => trim((string) $request->post('slug', '')),
            'description'          => trim((string) $request->post('description', '')),
            'price_monthly'        => (float) $request->post('price_monthly', 0),
            'price_yearly'         => (float) $request->post('price_yearly', 0),
            'max_clients'          => (int)   $request->post('max_clients', 0) ?: null,
            'max_users'            => (int)   $request->post('max_users', 0) ?: null,
            'max_meta_accounts'    => (int)   $request->post('max_meta_accounts', 0) ?: null,
            'max_organic_accounts' => (int)   $request->post('max_organic_accounts', 0) ?: null,
            'features'             => array_values($features),
            'is_active'            => (bool)  $request->post('is_active', false),
            'sort_order'           => (int)   $request->post('sort_order', 0),
        ];
    }
}
