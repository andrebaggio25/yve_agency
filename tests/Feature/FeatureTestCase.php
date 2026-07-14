<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base dos testes de feature (QA-03): exercita o app **de ponta a ponta** —
 * rota real → middlewares reais → controller → banco PostgreSQL real.
 *
 * Por que Postgres e não SQLite: as migrations usam `JSONB`, `FILTER (WHERE …)`,
 * `FOR UPDATE SKIP LOCKED`, `TIMESTAMPTZ`. Em SQLite metade do schema não sobe,
 * e o que sobe não se parece com produção — o teste passaria mentindo.
 *
 * Banco: `docker compose -f docker-compose.test.yml up -d` + `composer db:test`.
 * Sem o banco no ar, estes testes são **skipped** (não falham): quem não tem
 * Docker continua rodando a suíte unitária.
 */
abstract class FeatureTestCase extends TestCase
{
    protected Router $router;
    protected PDO $pdo;

    /** Tabelas limpas antes de cada teste (ordem irrelevante — usamos CASCADE). */
    private const TRUNCATE = [
        'activity_logs', 'jobs', 'notification_jobs', 'notifications',
        'drive_files', 'drive_folders', 'google_drive_integrations',
        'payments', 'invoices', 'contracts',
        'content_plan_items', 'content_plans',
        'tasks', 'client_user_access', 'clients',
        'user_roles', 'users', 'agencies',
        // platform_settings guarda o heartbeat do cron e o throttle de alerta
        // (OBS-01) — sujeira entre testes faria um teste mentir para o outro.
        'platform_settings',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::testDatabaseAvailable()) {
            $this->markTestSkipped(
                'Banco de teste indisponível. Suba com: docker compose -f docker-compose.test.yml up -d && composer db:test'
            );
        }

        $this->pdo = Database::connection();
        $this->resetDatabase();

        $_SESSION = [];
        $_POST = $_GET = $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        $container    = new Container();
        $this->router = new Router($container);
        $router       = $this->router;
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // ── Banco ────────────────────────────────────────────────────────────────

    private static function testDatabaseAvailable(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }

        try {
            Database::connection()->query('SELECT 1');
            $ok = true;
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    private function resetDatabase(): void
    {
        $tables = implode(', ', self::TRUNCATE);
        $this->pdo->exec("TRUNCATE {$tables} RESTART IDENTITY CASCADE");
    }

    // ── Fábricas mínimas ─────────────────────────────────────────────────────

    protected function createAgency(string $name = 'Agência Teste'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO agencies (name, slug, status, created_at)
             VALUES (:n, :s, 'active', NOW()) RETURNING id"
        );
        $stmt->execute([':n' => $name, ':s' => strtolower(str_replace(' ', '-', $name)) . '-' . random_int(1000, 9999)]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{id:int,email:string,password:string} */
    protected function createUser(int $agencyId, string $email = 'user@test.com', string $password = 'senha-forte-123'): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (agency_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:a, 'Usuário Teste', :e, :h, 'active', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':e' => $email, ':h' => password_hash($password, PASSWORD_ARGON2ID)]);

        return ['id' => (int) $stmt->fetchColumn(), 'email' => $email, 'password' => $password];
    }

    protected function createClient(int $agencyId, string $name = 'Cliente Teste', bool $portal = true): array
    {
        $token = bin2hex(random_bytes(16));
        $stmt  = $this->pdo->prepare(
            "INSERT INTO clients (agency_id, name, status, portal_token, portal_enabled, created_at)
             VALUES (:a, :n, 'active', :t, :p, NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':n' => $name, ':t' => $token, ':p' => $portal ? 'true' : 'false']);

        return ['id' => (int) $stmt->fetchColumn(), 'portal_token' => $token];
    }

    // ── Sessão ───────────────────────────────────────────────────────────────

    /** Loga o usuário como a AuthService faria (sessão + permissões + clientes). */
    protected function actingAs(int $userId, array $permissions = [], array $clientIds = []): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user']        = $user;
        $_SESSION['permissions'] = $permissions;
        $_SESSION['client_ids']  = $clientIds;
    }

    // ── HTTP ─────────────────────────────────────────────────────────────────

    protected function get(string $uri): Response
    {
        return $this->request('GET', $uri);
    }

    /** POST com CSRF válido por padrão (o caso sem token é testado à parte). */
    protected function post(string $uri, array $data = [], bool $withCsrf = true): Response
    {
        if ($withCsrf) {
            $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
            $data['_csrf_token'] = $_SESSION['csrf_token'];
        }

        return $this->request('POST', $uri, $data);
    }

    private function request(string $method, string $uri, array $data = []): Response
    {
        $parts = parse_url($uri);

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET  = [];
        $_POST = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $_GET);
        }
        if ($method === 'POST') {
            $_POST = $data;
        }

        return $this->router->handle(Request::fromGlobals());
    }
}
