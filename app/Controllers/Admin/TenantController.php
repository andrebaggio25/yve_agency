<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AgencyRepository;
use App\Services\BillingService;
use App\Services\TenantService;
use App\Support\Auth;

class TenantController extends Controller
{
    public function __construct(
        private readonly BillingService   $billing,
        private readonly TenantService    $tenants,
        private readonly AgencyRepository $agencies,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $agencies = $this->agencies->allWithCounts();

        return $this->view('admin.tenants.index', compact('agencies'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePlatformAdmin();
        $plans = $this->billing->allPlans();
        return $this->view('admin.tenants.create', compact('plans'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $result = $this->tenants->createWithAdmin([
            'name'           => $request->post('name', ''),
            'country'        => $request->post('country', 'BR'),
            'currency_code'  => $request->post('currency_code', 'BRL'),
            'timezone'       => $request->post('timezone', 'America/Sao_Paulo'),
            'status'         => $request->post('status', 'active'),
            'admin_name'     => $request->post('admin_name', ''),
            'admin_email'    => $request->post('admin_email', ''),
            'admin_password' => $request->post('admin_password', ''),
        ]);

        if (!$result['success']) {
            $this->withError((string) $result['error']);
            return $this->redirect('/admin/tenants/criar');
        }

        $agencyId = (int) $result['agency_id'];

        $planId = (int) $request->post('plan_id', 0);
        if ($planId > 0) {
            $this->billing->assignPlan(
                $agencyId,
                $planId,
                (string) $request->post('billing_cycle', 'monthly'),
                (string) $request->post('subscription_status', 'trialing')
            );
        }

        $name        = trim((string) $request->post('name', ''));
        $email       = trim((string) $request->post('admin_email', ''));
        $passDisplay = $result['password'] ?? '(senha definida pelo admin)';

        $this->withSuccess("Tenant \"{$name}\" criado. Admin: {$email} / Senha: {$passDisplay}");
        return $this->redirect('/admin/tenants/' . $agencyId . '/editar');
    }

    public function edit(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->agencies->find($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        $users = $this->agencies->usersOf($id);

        return $this->view('admin.tenants.edit', compact('agency', 'users'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->agencies->find($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        $name = trim((string) $request->post('name', ''));
        if (empty($name)) {
            $this->withError('O nome é obrigatório.');
            return $this->redirect('/admin/tenants/' . $id . '/editar');
        }

        $this->agencies->updateAdmin($id, [
            'name'          => $name,
            'country'       => (string) $request->post('country', 'BR'),
            'currency_code' => (string) $request->post('currency_code', 'BRL'),
            'timezone'      => (string) $request->post('timezone', 'America/Sao_Paulo'),
            'status'        => (string) $request->post('status', 'active'),
        ]);

        $this->withSuccess('Tenant atualizado.');
        return $this->redirect('/admin/tenants/' . $id . '/editar');
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->agencies->find($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        // Guarda: excluir agência cascateia em todo o dado do tenant.
        $userCount = $this->agencies->countUsers($id);
        if ($userCount > 0) {
            $this->withError("Não é possível excluir: o tenant possui {$userCount} usuário(s).");
            return $this->redirect('/admin/tenants/' . $id . '/editar');
        }

        $this->agencies->deleteById($id);

        $this->withSuccess('Tenant excluído.');
        return $this->redirect('/admin/tenants');
    }
}
