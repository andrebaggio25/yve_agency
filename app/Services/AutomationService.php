<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutomationRepository;

/**
 * Regras de negócio do motor de automação: catálogo, agenda, e o "gate" de
 * duas camadas (agência ativa + opt-in por cliente) + idempotência.
 */
class AutomationService
{
    private ?array $catalog = null;

    public function __construct(private readonly AutomationRepository $repo) {}

    // ── Catálogo ────────────────────────────────────────────────────────────

    public function catalog(): array
    {
        return $this->catalog ??= require base_path('config/automations.php');
    }

    public function definition(string $key): ?array
    {
        return $this->catalog()[$key] ?? null;
    }

    /** Automações com opt-in por cliente (applies_to = client). */
    public function clientAutomations(): array
    {
        return array_filter($this->catalog(), fn($d) => ($d['applies_to'] ?? 'agency') === 'client');
    }

    // ── Estado por agência (merge catálogo + automation_rules) ──────────────

    /**
     * Visão para a UI: cada automação do catálogo + estado salvo da agência.
     * @return array<string, array>
     */
    public function rulesForAgency(int $agencyId): array
    {
        $stored = [];
        foreach ($this->repo->rulesForAgency($agencyId) as $r) {
            $stored[$r['automation_key']] = $r;
        }

        $out = [];
        foreach ($this->catalog() as $key => $def) {
            $row = $stored[$key] ?? null;
            $out[$key] = $def + [
                'key'            => $key,
                'status'         => $row['status'] ?? 'inactive',
                'scheduled_time' => $row['scheduled_time'] ?? ($def['time'] ?? '08:00'),
                'scheduled_day'  => $row['scheduled_day'] ?? ($def['day'] ?? null),
                'channels_on'    => isset($row['channels']) ? (json_decode($row['channels'], true) ?: ($def['channels'] ?? [])) : ($def['channels'] ?? []),
            ];
        }
        return $out;
    }

    public function isAgencyRuleActive(int $agencyId, string $key): bool
    {
        $rule = $this->repo->findRule($agencyId, $key);
        return $rule !== null && ($rule['status'] ?? '') === 'active';
    }

    /**
     * Gate principal: a automação está habilitada para este cliente?
     * Camada 1: agência ativou. Camada 2 (só applies_to=client): opt-in do cliente.
     */
    public function isEnabledForClient(int $agencyId, ?int $clientId, string $key): bool
    {
        $def = $this->definition($key);
        if (!$def) return false;
        if (!$this->isAgencyRuleActive($agencyId, $key)) return false;

        if (($def['applies_to'] ?? 'agency') === 'agency') {
            return true;
        }
        if (!$clientId) return false;
        return $this->repo->clientEnabled($clientId, $key); // opt-in: ausência = false
    }

    // ── Idempotência ────────────────────────────────────────────────────────

    public function shouldRun(string $key, string $dedupeKey): bool
    {
        return !$this->repo->logExists($key, $dedupeKey);
    }

    public function markRan(int $agencyId, ?int $clientId, string $key, string $dedupeKey, string $status = 'done', ?string $channel = null, ?string $detail = null): void
    {
        $this->repo->writeLog([
            'agency_id'      => $agencyId,
            'client_id'      => $clientId,
            'automation_key' => $key,
            'dedupe_key'     => $dedupeKey,
            'channel'        => $channel,
            'status'         => $status,
            'detail'         => $detail,
        ]);
    }

    // ── Configuração (UI super_admin) ───────────────────────────────────────

    /** Cria linhas faltantes em automation_rules a partir do catálogo (default inativo). */
    public function ensureRulesForAgency(int $agencyId): void
    {
        $existing = [];
        foreach ($this->repo->rulesForAgency($agencyId) as $r) {
            $existing[$r['automation_key']] = true;
        }
        foreach ($this->catalog() as $key => $def) {
            if (isset($existing[$key])) continue;
            $this->repo->upsertRule($agencyId, $key, [
                'name'           => $def['label'] ?? $key,
                'status'         => 'inactive',
                'frequency'      => $def['frequency'] ?? null,
                'scheduled_day'  => $def['day'] ?? null,
                'scheduled_time' => $def['time'] ?? null,
                'channels'       => $def['channels'] ?? [],
                'next_run_at'    => null,
            ]);
        }
    }

    public function configureAgencyRule(int $agencyId, string $key, bool $active, ?string $time = null, ?string $day = null, ?array $channels = null): void
    {
        $def = $this->definition($key);
        if (!$def) return;

        $time      = $time ?: ($def['time'] ?? '08:00');
        $day       = $day ?: ($def['day'] ?? null);
        $frequency = $def['frequency'] ?? null;

        $this->repo->upsertRule($agencyId, $key, [
            'name'           => $def['label'] ?? $key,
            'status'         => $active ? 'active' : 'inactive',
            'frequency'      => $frequency,
            'scheduled_day'  => $day,
            'scheduled_time' => $time,
            'channels'       => $channels ?? ($def['channels'] ?? []),
            // Agenda só vale para automações schedule e quando ativadas.
            'next_run_at'    => ($active && $frequency) ? $this->computeNext($frequency, $day, $time) : null,
        ]);
    }

    // ── Opt-in por cliente ──────────────────────────────────────────────────

    public function settingsForClient(int $clientId): array
    {
        return $this->repo->settingsForClient($clientId);
    }

    public function settingsMatrix(int $agencyId): array
    {
        return $this->repo->settingsMatrix($agencyId);
    }

    public function setClientSetting(int $agencyId, int $clientId, string $key, bool $enabled): void
    {
        $this->repo->upsertClientSetting($agencyId, $clientId, $key, $enabled);
    }

    /** @param array<int, array<string,bool>> $matrix [client_id => [key => bool]] */
    public function bulkSetMatrix(int $agencyId, array $matrix): void
    {
        $clientKeys = array_keys($this->clientAutomations());
        foreach ($matrix as $clientId => $keys) {
            foreach ($clientKeys as $key) {
                $this->repo->upsertClientSetting($agencyId, (int) $clientId, $key, !empty($keys[$key]));
            }
        }
    }

    // ── Agenda ──────────────────────────────────────────────────────────────

    /** Próximo horário de execução estritamente futuro. Usado na ativação e no reschedule. */
    public function computeNext(?string $frequency, ?string $day, ?string $time): ?string
    {
        if (!$frequency) return null; // evento — sem agenda
        $time = $time ?: '08:00';
        $now  = time();

        $ts = match ($frequency) {
            'daily'   => strtotime(date('Y-m-d') . ' ' . $time),
            'weekly'  => strtotime('this ' . ($day ?: 'Monday') . ' ' . $time) ?: strtotime('next ' . ($day ?: 'Monday') . ' ' . $time),
            'monthly' => strtotime(date('Y-m-') . str_pad((string) ((int) ($day ?: 1)), 2, '0', STR_PAD_LEFT) . ' ' . $time),
            default   => strtotime('+1 hour'),
        };

        if ($ts === false || $ts <= $now) {
            $ts = match ($frequency) {
                'daily'   => strtotime('+1 day', strtotime(date('Y-m-d') . ' ' . $time)),
                'weekly'  => strtotime('next ' . ($day ?: 'Monday') . ' ' . $time),
                'monthly' => strtotime('+1 month', strtotime(date('Y-m-') . str_pad((string) ((int) ($day ?: 1)), 2, '0', STR_PAD_LEFT) . ' ' . $time)),
                default   => strtotime('+1 hour'),
            };
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
