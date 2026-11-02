<?php

declare(strict_types=1);

/**
 * Semente do smoke de navegador (QA-04).
 *
 * O `smoke.mjs` precisa de um login e de um portal de cliente para não pular
 * tela nenhuma. Este script cria esse par **no banco de teste local** e imprime
 * as variáveis prontas para o shell:
 *
 *     eval "$(php tests/browser/seed.php)"
 *     npm run test:browser
 *
 * Regras:
 *   - lê **apenas** o `.env.testing` e aborta se o host não for local — nunca
 *     pode tocar no Supabase de produção;
 *   - a senha é local e descartável (`SMOKE_PASSWORD` sobrescreve). Não use
 *     credencial real aqui: o banco de teste é truncado por `composer test`;
 *   - é idempotente: rodar duas vezes devolve o mesmo usuário e o mesmo token.
 *
 * Saída: `export ...` no stdout (para o `eval`), progresso no stderr.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

const EMAIL_SMOKE  = 'smoke@local.test';
const SENHA_PADRAO = 'smoke-local-123456';

$root = dirname(__DIR__, 2);

// ── .env.testing, com a mesma trava do phinx.test.php ────────────────────────
$envFile = $root . '/.env.testing';
if (!is_file($envFile)) {
    fwrite(STDERR, "ABORTADO: .env.testing não existe. Suba o banco de teste primeiro (docs/OPERACAO.md).\n");
    exit(1);
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v]       = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$host = $env['DB_HOST'] ?? '';
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "ABORTADO: .env.testing aponta para host não-local ({$host}).\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $env['DB_PORT'] ?? '55432', $env['DB_NAME'] ?? 'yve_test'),
        $env['DB_USER'] ?? 'postgres',
        $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
} catch (Throwable $e) {
    fwrite(STDERR, "ABORTADO: banco de teste fora do ar. Suba com:\n  docker compose -f docker-compose.test.yml up -d\n");
    exit(1);
}

if (!$pdo->query("SELECT to_regclass('public.users') IS NOT NULL")->fetchColumn()) {
    fwrite(STDERR, "ABORTADO: schema ausente no banco de teste. Rode: composer db:test\n");
    exit(1);
}

$senha = getenv('SMOKE_PASSWORD') ?: SENHA_PADRAO;

// ── Agência ──────────────────────────────────────────────────────────────────
$agencyId = $pdo->query("SELECT id FROM agencies WHERE slug = 'smoke-local'")->fetchColumn();
if (!$agencyId) {
    $agencyId = $pdo->query(
        "INSERT INTO agencies (name, slug, status, created_at)
         VALUES ('Smoke Local', 'smoke-local', 'active', NOW()) RETURNING id"
    )->fetchColumn();
}
$agencyId = (int) $agencyId;

// ── Papel da agência com o catálogo inteiro ──────────────────────────────────
// Papel do tenant, não do sistema (D10): os perfis padrão ficam intocados.
$permissions = require $root . '/config/permissions.php';

$insertPerm = $pdo->prepare(
    "INSERT INTO permissions (name, slug, module, description, created_at)
     VALUES (:n, :s, :m, :d, NOW()) ON CONFLICT (slug) DO NOTHING"
);
foreach ($permissions as $slug => $descricao) {
    $insertPerm->execute([
        ':n' => $descricao,
        ':s' => $slug,
        ':m' => explode('.', (string) $slug, 2)[0],
        ':d' => $descricao,
    ]);
}

$roleId = $pdo->prepare("SELECT id FROM roles WHERE slug = 'smoke-admin' AND agency_id = :a");
$roleId->execute([':a' => $agencyId]);
$roleId = $roleId->fetchColumn();

if (!$roleId) {
    $stmt = $pdo->prepare(
        "INSERT INTO roles (agency_id, name, slug, description, created_at, updated_at)
         VALUES (:a, 'Smoke Admin', 'smoke-admin', 'Papel do smoke de navegador (local)', NOW(), NOW()) RETURNING id"
    );
    $stmt->execute([':a' => $agencyId]);
    $roleId = $stmt->fetchColumn();
}
$roleId = (int) $roleId;

$pdo->prepare(
    'INSERT INTO role_permissions (role_id, permission_id, created_at)
     SELECT :r, p.id, NOW() FROM permissions p
     WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = :r2 AND rp.permission_id = p.id)'
)->execute([':r' => $roleId, ':r2' => $roleId]);

// ── Usuário ──────────────────────────────────────────────────────────────────
$userId = $pdo->prepare('SELECT id FROM users WHERE email = :e');
$userId->execute([':e' => EMAIL_SMOKE]);
$userId = $userId->fetchColumn();

$hash = password_hash($senha, PASSWORD_ARGON2ID);

if ($userId) {
    // Senha reescrita a cada semeadura: quem manda é este script, não o banco.
    $pdo->prepare('UPDATE users SET password_hash = :h, status = \'active\', agency_id = :a WHERE id = :id')
        ->execute([':h' => $hash, ':a' => $agencyId, ':id' => $userId]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users (agency_id, name, email, password_hash, status, created_at, updated_at)
         VALUES (:a, 'Smoke', :e, :h, 'active', NOW(), NOW()) RETURNING id"
    );
    $stmt->execute([':a' => $agencyId, ':e' => EMAIL_SMOKE, ':h' => $hash]);
    $userId = $stmt->fetchColumn();
}
$userId = (int) $userId;

$pdo->prepare(
    'INSERT INTO user_roles (user_id, role_id, created_at)
     SELECT :u, :r, NOW()
     WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = :u2 AND role_id = :r2)'
)->execute([':u' => $userId, ':r' => $roleId, ':u2' => $userId, ':r2' => $roleId]);

// ── Cliente com portal ligado ────────────────────────────────────────────────
$client = $pdo->prepare('SELECT id, portal_token FROM clients WHERE agency_id = :a AND name = :n');
$client->execute([':a' => $agencyId, ':n' => 'Cliente Smoke']);
$client = $client->fetch();

if (!$client) {
    $stmt = $pdo->prepare(
        "INSERT INTO clients (agency_id, name, status, portal_token, portal_enabled, created_at)
         VALUES (:a, 'Cliente Smoke', 'active', :t, true, NOW()) RETURNING id, portal_token"
    );
    $stmt->execute([':a' => $agencyId, ':t' => bin2hex(random_bytes(16))]);
    $client = $stmt->fetch();
} else {
    $pdo->prepare('UPDATE clients SET portal_enabled = true, status = \'active\' WHERE id = :id')
        ->execute([':id' => (int) $client['id']]);
}

$pdo->prepare(
    'INSERT INTO client_user_access (client_id, user_id, access_level, created_at)
     VALUES (:c, :u, \'standard\', NOW()) ON CONFLICT (user_id, client_id) DO NOTHING'
)->execute([':c' => (int) $client['id'], ':u' => $userId]);

// Bloqueio de login e rate limit ficam em arquivo (SEC-18): sobra de um smoke
// anterior derrubaria o login desta rodada.
App\Support\LoginThrottle::clear(EMAIL_SMOKE);
foreach (glob(storage_path('framework/rate_limit_*')) ?: [] as $file) {
    @unlink($file);
}

fwrite(STDERR, "✓ Semeado no banco de teste ({$host}:" . ($env['DB_PORT'] ?? '55432') . "/" . ($env['DB_NAME'] ?? 'yve_test') . "):\n");
fwrite(STDERR, '  agência #' . $agencyId . ' · usuário ' . EMAIL_SMOKE . ' · cliente #' . $client['id'] . "\n");

$exports = [
    'SMOKE_EMAIL'    => EMAIL_SMOKE,
    'SMOKE_PASSWORD' => $senha,
    'PORTAL_TOKEN'   => (string) $client['portal_token'],
];

foreach ($exports as $k => $v) {
    echo 'export ' . $k . '=' . escapeshellarg($v) . "\n";
}
