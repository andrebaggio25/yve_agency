<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\RoleService;
use App\Support\Auth;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('roles.view');
        $roles = $this->roleService->list(Auth::agencyId());
        return $this->view('users.roles.index', ['roles' => $roles]);
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('roles.create');
        $permissions = require base_path('config/permissions.php');
        return $this->view('users.roles.create', ['allPermissions' => $permissions]);
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('roles.create');

        $data = $request->only('name', 'slug', 'description');
        $perms = (array) ($request->post('permissions') ?? []);

        $result = $this->roleService->create($data, $perms, Auth::agencyId());

        if (!$result['success']) {
            $this->withErrors($result['errors'])->withInput($data);
            return $this->redirect('/perfis/novo');
        }

        $this->withSuccess('Perfil criado.');
        return $this->redirect('/perfis');
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('roles.view');
        $role = $this->roleService->findById((int) $request->param('id'), Auth::agencyId());
        if (!$role) return $this->view('errors.404', [], 404);
        return $this->view('users.roles.show', ['role' => $role]);
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('roles.edit');
        $role = $this->roleService->findById((int) $request->param('id'), Auth::agencyId());
        if (!$role) return $this->view('errors.404', [], 404);
        $permissions = require base_path('config/permissions.php');
        return $this->view('users.roles.edit', ['role' => $role, 'allPermissions' => $permissions]);
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('roles.edit');

        $id   = (int) $request->param('id');
        $data = $request->only('name', 'description');
        $perms = (array) ($request->post('permissions') ?? []);

        $result = $this->roleService->update($id, $data, $perms, Auth::agencyId());

        if (!$result['success']) {
            $this->withErrors($result['errors']);
            return $this->redirect("/perfis/{$id}/editar");
        }

        $this->withSuccess('Perfil atualizado.');
        return $this->redirect("/perfis/{$id}");
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('roles.delete');
        $this->roleService->delete((int) $request->param('id'), Auth::agencyId());
        $this->withSuccess('Perfil removido.');
        return $this->redirect('/perfis');
    }
}
