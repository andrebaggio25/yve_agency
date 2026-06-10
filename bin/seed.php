#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = new PDO(
    'pgsql:host=' . $_ENV['DB_HOST'] . ';port=5432;dbname=postgres',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$now = date('Y-m-d H:i:s');

echo "Seeding...\n";

// 1. Currencies
$pdo->exec("INSERT INTO currencies (code,symbol,name,decimal_places,created_at) VALUES
  ('BRL','R\$','Real Brasileiro',2,'{$now}'),
  ('USD','\$','US Dollar',2,'{$now}'),
  ('EUR','€','Euro',2,'{$now}')
  ON CONFLICT (code) DO NOTHING");
echo "  ✓ Currencies\n";

// 2. Permissions
$permissions = require dirname(__DIR__) . '/config/permissions.php';
$vals = [];
foreach ($permissions as $slug => $desc) {
    $module = explode('.', $slug)[0];
    $vals[] = sprintf(
        "(%s, %s, %s, '%s')",
        $pdo->quote($desc),
        $pdo->quote($slug),
        $pdo->quote($module),
        $now
    );
}
$pdo->exec('INSERT INTO permissions (name,slug,module,created_at) VALUES ' . implode(',', $vals) . ' ON CONFLICT (slug) DO NOTHING');
echo "  ✓ Permissions (" . count($permissions) . ")\n";

// 3. Agency
$pdo->exec("INSERT INTO agencies (name,country,currency_code,timezone,status,created_at) VALUES ('YVE Agency','BR','BRL','America/Sao_Paulo','active','{$now}') ON CONFLICT DO NOTHING");
$agency = $pdo->query('SELECT id FROM agencies LIMIT 1')->fetch();
echo "  ✓ Agency (id={$agency['id']})\n";

// 4. Roles
$rolesDef = [
    ['super_admin',      'Super Admin'],
    ['agency_admin',     'Admin da Agência'],
    ['traffic_manager',  'Gestor de Tráfego'],
    ['social_media',     'Social Media'],
    ['designer',         'Designer'],
    ['financial',        'Financeiro'],
    ['client_admin',     'Cliente — Admin'],
    ['client_approver',  'Cliente — Aprovador'],
    ['client_financial', 'Cliente — Financeiro'],
];
$rvals = [];
foreach ($rolesDef as [$slug, $name]) {
    $rvals[] = sprintf("(NULL, %s, %s, '%s', '%s')", $pdo->quote($name), $pdo->quote($slug), $now, $now);
}
$pdo->exec('INSERT INTO roles (agency_id,name,slug,created_at,updated_at) VALUES ' . implode(',', $rvals) . ' ON CONFLICT DO NOTHING');
echo "  ✓ Roles (" . count($rolesDef) . ")\n";

// 5. Super admin e agency_admin recebem TODAS as permissões
$allPerms = $pdo->query('SELECT id FROM permissions')->fetchAll();
foreach (['super_admin', 'agency_admin'] as $roleSlug) {
    $role = $pdo->query("SELECT id FROM roles WHERE slug=" . $pdo->quote($roleSlug) . " LIMIT 1")->fetch();
    $rpVals = [];
    foreach ($allPerms as $p) {
        $rpVals[] = "({$role['id']},{$p['id']},'{$now}')";
    }
    if ($rpVals) {
        $pdo->exec('INSERT INTO role_permissions (role_id,permission_id,created_at) VALUES ' . implode(',', $rpVals) . ' ON CONFLICT DO NOTHING');
    }
}
echo "  ✓ Permissões super_admin + agency_admin\n";

// 6. Permissões dos demais roles
$rolePerms = [
    'traffic_manager' => ['dashboard.view','clients.view','ads_metrics.view','ads_actions.request','ads_actions.approve','ai_insights.view','ai.generate_report','ai.recommend_ads_action','content.view','tasks.view','tasks.create','tasks.edit'],
    'social_media'    => ['dashboard.view','clients.view','content.view','content.create','content.edit','content.send_to_approval','approvals.view','drive_assets.view','organic_metrics.view','tasks.view','tasks.create','tasks.edit','whatsapp.view','email.view'],
    'designer'        => ['dashboard.view','clients.view','content.view','drive_assets.view','approvals.view','tasks.view'],
    'financial'       => ['dashboard.view','clients.view','contracts.view','contracts.create','contracts.edit','contracts.send','invoices.view','invoices.create','invoices.edit','invoices.send','payments.view','payments.create','financial_reports.view','financial_reports.export'],
    'client_admin'    => ['dashboard.view','approvals.view','approvals.comment','approvals.approve','approvals.reject','content.view','drive_assets.view'],
    'client_approver' => ['approvals.view','approvals.comment','approvals.approve','approvals.reject','content.view','drive_assets.view'],
    'client_financial'=> ['invoices.view','contracts.view','payments.view'],
];
foreach ($rolePerms as $slug => $slugs) {
    $role = $pdo->query("SELECT id FROM roles WHERE slug=" . $pdo->quote($slug) . " LIMIT 1")->fetch();
    if (!$role) continue;
    $rpVals = [];
    foreach ($slugs as $ps) {
        $perm = $pdo->query('SELECT id FROM permissions WHERE slug=' . $pdo->quote($ps) . ' LIMIT 1')->fetch();
        if ($perm) $rpVals[] = "({$role['id']},{$perm['id']},'{$now}')";
    }
    if ($rpVals) {
        $pdo->exec('INSERT INTO role_permissions (role_id,permission_id,created_at) VALUES ' . implode(',', $rpVals) . ' ON CONFLICT DO NOTHING');
    }
}
echo "  ✓ Permissões demais roles\n";

// 7. Super admin user
$hash = password_hash('admin123!', PASSWORD_ARGON2ID);
$pdo->exec("INSERT INTO users (agency_id,name,email,password_hash,status,created_at,updated_at)
  VALUES ({$agency['id']},'Super Admin','admin@yveagency.com'," . $pdo->quote($hash) . ",'active','{$now}','{$now}')
  ON CONFLICT (email) DO NOTHING");
$user  = $pdo->query("SELECT id FROM users WHERE email='admin@yveagency.com' LIMIT 1")->fetch();
$sRole = $pdo->query("SELECT id FROM roles WHERE slug='super_admin' LIMIT 1")->fetch();
$pdo->exec("INSERT INTO user_roles (user_id,role_id,created_at) VALUES ({$user['id']},{$sRole['id']},'{$now}') ON CONFLICT DO NOTHING");
echo "  ✓ Super admin (admin@yveagency.com / admin123!)\n";

// Resumo
echo "\n─────────────────────────────\n";
foreach (['currencies','permissions','roles','role_permissions','users'] as $t) {
    $n = $pdo->query("SELECT COUNT(*) as n FROM {$t}")->fetch();
    echo "  {$t}: {$n['n']}\n";
}
echo "\n⚠  Altere a senha do admin após o primeiro login!\n";
