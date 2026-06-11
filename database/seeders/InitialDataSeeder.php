<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Popula:
 * - currencies (BRL, USD, EUR)
 * - permissions (lista canônica de config/permissions.php)
 * - roles padrão do sistema
 * - agência inicial + super_admin
 */
final class InitialDataSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->seedCurrencies();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedAgencyAndSuperAdmin();
        $this->seedPlatformAdmin();
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'BRL', 'symbol' => 'R$',  'name' => 'Real Brasileiro',  'decimal_places' => 2],
            ['code' => 'USD', 'symbol' => '$',   'name' => 'US Dollar',         'decimal_places' => 2],
            ['code' => 'EUR', 'symbol' => '€',   'name' => 'Euro',              'decimal_places' => 2],
        ];

        foreach ($currencies as $c) {
            $exists = $this->fetchRow(
                "SELECT id FROM currencies WHERE code = '{$c['code']}'"
            );
            if (!$exists) {
                $this->table('currencies')->insert($c)->saveData();
            }
        }

        echo "  ✓ Currencies seeded\n";
    }

    private function seedPermissions(): void
    {
        // Load permission map from config
        $permissions = require dirname(__DIR__, 2) . '/config/permissions.php';

        foreach ($permissions as $slug => $description) {
            $exists = $this->fetchRow("SELECT id FROM permissions WHERE slug = '{$slug}'");
            if ($exists) continue;

            $parts = explode('.', $slug, 2);
            $this->table('permissions')->insert([
                'name'        => $description,
                'slug'        => $slug,
                'module'      => $parts[0],
                'description' => $description,
                'created_at'  => date('Y-m-d H:i:s'),
            ])->saveData();
        }

        echo "  ✓ Permissions seeded\n";
    }

    private function seedRoles(): void
    {
        $roles = [
            ['slug' => 'super_admin',     'name' => 'Super Admin',              'permissions' => '__ALL__'],
            ['slug' => 'agency_admin',    'name' => 'Admin da Agência',         'permissions' => '__ALL__'],
            ['slug' => 'traffic_manager', 'name' => 'Gestor de Tráfego',       'permissions' => [
                'dashboard.view', 'clients.view', 'ads_metrics.view',
                'ads_actions.view', 'ads_actions.request', 'ads_actions.approve', 'ads_actions.execute',
                'ai_insights.view', 'ai.generate_report', 'ai.recommend_ads_action',
                'organic_metrics.view',
                'content.view', 'tasks.view', 'tasks.create', 'tasks.edit',
            ]],
            ['slug' => 'social_media',    'name' => 'Social Media',             'permissions' => [
                'dashboard.view', 'clients.view', 'content.view', 'content.create', 'content.edit',
                'content.send_to_approval', 'approvals.view', 'drive_assets.view',
                'organic_metrics.view', 'tasks.view', 'tasks.create', 'tasks.edit',
                'whatsapp.view', 'email.view',
            ]],
            ['slug' => 'designer',        'name' => 'Designer',                 'permissions' => [
                'dashboard.view', 'clients.view', 'content.view', 'drive_assets.view',
                'approvals.view', 'tasks.view',
            ]],
            ['slug' => 'financial',       'name' => 'Financeiro',               'permissions' => [
                'dashboard.view', 'clients.view_basic', 'contracts.view', 'contracts.create',
                'contracts.edit', 'contracts.send', 'invoices.view', 'invoices.create',
                'invoices.edit', 'invoices.send', 'payments.view', 'payments.create',
                'financial_reports.view', 'financial_reports.export',
            ]],
            ['slug' => 'client_admin',    'name' => 'Cliente — Admin',          'permissions' => [
                'dashboard.view', 'approvals.view', 'approvals.comment', 'approvals.approve', 'approvals.reject',
                'content.view', 'drive_assets.view',
            ]],
            ['slug' => 'client_approver', 'name' => 'Cliente — Aprovador',      'permissions' => [
                'approvals.view', 'approvals.comment', 'approvals.approve', 'approvals.reject',
                'content.view', 'drive_assets.view',
            ]],
            ['slug' => 'client_financial','name' => 'Cliente — Financeiro',     'permissions' => [
                'invoices.view', 'contracts.view', 'payments.view',
            ]],
        ];

        foreach ($roles as $roleData) {
            $exists = $this->fetchRow("SELECT id FROM roles WHERE slug = '{$roleData['slug']}'");
            if ($exists) continue;

            $this->table('roles')->insert([
                'agency_id'   => null,
                'name'        => $roleData['name'],
                'slug'        => $roleData['slug'],
                'description' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ])->saveData();

            $role = $this->fetchRow("SELECT id FROM roles WHERE slug = '{$roleData['slug']}'");

            $permSlugs = $roleData['permissions'] === '__ALL__'
                ? array_column($this->fetchAll('SELECT slug FROM permissions'), 'slug')
                : $roleData['permissions'];

            foreach ($permSlugs as $slug) {
                $perm = $this->fetchRow("SELECT id FROM permissions WHERE slug = '{$slug}'");
                if ($perm) {
                    $this->table('role_permissions')->insert([
                        'role_id'       => $role['id'],
                        'permission_id' => $perm['id'],
                        'created_at'    => date('Y-m-d H:i:s'),
                    ])->saveData();
                }
            }
        }

        echo "  ✓ Roles seeded\n";
    }

    private function seedAgencyAndSuperAdmin(): void
    {
        // Agência
        $agencyExists = $this->fetchRow("SELECT id FROM agencies WHERE id = 1");
        if (!$agencyExists) {
            $this->table('agencies')->insert([
                'name'          => 'YVE Agency',
                'legal_name'    => null,
                'country'       => 'BR',
                'currency_code' => 'BRL',
                'timezone'      => 'America/Sao_Paulo',
                'status'        => 'active',
                'created_at'    => date('Y-m-d H:i:s'),
            ])->saveData();
        }

        $agency = $this->fetchRow('SELECT id FROM agencies LIMIT 1');

        // Super admin
        $adminEmail  = 'admin@yveagency.com';
        $adminExists = $this->fetchRow("SELECT id FROM users WHERE email = '{$adminEmail}'");

        if (!$adminExists) {
            $this->table('users')->insert([
                'agency_id'     => $agency['id'],
                'name'          => 'Super Admin',
                'email'         => $adminEmail,
                'password_hash' => password_hash('admin123!', PASSWORD_ARGON2ID),
                'status'        => 'active',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ])->saveData();

            $admin = $this->fetchRow("SELECT id FROM users WHERE email = '{$adminEmail}'");
            $role  = $this->fetchRow("SELECT id FROM roles WHERE slug = 'super_admin'");

            $this->table('user_roles')->insert([
                'user_id'    => $admin['id'],
                'role_id'    => $role['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ])->saveData();
        }

        echo "  ✓ Agency + super_admin seeded (email: {$adminEmail} / senha: admin123!)\n";
        echo "  ⚠ Altere a senha do super_admin após o primeiro login!\n";
    }

    private function seedPlatformAdmin(): void
    {
        $email  = 'platform@yveagency.com';
        $exists = $this->fetchRow("SELECT id FROM users WHERE email = '{$email}'");
        if ($exists) {
            echo "  ✓ Platform admin já existe\n";
            return;
        }

        $this->table('users')->insert([
            'agency_id'        => null,
            'name'             => 'Platform Admin',
            'email'            => $email,
            'password_hash'    => password_hash('platform123!', PASSWORD_ARGON2ID),
            'is_platform_admin'=> true,
            'status'           => 'active',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ])->saveData();

        echo "  ✓ Platform admin seeded (email: {$email} / senha: platform123!)\n";
        echo "  ⚠ Altere a senha do platform admin após o primeiro login!\n";
    }
}
