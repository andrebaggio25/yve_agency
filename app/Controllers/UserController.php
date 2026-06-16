<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use App\Services\BillingService;
use App\Support\Auth;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService    $userService,
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('users.view');

        $users = $this->userService->listForAgency(Auth::agencyId());
        return $this->view('users.index', ['users' => $users]);
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('users.create');

        $roles = $this->userService->listRoles(Auth::agencyId());
        return $this->view('users.create', ['roles' => $roles]);
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('users.create');

        if (!$this->billing->checkLimit((int) Auth::agencyId(), 'users')) {
            $this->withError('Limite de usuários do seu plano atingido. Faça upgrade para adicionar mais.');
            return $this->redirect('/usuarios/novo');
        }

        $data = $request->only('name', 'email', 'password', 'password_confirmation', 'phone', 'role_id', 'language', 'status');

        $result = $this->userService->create($data, Auth::agencyId());

        if (!$result['success']) {
            $this->withErrors($result['errors'])->withInput($data);
            return $this->redirect('/usuarios/novo');
        }

        $this->withSuccess('Usuário criado com sucesso.');
        return $this->redirect('/usuarios');
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('users.view');

        $user = $this->userService->findById((int) $request->param('id'), Auth::agencyId());

        if (!$user) {
            return $this->view('errors.404', [], 404);
        }

        return $this->view('users.show', ['user' => $user]);
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('users.edit');

        $user  = $this->userService->findById((int) $request->param('id'), Auth::agencyId());
        $roles = $this->userService->listRoles(Auth::agencyId());

        if (!$user) {
            return $this->view('errors.404', [], 404);
        }

        return $this->view('users.edit', ['user' => $user, 'roles' => $roles]);
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('users.edit');

        $id   = (int) $request->param('id');
        $data = $request->only('name', 'email', 'phone', 'status', 'password', 'password_confirmation', 'role_id', 'role_ids', 'language');

        $result = $this->userService->update($id, $data, Auth::agencyId());

        if (!$result['success']) {
            $this->withErrors($result['errors']);
            return $this->redirect("/usuarios/{$id}/editar");
        }

        $this->withSuccess('Usuário atualizado com sucesso.');
        return $this->redirect("/usuarios/{$id}");
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('users.delete');

        $id = (int) $request->param('id');
        $this->userService->delete($id, Auth::agencyId());

        $this->withSuccess('Usuário removido.');
        return $this->redirect('/usuarios');
    }
}
