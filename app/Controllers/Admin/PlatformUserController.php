<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use PDO;

class PlatformUserController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $search   = trim((string) $request->get('q', ''));
        $agencyId = (int) $request->get('agency_id', 0);

        $where  = ["u.is_platform_admin = FALSE"];
        $params = [];

        if ($search !== '') {
            $where[]          = "(u.name ILIKE :q OR u.email ILIKE :q)";
            $params[':q']     = "%{$search}%";
        }
        if ($agencyId > 0) {
            $where[]            = "u.agency_id = :aid";
            $params[':aid']     = $agencyId;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.status, u.language, u.created_at,
                   a.name AS agency_name, a.id AS agency_id,
                   STRING_AGG(r.name, ', ') AS roles
            FROM users u
            LEFT JOIN agencies a    ON a.id = u.agency_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r       ON r.id = ur.role_id
            WHERE {$whereClause}
            GROUP BY u.id, a.name, a.id
            ORDER BY a.name, u.name
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $agencies = $this->pdo->query(
            "SELECT id, name FROM agencies ORDER BY name"
        )->fetchAll();

        return $this->view('admin.users.index', compact('users', 'agencies', 'search', 'agencyId'));
    }
}
