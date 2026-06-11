<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBillingTables extends AbstractMigration
{
    public function up(): void
    {
        // ── Planos de assinatura ──────────────────────────────────────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS subscription_plans (
                id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                name           VARCHAR(100) NOT NULL,
                slug           VARCHAR(100) NOT NULL UNIQUE,
                description    TEXT,
                price_monthly  NUMERIC(10,2) NOT NULL DEFAULT 0,
                price_yearly   NUMERIC(10,2) NOT NULL DEFAULT 0,
                max_clients    INT,
                max_users      INT,
                max_meta_accounts    INT,
                max_organic_accounts INT,
                features       JSONB NOT NULL DEFAULT '[]',
                is_active      BOOLEAN NOT NULL DEFAULT true,
                sort_order     INT NOT NULL DEFAULT 0,
                created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        // ── Assinaturas das agências ──────────────────────────────────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS agency_subscriptions (
                id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                agency_id                BIGINT NOT NULL REFERENCES agencies(id) ON DELETE CASCADE,
                plan_id                  BIGINT NOT NULL REFERENCES subscription_plans(id) ON DELETE RESTRICT,
                status                   VARCHAR(30) NOT NULL DEFAULT 'trialing',
                billing_cycle            VARCHAR(10) NOT NULL DEFAULT 'monthly',
                trial_ends_at            TIMESTAMPTZ,
                current_period_start     TIMESTAMPTZ,
                current_period_end       TIMESTAMPTZ,
                cancelled_at             TIMESTAMPTZ,
                external_subscription_id VARCHAR(255),
                notes                    TEXT,
                created_at               TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at               TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        $this->execute("CREATE UNIQUE INDEX IF NOT EXISTS agency_subscriptions_agency_unique ON agency_subscriptions(agency_id) WHERE status != 'cancelled'");
        $this->execute("CREATE INDEX IF NOT EXISTS agency_subscriptions_agency_idx ON agency_subscriptions(agency_id)");

        // ── Eventos de billing ────────────────────────────────────────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS billing_events (
                id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                agency_id   BIGINT NOT NULL REFERENCES agencies(id) ON DELETE CASCADE,
                plan_id     BIGINT REFERENCES subscription_plans(id) ON DELETE SET NULL,
                type        VARCHAR(50) NOT NULL,
                amount      NUMERIC(10,2) NOT NULL DEFAULT 0,
                description TEXT,
                metadata    JSONB NOT NULL DEFAULT '{}',
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        $this->execute("CREATE INDEX IF NOT EXISTS billing_events_agency_idx ON billing_events(agency_id)");

        // ── Planos padrão ─────────────────────────────────────────────────────
        $this->execute("
            INSERT INTO subscription_plans (name, slug, description, price_monthly, price_yearly, max_clients, max_users, max_meta_accounts, max_organic_accounts, features, sort_order)
            VALUES
                ('Free',       'free',       'Para experimentar', 0,     0,     3,    2,    1,   1,   '[\"content_plans\",\"approvals\"]', 0),
                ('Starter',    'starter',    'Para pequenas agências', 297, 2673, 10,   5,    3,   3,   '[\"content_plans\",\"approvals\",\"financial\",\"tasks\",\"portal\"]', 1),
                ('Pro',        'pro',        'Para agências em crescimento', 597, 5373, 30, 15, 10, 10, '[\"content_plans\",\"approvals\",\"financial\",\"tasks\",\"portal\",\"ads\",\"organic\",\"ai_insights\"]', 2),
                ('Enterprise', 'enterprise', 'Sem limites',       1497, 13473, NULL, NULL, NULL, NULL, '[\"all\"]', 3)
            ON CONFLICT (slug) DO NOTHING
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS billing_events");
        $this->execute("DROP TABLE IF EXISTS agency_subscriptions");
        $this->execute("DROP TABLE IF EXISTS subscription_plans");
    }
}
