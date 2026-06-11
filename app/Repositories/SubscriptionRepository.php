<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class SubscriptionRepository extends Repository
{
    protected string $table = 'agency_subscriptions';

    // ── Plans ─────────────────────────────────────────────────────────────────

    public function allPlans(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE is_active = true' : '';
        return $this->all("SELECT * FROM subscription_plans {$where} ORDER BY sort_order, id");
    }

    public function findPlanById(int $id): ?array
    {
        return $this->first("SELECT * FROM subscription_plans WHERE id = :id", [':id' => $id]);
    }

    public function findPlanBySlug(string $slug): ?array
    {
        return $this->first("SELECT * FROM subscription_plans WHERE slug = :slug", [':slug' => $slug]);
    }

    public function createPlan(array $data): int
    {
        $this->query("
            INSERT INTO subscription_plans (name, slug, description, price_monthly, price_yearly,
                max_clients, max_users, max_meta_accounts, max_organic_accounts, features, sort_order)
            VALUES (:name, :slug, :description, :price_monthly, :price_yearly,
                :max_clients, :max_users, :max_meta_accounts, :max_organic_accounts, :features, :sort_order)
        ", [
            ':name'                => $data['name'],
            ':slug'                => $data['slug'],
            ':description'         => $data['description'] ?? null,
            ':price_monthly'       => $data['price_monthly'],
            ':price_yearly'        => $data['price_yearly'],
            ':max_clients'         => $data['max_clients'] ?: null,
            ':max_users'           => $data['max_users'] ?: null,
            ':max_meta_accounts'   => $data['max_meta_accounts'] ?: null,
            ':max_organic_accounts'=> $data['max_organic_accounts'] ?: null,
            ':features'            => json_encode($data['features'] ?? []),
            ':sort_order'          => $data['sort_order'] ?? 0,
        ]);
        return (int) $this->first("SELECT id FROM subscription_plans ORDER BY id DESC LIMIT 1")['id'];
    }

    public function updatePlan(int $id, array $data): void
    {
        $this->query("
            UPDATE subscription_plans SET
                name                 = :name,
                description          = :description,
                price_monthly        = :price_monthly,
                price_yearly         = :price_yearly,
                max_clients          = :max_clients,
                max_users            = :max_users,
                max_meta_accounts    = :max_meta_accounts,
                max_organic_accounts = :max_organic_accounts,
                features             = :features,
                is_active            = :is_active,
                sort_order           = :sort_order
            WHERE id = :id
        ", [
            ':id'                  => $id,
            ':name'                => $data['name'],
            ':description'         => $data['description'] ?? null,
            ':price_monthly'       => $data['price_monthly'],
            ':price_yearly'        => $data['price_yearly'],
            ':max_clients'         => $data['max_clients'] ?: null,
            ':max_users'           => $data['max_users'] ?: null,
            ':max_meta_accounts'   => $data['max_meta_accounts'] ?: null,
            ':max_organic_accounts'=> $data['max_organic_accounts'] ?: null,
            ':features'            => json_encode($data['features'] ?? []),
            ':is_active'           => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            ':sort_order'          => $data['sort_order'] ?? 0,
        ]);
    }

    // ── Agency subscriptions ──────────────────────────────────────────────────

    public function findActiveByAgency(int $agencyId): ?array
    {
        return $this->first("
            SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
                   p.max_clients, p.max_users, p.max_meta_accounts, p.max_organic_accounts,
                   p.features, p.price_monthly, p.price_yearly
            FROM agency_subscriptions s
            JOIN subscription_plans p ON p.id = s.plan_id
            WHERE s.agency_id = :agency_id
              AND s.status NOT IN ('cancelled')
            ORDER BY s.id DESC
            LIMIT 1
        ", [':agency_id' => $agencyId]);
    }

    public function allSubscriptions(array $filters = []): array
    {
        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND s.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['plan_id'])) {
            $where .= ' AND s.plan_id = :plan_id';
            $params[':plan_id'] = $filters['plan_id'];
        }

        return $this->all("
            SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, a.name AS agency_name
            FROM agency_subscriptions s
            JOIN subscription_plans p ON p.id = s.plan_id
            JOIN agencies a ON a.id = s.agency_id
            {$where}
            ORDER BY s.created_at DESC
        ", $params);
    }

    /**
     * All agencies, with their most recent active subscription (or null).
     * Used in the admin subscriptions overview.
     */
    public function allAgenciesWithSubscription(): array
    {
        return $this->all("
            SELECT
                a.id AS agency_id,
                a.name AS agency_name,
                a.status AS agency_status,
                s.id AS sub_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.current_period_start,
                s.current_period_end,
                p.name AS plan_name,
                p.slug AS plan_slug
            FROM agencies a
            LEFT JOIN LATERAL (
                SELECT * FROM agency_subscriptions
                WHERE agency_id = a.id
                ORDER BY id DESC
                LIMIT 1
            ) s ON true
            LEFT JOIN subscription_plans p ON p.id = s.plan_id
            ORDER BY a.name
        ");
    }

    public function findSubscriptionById(int $id): ?array
    {
        return $this->first("
            SELECT s.*, p.name AS plan_name, a.name AS agency_name
            FROM agency_subscriptions s
            JOIN subscription_plans p ON p.id = s.plan_id
            JOIN agencies a ON a.id = s.agency_id
            WHERE s.id = :id
        ", [':id' => $id]);
    }

    public function createSubscription(array $data): int
    {
        $this->query("
            INSERT INTO agency_subscriptions
                (agency_id, plan_id, status, billing_cycle, trial_ends_at, current_period_start, current_period_end, notes)
            VALUES
                (:agency_id, :plan_id, :status, :billing_cycle, :trial_ends_at, :period_start, :period_end, :notes)
        ", [
            ':agency_id'    => $data['agency_id'],
            ':plan_id'      => $data['plan_id'],
            ':status'       => $data['status'] ?? 'trialing',
            ':billing_cycle'=> $data['billing_cycle'] ?? 'monthly',
            ':trial_ends_at'=> $data['trial_ends_at'] ?? null,
            ':period_start' => $data['current_period_start'] ?? null,
            ':period_end'   => $data['current_period_end'] ?? null,
            ':notes'        => $data['notes'] ?? null,
        ]);
        return (int) $this->first("SELECT id FROM agency_subscriptions WHERE agency_id = :a ORDER BY id DESC LIMIT 1", [':a' => $data['agency_id']])['id'];
    }

    public function updateSubscription(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];

        foreach (['plan_id','status','billing_cycle','trial_ends_at','current_period_start','current_period_end','cancelled_at','external_subscription_id','notes'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]       = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) return;

        $fields[] = "updated_at = NOW()";
        $this->query("UPDATE agency_subscriptions SET " . implode(', ', $fields) . " WHERE id = :id", $params);
    }

    // ── Billing events ────────────────────────────────────────────────────────

    public function logEvent(int $agencyId, ?int $planId, string $type, float $amount, string $description, array $meta = []): void
    {
        $this->query("
            INSERT INTO billing_events (agency_id, plan_id, type, amount, description, metadata)
            VALUES (:agency_id, :plan_id, :type, :amount, :description, :metadata)
        ", [
            ':agency_id'   => $agencyId,
            ':plan_id'     => $planId,
            ':type'        => $type,
            ':amount'      => $amount,
            ':description' => $description,
            ':metadata'    => json_encode($meta),
        ]);
    }

    public function eventsForAgency(int $agencyId, int $limit = 20): array
    {
        return $this->all("
            SELECT e.*, p.name AS plan_name
            FROM billing_events e
            LEFT JOIN subscription_plans p ON p.id = e.plan_id
            WHERE e.agency_id = :agency_id
            ORDER BY e.created_at DESC
            LIMIT :limit
        ", [':agency_id' => $agencyId, ':limit' => $limit]);
    }

    // ── Usage counters ────────────────────────────────────────────────────────

    public function usageFor(int $agencyId): array
    {
        return $this->first("
            SELECT
                (SELECT COUNT(*) FROM clients        WHERE agency_id = :a1 AND status != 'archived') AS clients,
                (SELECT COUNT(*) FROM users          WHERE agency_id = :a2 AND status = 'active')    AS users,
                (SELECT COUNT(*) FROM ad_accounts    WHERE agency_id = :a3 AND status = 'active')    AS meta_accounts,
                (SELECT COUNT(*) FROM organic_accounts WHERE agency_id = :a4 AND status = 'active')  AS organic_accounts
        ", [':a1' => $agencyId, ':a2' => $agencyId, ':a3' => $agencyId, ':a4' => $agencyId]) ?? [];
    }
}
