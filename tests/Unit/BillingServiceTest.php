<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SubscriptionRepository;
use App\Services\BillingService;
use PHPUnit\Framework\TestCase;

class BillingServiceTest extends TestCase
{
    private function serviceWith(?array $subscription, array $usage): BillingService
    {
        $repo = $this->createMock(SubscriptionRepository::class);
        $repo->method('findActiveByAgency')->willReturn($subscription);
        $repo->method('usageFor')->willReturn($usage);
        // findPlanBySlug só é chamado no fallback "free" (subscription null)
        $repo->method('findPlanBySlug')->willReturn(null);

        return new BillingService($repo);
    }

    public function test_under_limit_allows(): void
    {
        $svc = $this->serviceWith(['max_clients' => 5], ['clients' => 3]);
        $this->assertTrue($svc->checkLimit(1, 'clients'));
    }

    public function test_at_limit_blocks(): void
    {
        $svc = $this->serviceWith(['max_clients' => 5], ['clients' => 5]);
        $this->assertFalse($svc->checkLimit(1, 'clients'));
    }

    public function test_null_limit_is_unlimited(): void
    {
        $svc = $this->serviceWith(['max_clients' => null], ['clients' => 999]);
        $this->assertTrue($svc->checkLimit(1, 'clients'));
    }

    public function test_no_subscription_allows_by_default(): void
    {
        // Sem assinatura e sem plano free → getSubscription retorna null → libera.
        $svc = $this->serviceWith(null, ['clients' => 999]);
        $this->assertTrue($svc->checkLimit(1, 'clients'));
    }
}
