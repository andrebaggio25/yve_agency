<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AgencyRepository;
use App\Repositories\PlatformUserRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Auth;

class PlatformUserController extends Controller
{
    public function __construct(
        private readonly UserRepository         $userRepo,
        private readonly AuthService            $authService,
        private readonly PlatformUserRepository $platformUsers,
        private readonly AgencyRepository       $agencies,
    ) {}

    // ------------------------------------------------------------------ index

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $search   = trim((string) $request->query('q', ''));
        $agencyId = (int) $request->query('agency_id', 0);

        $users    = $this->platformUsers->search($search, $agencyId);
        $agencies = $this->agencies->allForSelect();

        return $this->view('admin.users.index', compact('users', 'agencies', 'search', 'agencyId'));
    }

    // ----------------------------------------------------------------- create

    public function create(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $agencies = $this->agencies->allForSelect();
        $roles    = $this->platformUsers->globalRoles();

        return $this->view('admin.users.form', ['user' => null, 'userRoles' => [], 'agencies' => $agencies, 'roles' => $roles]);
    }

    public function store(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $name     = trim((string) $request->post('name', ''));
        $email    = trim((string) $request->post('email', ''));
        $agencyId = (int) $request->post('agency_id', 0);
        $password = trim((string) $request->post('password', ''));
        $roleIds  = array_map('intval', (array) $request->post('role_ids', []));

        if (!$name || !$email || !$agencyId) {
            $this->withError('Nome, e-mail e tenant são obrigatórios.');
            return $this->redirect('/admin/usuarios/novo');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->withError('E-mail inválido.');
            return $this->redirect('/admin/usuarios/novo');
        }
        if ($this->userRepo->emailExists($email)) {
            $this->withError("E-mail \"{$email}\" já está em uso.");
            return $this->redirect('/admin/usuarios/novo');
        }
        if (strlen($password) < 8) {
            $this->withError('A senha deve ter no mínimo 8 caracteres.');
            return $this->redirect('/admin/usuarios/novo');
        }

        $userId = $this->platformUsers->create(
            $agencyId,
            $name,
            $email,
            password_hash($password, PASSWORD_ARGON2ID)
        );

        if ($roleIds) {
            $this->userRepo->syncRoles($userId, $roleIds);
        }

        $this->withSuccess("Usuário \"{$name}\" criado com sucesso.");
        return $this->redirect('/admin/usuarios');
    }

    // ------------------------------------------------------------------- edit

    public function edit(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $user = $this->findUser((int) $request->param('id'));
        if (!$user) {
            $this->withError('Usuário não encontrado.');
            return $this->redirect('/admin/usuarios');
        }

        $agencies  = $this->agencies->allForSelect();
        $roles     = $this->platformUsers->globalRoles();
        $userRoles = $this->platformUsers->roleIdsOf((int) $user['id']);

        return $this->view('admin.users.form', compact('user', 'agencies', 'roles', 'userRoles'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id   = (int) $request->param('id');
        $user = $this->findUser($id);
        if (!$user) {
            $this->withError('Usuário não encontrado.');
            return $this->redirect('/admin/usuarios');
        }

        $name     = trim((string) $request->post('name', ''));
        $email    = trim((string) $request->post('email', ''));
        $agencyId = (int) $request->post('agency_id', 0);
        $roleIds  = array_map('intval', (array) $request->post('role_ids', []));

        if (!$name || !$email || !$agencyId) {
            $this->withError('Nome, e-mail e tenant são obrigatórios.');
            return $this->redirect("/admin/usuarios/{$id}/editar");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->withError('E-mail inválido.');
            return $this->redirect("/admin/usuarios/{$id}/editar");
        }

        if ($this->platformUsers->emailTaken($email, $id)) {
            $this->withError("E-mail \"{$email}\" já está em uso por outro usuário.");
            return $this->redirect("/admin/usuarios/{$id}/editar");
        }

        $this->platformUsers->updateProfile($id, $name, $email, $agencyId);
        $this->userRepo->syncRoles($id, $roleIds);

        $this->withSuccess('Usuário atualizado.');
        return $this->redirect('/admin/usuarios');
    }

    // --------------------------------------------------------------- password

    public function setPassword(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id   = (int) $request->param('id');
        $user = $this->findUser($id);
        if (!$user) {
            $this->withError('Usuário não encontrado.');
            return $this->redirect('/admin/usuarios');
        }

        $password = trim((string) $request->post('password', ''));
        if (strlen($password) < 8) {
            $this->withError('A senha deve ter no mínimo 8 caracteres.');
            return $this->redirect("/admin/usuarios/{$id}/editar");
        }

        $this->userRepo->updatePassword($id, password_hash($password, PASSWORD_ARGON2ID));

        $this->withSuccess("Senha de {$user['name']} redefinida com sucesso.");
        return $this->redirect("/admin/usuarios/{$id}/editar");
    }

    public function sendReset(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id   = (int) $request->param('id');
        $user = $this->findUser($id);
        if (!$user) {
            $this->withError('Usuário não encontrado.');
            return $this->redirect('/admin/usuarios');
        }

        $result = $this->authService->sendPasswordResetLink($user['email']);

        if ($result['success'] ?? false) {
            $this->withSuccess("Link de redefinição enviado para {$user['email']}.");
        } else {
            $this->withError('Falha ao enviar o e-mail. Verifique as configurações de SMTP.');
        }

        return $this->redirect("/admin/usuarios/{$id}/editar");
    }

    // --------------------------------------------------------------- status

    public function toggleStatus(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id   = (int) $request->param('id');
        $user = $this->findUser($id);
        if (!$user) {
            $this->withError('Usuário não encontrado.');
            return $this->redirect('/admin/usuarios');
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->platformUsers->setStatus($id, $newStatus);

        $label = $newStatus === 'active' ? 'ativado' : 'inativado';
        $this->withSuccess("Usuário {$user['name']} {$label}.");
        return $this->redirect('/admin/usuarios');
    }

    // ---------------------------------------------------------------- helpers

    private function findUser(int $id): ?array
    {
        return $this->platformUsers->find($id);
    }
}
