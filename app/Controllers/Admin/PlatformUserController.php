<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Auth;
use PDO;

class PlatformUserController extends Controller
{
    private PDO $pdo;

    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly AuthService    $authService,
    ) {
        $this->pdo = Database::connection();
    }

    // ------------------------------------------------------------------ index

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $search   = trim((string) $request->query('q', ''));
        $agencyId = (int) $request->query('agency_id', 0);

        $where  = ["u.is_platform_admin = FALSE"];
        $params = [];

        if ($search !== '') {
            $where[]      = "(u.name ILIKE :q OR u.email ILIKE :q)";
            $params[':q'] = "%{$search}%";
        }
        if ($agencyId > 0) {
            $where[]       = "u.agency_id = :aid";
            $params[':aid'] = $agencyId;
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.status, u.created_at,
                   a.name AS agency_name, a.id AS agency_id,
                   STRING_AGG(r.name, ', ' ORDER BY r.name) AS roles
            FROM users u
            LEFT JOIN agencies a    ON a.id = u.agency_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r       ON r.id = ur.role_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id, a.name, a.id
            ORDER BY a.name, u.name
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $agencies = $this->pdo->query("SELECT id, name FROM agencies ORDER BY name")->fetchAll();

        return $this->view('admin.users.index', compact('users', 'agencies', 'search', 'agencyId'));
    }

    // ----------------------------------------------------------------- create

    public function create(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $agencies = $this->pdo->query("SELECT id, name FROM agencies ORDER BY name")->fetchAll();
        $roles    = $this->pdo->query("SELECT id, name, slug FROM roles WHERE agency_id IS NULL ORDER BY name")->fetchAll();

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

        $stmt = $this->pdo->prepare("
            INSERT INTO users (agency_id, name, email, password_hash, status, created_at, updated_at)
            VALUES (:agency_id, :name, :email, :hash, 'active', NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id' => $agencyId,
            ':name'      => $name,
            ':email'     => $email,
            ':hash'      => password_hash($password, PASSWORD_ARGON2ID),
        ]);
        $userId = (int) $stmt->fetchColumn();

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

        $agencies = $this->pdo->query("SELECT id, name FROM agencies ORDER BY name")->fetchAll();
        $roles    = $this->pdo->query("SELECT id, name, slug FROM roles WHERE agency_id IS NULL ORDER BY name")->fetchAll();

        $urStmt = $this->pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :id");
        $urStmt->execute([':id' => $user['id']]);
        $userRoles = array_column($urStmt->fetchAll(), 'role_id');

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

        // check email uniqueness (excluding self)
        $taken = $this->pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
        $taken->execute([':email' => $email, ':id' => $id]);
        if ($taken->fetchColumn()) {
            $this->withError("E-mail \"{$email}\" já está em uso por outro usuário.");
            return $this->redirect("/admin/usuarios/{$id}/editar");
        }

        $this->pdo->prepare("
            UPDATE users SET name = :name, email = :email, agency_id = :agency_id, updated_at = NOW()
            WHERE id = :id
        ")->execute([':name' => $name, ':email' => $email, ':agency_id' => $agencyId, ':id' => $id]);

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
        $this->pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id")
            ->execute([':status' => $newStatus, ':id' => $id]);

        $label = $newStatus === 'active' ? 'ativado' : 'inativado';
        $this->withSuccess("Usuário {$user['name']} {$label}.");
        return $this->redirect('/admin/usuarios');
    }

    // ---------------------------------------------------------------- helpers

    private function findUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, a.name AS agency_name
            FROM users u
            LEFT JOIN agencies a ON a.id = u.agency_id
            WHERE u.id = :id AND u.is_platform_admin = FALSE
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
