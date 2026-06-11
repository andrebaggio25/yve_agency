<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SubscriptionRepository;
use App\Services\BillingService;
use App\Support\Auth;
use PDO;

class TenantController extends Controller
{
    private PDO $pdo;
    private BillingService $billing;

    public function __construct(BillingService $billing)
    {
        $this->pdo     = Database::connection();
        $this->billing = $billing;
    }

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $agencies = $this->pdo->query("
            SELECT a.*,
                   COUNT(DISTINCT u.id)  AS user_count,
                   COUNT(DISTINCT c.id)  AS client_count
            FROM agencies a
            LEFT JOIN users u  ON u.agency_id = a.id AND u.is_platform_admin = FALSE
            LEFT JOIN clients c ON c.agency_id = a.id
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ")->fetchAll();

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

        $name       = trim((string) $request->post('name', ''));
        $country    = (string) $request->post('country', 'BR');
        $currency   = (string) $request->post('currency_code', 'BRL');
        $timezone   = (string) $request->post('timezone', 'America/Sao_Paulo');
        $status     = (string) $request->post('status', 'active');
        $adminName    = trim((string) $request->post('admin_name', ''));
        $adminEmail   = trim((string) $request->post('admin_email', ''));
        $adminPass    = trim((string) $request->post('admin_password', ''));
        $planId       = (int) $request->post('plan_id', 0);
        $billingCycle = (string) $request->post('billing_cycle', 'monthly');
        $subStatus    = (string) $request->post('subscription_status', 'trialing');

        if (empty($name)) {
            $this->withError('O nome do tenant é obrigatório.');
            return $this->redirect('/admin/tenants/criar');
        }
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->withError('E-mail do administrador é obrigatório e deve ser válido.');
            return $this->redirect('/admin/tenants/criar');
        }

        $existingEmail = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
        $existingEmail->execute([':email' => $adminEmail]);
        if ($existingEmail->fetchColumn()) {
            $this->withError("Já existe um usuário com o e-mail \"{$adminEmail}\".");
            return $this->redirect('/admin/tenants/criar');
        }

        $slug = $this->generateUniqueSlug($name);

        $this->pdo->beginTransaction();
        try {
            // 1. Create agency
            $stmt = $this->pdo->prepare("
                INSERT INTO agencies (name, country, currency_code, timezone, status, slug, created_at)
                VALUES (:name, :country, :currency, :timezone, :status, :slug, NOW())
                RETURNING id
            ");
            $stmt->execute([
                ':name'     => $name,
                ':country'  => $country,
                ':currency' => $currency,
                ':timezone' => $timezone,
                ':status'   => $status,
                ':slug'     => $slug,
            ]);
            $agencyId = (int) $stmt->fetchColumn();

            // 2. Create super_admin user
            $password = $adminPass ?: $this->generatePassword();
            $stmt = $this->pdo->prepare("
                INSERT INTO users (agency_id, name, email, password_hash, status, language, created_at, updated_at)
                VALUES (:agency_id, :name, :email, :password_hash, 'active', 'pt', NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                ':agency_id'     => $agencyId,
                ':name'          => $adminName ?: 'Super Admin',
                ':email'         => $adminEmail,
                ':password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
            $userId = (int) $stmt->fetchColumn();

            // 3. Assign super_admin role
            $role = $this->pdo->query("SELECT id FROM roles WHERE slug = 'super_admin' LIMIT 1")->fetch();
            if ($role) {
                $this->pdo->prepare("
                    INSERT INTO user_roles (user_id, role_id, created_at)
                    VALUES (:user_id, :role_id, NOW())
                ")->execute([':user_id' => $userId, ':role_id' => $role['id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->withError('Erro ao criar tenant: ' . $e->getMessage());
            return $this->redirect('/admin/tenants/criar');
        }

        // 4. Create subscription if plan selected
        if ($planId > 0) {
            $this->billing->assignPlan($agencyId, $planId, $billingCycle, $subStatus);
        }

        $passDisplay = $adminPass ? '(senha definida pelo admin)' : $password;
        $this->withSuccess("Tenant \"{$name}\" criado. Admin: {$adminEmail} / Senha: {$passDisplay}");
        return $this->redirect('/admin/tenants/' . $agencyId . '/editar');
    }

    public function edit(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->findAgency($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        $users = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.status, r.name AS role_name
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r       ON r.id = ur.role_id
            WHERE u.agency_id = :agency_id AND u.is_platform_admin = FALSE
            ORDER BY u.name
        ");
        $users->execute([':agency_id' => $id]);
        $users = $users->fetchAll();

        return $this->view('admin.tenants.edit', compact('agency', 'users'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->findAgency($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        $name     = trim((string) $request->post('name', ''));
        $country  = (string) $request->post('country', 'BR');
        $currency = (string) $request->post('currency_code', 'BRL');
        $timezone = (string) $request->post('timezone', 'America/Sao_Paulo');
        $status   = (string) $request->post('status', 'active');

        if (empty($name)) {
            $this->withError('O nome é obrigatório.');
            return $this->redirect('/admin/tenants/' . $id . '/editar');
        }

        $this->pdo->prepare("
            UPDATE agencies SET name = :name, country = :country, currency_code = :currency,
                timezone = :timezone, status = :status, updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':name'     => $name,
            ':country'  => $country,
            ':currency' => $currency,
            ':timezone' => $timezone,
            ':status'   => $status,
            ':id'       => $id,
        ]);

        $this->withSuccess('Tenant atualizado.');
        return $this->redirect('/admin/tenants/' . $id . '/editar');
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $id     = (int) $request->param('id');
        $agency = $this->findAgency($id);
        if (!$agency) {
            $this->withError('Tenant não encontrado.');
            return $this->redirect('/admin/tenants');
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE agency_id = :id AND is_platform_admin = FALSE");
        $countStmt->execute([':id' => $id]);
        $userCount = (int) $countStmt->fetchColumn();

        if ($userCount > 0) {
            $this->withError("Não é possível excluir: o tenant possui {$userCount} usuário(s).");
            return $this->redirect('/admin/tenants/' . $id . '/editar');
        }

        $this->pdo->prepare("DELETE FROM agencies WHERE id = :id")->execute([':id' => $id]);
        $this->withSuccess('Tenant excluído.');
        return $this->redirect('/admin/tenants');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findAgency(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM agencies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function generatePassword(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
        $pass  = '';
        for ($i = 0; $i < 12; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $base = trim($base, '-') ?: 'agency';
        $slug = $base;
        $i    = 1;
        $chk  = $this->pdo->prepare("SELECT 1 FROM agencies WHERE slug = :slug LIMIT 1");
        $chk->execute([':slug' => $slug]);
        while ($chk->fetchColumn()) {
            $slug = $base . '-' . ($i++);
            $chk->execute([':slug' => $slug]);
        }
        return $slug;
    }
}
