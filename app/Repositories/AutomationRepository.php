<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Acesso às tabelas do motor de automação. Todos os métodos recebem agency_id
 * explícito — NUNCA dependem de Auth/sessão, pois rodam também no contexto do
 * worker/cron (sem sessão).
 */
class AutomationRepository extends Repository
{
    protected string $table = 'automation_rules';

    // ── automation_rules ────────────────────────────────────────────────────

    public function rulesForAgency(int $agencyId): array
    {
        return $this->all(
            "SELECT * FROM automation_rules WHERE agency_id = :a ORDER BY automation_key",
            [':a' => $agencyId],
        );
    }

    public function findRule(int $agencyId, string $key): ?array
    {
        return $this->first(
            "SELECT * FROM automation_rules WHERE agency_id = :a AND automation_key = :k LIMIT 1",
            [':a' => $agencyId, ':k' => $key],
        );
    }

    public function findRuleById(int $id): ?array
    {
        return $this->first("SELECT * FROM automation_rules WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    /** Regras agendadas (frequency != null) prontas para rodar, de todas as agências. */
    public function dueScheduledRules(): array
    {
        return $this->all("
            SELECT * FROM automation_rules
            WHERE status = 'active'
              AND frequency IS NOT NULL
              AND (next_run_at IS NULL OR next_run_at <= NOW())
            ORDER BY agency_id
        ");
    }

    public function upsertRule(int $agencyId, string $key, array $data): void
    {
        $this->query("
            INSERT INTO automation_rules
                (agency_id, automation_key, name, status, frequency, scheduled_day, scheduled_time, channels, next_run_at, created_at, updated_at)
            VALUES
                (:agency_id, :key, :name, :status, :frequency, :day, :time, :channels, :next_run_at, NOW(), NOW())
            ON CONFLICT (agency_id, automation_key) DO UPDATE SET
                name           = EXCLUDED.name,
                status         = EXCLUDED.status,
                frequency      = EXCLUDED.frequency,
                scheduled_day  = EXCLUDED.scheduled_day,
                scheduled_time = EXCLUDED.scheduled_time,
                channels       = EXCLUDED.channels,
                next_run_at    = EXCLUDED.next_run_at,
                updated_at     = NOW()
        ", [
            ':agency_id'   => $agencyId,
            ':key'         => $key,
            ':name'        => $data['name'] ?? null,
            ':status'      => $data['status'] ?? 'inactive',
            ':frequency'   => $data['frequency'] ?? null,
            ':day'         => $data['scheduled_day'] ?? null,
            ':time'        => $data['scheduled_time'] ?? null,
            ':channels'    => isset($data['channels']) ? json_encode($data['channels']) : null,
            ':next_run_at' => $data['next_run_at'] ?? null,
        ]);
    }

    public function touchRun(int $id, ?string $nextRunAt): void
    {
        $this->query(
            "UPDATE automation_rules SET last_run_at = NOW(), next_run_at = :n, updated_at = NOW() WHERE id = :id",
            [':n' => $nextRunAt, ':id' => $id],
        );
    }

    // ── client_automation_settings ──────────────────────────────────────────

    public function settingsForClient(int $clientId): array
    {
        $rows = $this->all(
            "SELECT automation_key, enabled FROM client_automation_settings WHERE client_id = :c",
            [':c' => $clientId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['automation_key']] = (bool) $r['enabled'];
        }
        return $out;
    }

    public function clientEnabled(int $clientId, string $key): bool
    {
        $row = $this->first(
            "SELECT enabled FROM client_automation_settings WHERE client_id = :c AND automation_key = :k LIMIT 1",
            [':c' => $clientId, ':k' => $key],
        );
        return $row ? (bool) $row['enabled'] : false;
    }

    /** @return array<int, array<string,bool>>  [client_id => [key => enabled]] */
    public function settingsMatrix(int $agencyId): array
    {
        $rows = $this->all(
            "SELECT client_id, automation_key, enabled FROM client_automation_settings WHERE agency_id = :a",
            [':a' => $agencyId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['client_id']][$r['automation_key']] = (bool) $r['enabled'];
        }
        return $out;
    }

    public function upsertClientSetting(int $agencyId, int $clientId, string $key, bool $enabled): void
    {
        $this->query("
            INSERT INTO client_automation_settings (agency_id, client_id, automation_key, enabled, created_at, updated_at)
            VALUES (:a, :c, :k, :e, NOW(), NOW())
            ON CONFLICT (client_id, automation_key) DO UPDATE SET
                enabled = EXCLUDED.enabled, updated_at = NOW()
        ", [':a' => $agencyId, ':c' => $clientId, ':k' => $key, ':e' => $enabled ? 'true' : 'false']);
    }

    // ── automation_log (idempotência) ───────────────────────────────────────

    public function logExists(string $key, string $dedupeKey): bool
    {
        $row = $this->first(
            "SELECT 1 FROM automation_log WHERE automation_key = :k AND dedupe_key = :d LIMIT 1",
            [':k' => $key, ':d' => $dedupeKey],
        );
        return $row !== null;
    }

    public function writeLog(array $data): void
    {
        $this->query("
            INSERT INTO automation_log (agency_id, client_id, automation_key, dedupe_key, channel, status, detail, created_at)
            VALUES (:a, :c, :k, :d, :ch, :st, :detail, NOW())
            ON CONFLICT (automation_key, dedupe_key) DO NOTHING
        ", [
            ':a'      => $data['agency_id'],
            ':c'      => $data['client_id'] ?? null,
            ':k'      => $data['automation_key'],
            ':d'      => $data['dedupe_key'],
            ':ch'     => $data['channel'] ?? null,
            ':st'     => $data['status'] ?? 'done',
            ':detail' => $data['detail'] ?? null,
        ]);
    }
}
