<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SubscriptionRepository;

class BillingService
{
    public function __construct(private readonly SubscriptionRepository $repo) {}

    public function getSubscription(int $agencyId): ?array
    {
        $sub = $this->repo->findActiveByAgency($agencyId);

        if (!$sub) {
            // Sem assinatura: tratar como free
            $free = $this->repo->findPlanBySlug('free');
            return $free ? array_merge($free, [
                'status'        => 'none',
                'billing_cycle' => 'monthly',
                'plan_name'     => $free['name'],
                'plan_slug'     => $free['slug'],
            ]) : null;
        }

        if (isset($sub['features']) && is_string($sub['features'])) {
            $sub['features'] = json_decode($sub['features'], true) ?? [];
        }

        return $sub;
    }

    public function getUsage(int $agencyId): array
    {
        return $this->repo->usageFor($agencyId);
    }

    public function checkLimit(int $agencyId, string $resource): bool
    {
        $sub = $this->getSubscription($agencyId);
        if (!$sub) return true; // sem plano = sem limite (segurança: deixar passar)

        $limitKey = "max_{$resource}"; // max_clients, max_users, etc.
        $limit = $sub[$limitKey] ?? null;

        if ($limit === null) return true; // null = ilimitado

        $usage = $this->getUsage($agencyId);
        $current = (int) ($usage[$resource] ?? 0);

        return $current < (int) $limit;
    }

    public function usageSummary(int $agencyId): array
    {
        $sub   = $this->getSubscription($agencyId);
        $usage = $this->getUsage($agencyId);

        $resources = ['clients', 'users', 'meta_accounts', 'organic_accounts'];
        $summary   = [];

        foreach ($resources as $r) {
            $limit   = $sub["max_{$r}"] ?? null;
            $current = (int) ($usage[$r] ?? 0);
            $pct     = ($limit && $limit > 0) ? min(100, round($current / $limit * 100)) : 0;

            $summary[$r] = [
                'current' => $current,
                'limit'   => $limit,
                'pct'     => $pct,
                'over'    => $limit !== null && $current >= (int) $limit,
            ];
        }

        return $summary;
    }

    public function assignPlan(int $agencyId, int $planId, string $billingCycle = 'monthly', string $status = 'active'): void
    {
        $existing = $this->repo->findActiveByAgency($agencyId);
        $plan     = $this->repo->findPlanById($planId);

        if (!$plan) return;

        $periodEnd = $billingCycle === 'yearly'
            ? date('Y-m-d H:i:s', strtotime('+1 year'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        if ($existing) {
            $this->repo->updateSubscription($existing['id'], [
                'plan_id'              => $planId,
                'status'               => $status,
                'billing_cycle'        => $billingCycle,
                'current_period_start' => date('Y-m-d H:i:s'),
                'current_period_end'   => $periodEnd,
            ]);
            $this->repo->logEvent($agencyId, $planId, 'plan_changed', 0,
                "Plano alterado para {$plan['name']}");
        } else {
            $this->repo->createSubscription([
                'agency_id'            => $agencyId,
                'plan_id'              => $planId,
                'status'               => $status,
                'billing_cycle'        => $billingCycle,
                'current_period_start' => date('Y-m-d H:i:s'),
                'current_period_end'   => $periodEnd,
            ]);
            $this->repo->logEvent($agencyId, $planId, 'subscription_created', 0,
                "Assinatura do plano {$plan['name']} criada");
        }
    }

    public function cancelSubscription(int $agencyId): void
    {
        $existing = $this->repo->findActiveByAgency($agencyId);
        if (!$existing) return;

        $this->repo->updateSubscription($existing['id'], [
            'status'       => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
        ]);
        $this->repo->logEvent($agencyId, (int) $existing['plan_id'], 'cancelled', 0, 'Assinatura cancelada');
    }

    public function allPlans(): array
    {
        return $this->repo->allPlans();
    }
}
