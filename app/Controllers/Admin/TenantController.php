<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use PDO;

class TenantController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
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
        return $this->view('admin.tenants.create');
    }

    public function store(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $name     = trim((string) $request->post('name', ''));
        $country  = (string) $request->post('country', 'BR');
        $currency = (string) $request->post('currency_code', 'BRL');
        $timezone = (string) $request->post('timezone', 'America/Sao_Paulo');
        $status   = (string) $request->post('status', 'active');

        if (empty($name)) {
            $this->withError('O nome é obrigatório.');
            return $this->redirect('/admin/tenants/criar');
        }

        $slug = $this->generateUniqueSlug($name);

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
        $id = (int) $stmt->fetchColumn();

        $this->withSuccess("Tenant \"{$name}\" criado com sucesso.");
        return $this->redirect('/admin/tenants/' . $id . '/editar');
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

        $userCount = (int) $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE agency_id = :id AND is_platform_admin = FALSE"
        )->execute([':id' => $id]) ? $this->pdo->query("SELECT COUNT(*) FROM users WHERE agency_id = {$id} AND is_platform_admin = FALSE")->fetchColumn() : 0;

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

    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $base = trim($base, '-') ?: 'agency';
        $slug = $base;
        $i    = 1;
        while ($this->pdo->query("SELECT 1 FROM agencies WHERE slug = '{$slug}'")->fetchColumn()) {
            $slug = $base . '-' . ($i++);
        }
        return $slug;
    }
}
