<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AgencyRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BillingService;
use App\Support\ActivityLogger;
use App\Support\Auth;

/**
 * Planos e assinaturas do SaaS (só o dono da plataforma).
 *
 * Toda rota deste controller está no grupo `PlatformAdminMiddleware` de
 * `routes/web.php`, mas era o **único** controller de `/admin` sem a guarda no
 * método (SEC-15): os irmãos — tenants, usuários da plataforma, configurações
 * globais, migrations — chamam `Auth::requirePlatformAdmin()` em cada um. Uma
 * rota registrada no grupo errado, ou um `$router->group` futuro mal fechado,
 * abriria preço de plano e assinatura de tenant para qualquer usuário logado.
 */
class SubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository $repo,
        private readonly BillingService         $billing,
        private readonly AgencyRepository       $agencies,
    ) {}

    public function plans(Request $request): Response
    {
        Auth::requirePlatformAdmin();

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
        Auth::requirePlatformAdmin();

        return $this->view('admin.billing.plan_form', ['plan' => null]);
    }

    public function storePlan(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $data = $this->planData($request);
        $this->repo->createPlan($data);

        ActivityLogger::log('admin.plan_created', 'admin', null, null, [
            'slug'          => $data['slug'],
            'name'          => $data['name'],
            'price_monthly' => $data['price_monthly'],
            'price_yearly'  => $data['price_yearly'],
            'is_active'     => $data['is_active'],
        ]);

        $this->withSuccess('Plano criado.');
        return $this->redirect('/admin/planos');
    }

    public function editPlan(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $plan = $this->repo->findPlanById((int) $request->param('id'));
        if (!$plan) return Response::view('errors.404', [], 404);

        if (is_string($plan['features'])) {
            $plan['features'] = json_decode($plan['features'], true) ?? [];
        }

        return $this->view('admin.billing.plan_form', compact('plan'));
    }

    public function updatePlan(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id   = (int) $request->param('id');
        $data = $this->planData($request);
        $this->repo->updatePlan($id, $data);

        ActivityLogger::log('admin.plan_updated', 'admin', null, null, [
            'plan_id'       => $id,
            'slug'          => $data['slug'],
            'price_monthly' => $data['price_monthly'],
            'price_yearly'  => $data['price_yearly'],
            'is_active'     => $data['is_active'],
        ]);

        $this->withSuccess('Plano atualizado.');
        return $this->redirect('/admin/planos');
    }

    public function subscriptions(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $tenants = $this->repo->allAgenciesWithSubscription();
        $plans   = $this->repo->allPlans();

        return $this->view('admin.billing.subscriptions', compact('tenants', 'plans'));
    }

    public function editSubscription(Request $request): Response
    {
        Auth::requirePlatformAdmin();

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
        Auth::requirePlatformAdmin();

        $agencyId     = (int) $request->param('agencyId');
        $planId       = (int) $request->post('plan_id', 0);
        $billingCycle = $request->post('billing_cycle', 'monthly');
        $status       = $request->post('status', 'active');

        if ($planId) {
            $this->billing->assignPlan($agencyId, $planId, $billingCycle, $status);

            // O admin da plataforma não tem `agency_id`: a trilha é a do tenant
            // cuja assinatura mudou, senão a linha cairia na agência 0 (SEC-15).
            ActivityLogger::log('admin.subscription_changed', 'admin', null, null, [
                'agency_id'     => $agencyId,
                'plan_id'       => $planId,
                'billing_cycle' => $billingCycle,
                'status'        => $status,
            ], $agencyId);

            $this->withSuccess('Assinatura atualizada.');
        }

        return $this->redirect('/admin/assinaturas');
    }

    private function findAgency(int $id): ?array
    {
        return $this->agencies->findBasic($id);
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
