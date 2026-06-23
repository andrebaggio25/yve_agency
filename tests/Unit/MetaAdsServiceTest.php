<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformSettingsRepository;
use App\Services\MetaAdsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetaAdsServiceTest extends TestCase
{
    private function service(): MetaAdsService
    {
        // Sem chamadas de rede nestes testes; o repositório é apenas dependência.
        return new MetaAdsService($this->createMock(PlatformSettingsRepository::class));
    }

    #[DataProvider('budgetProvider')]
    public function test_parse_budget_to_cents(?string $input, int $expected): void
    {
        $this->assertSame($expected, $this->service()->parseBudgetToCents($input));
    }

    public static function budgetProvider(): array
    {
        return [
            'simples'         => ['50', 5000],
            'com prefixo'     => ['R$ 50/dia', 5000],
            'decimal vírgula' => ['R$ 50,90', 5090],
            'milhar'          => ['R$ 1.250,50', 125050],
            'null'            => [null, 0],
            'vazio'           => ['', 0],
            'sem número'      => ['grátis', 0],
        ];
    }

    public function test_normalize_insight_calculates_roas_and_extracts_actions(): void
    {
        $row = [
            'date_start'    => '2026-06-01',
            'impressions'   => '1000',
            'reach'         => '800',
            'frequency'     => '1.25',
            'clicks'        => '50',
            'inline_link_clicks' => '40',
            'spend'         => '100',
            'cpc'           => '2',
            'cpm'           => '100',
            'ctr'           => '5',
            'cpp'           => '0.125',
            'actions'       => [
                ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '4'],
            ],
            'action_values' => [
                ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '400'],
            ],
        ];

        $result = $this->service()->normalizeInsight($row, 'campaign', 7, 3);

        $this->assertSame('campaign', $result['entity_type']);
        $this->assertSame(7, $result['entity_id']);
        $this->assertSame(3, $result['ad_account_id']);
        $this->assertSame('2026-06-01', $result['date']);
        $this->assertSame(1000, $result['impressions']);
        $this->assertSame(40, $result['link_clicks']);
        $this->assertSame(100.0, $result['spend']);
        $this->assertSame(4, $result['conversions']);
        $this->assertSame(400.0, $result['conversion_value']);
        // ROAS = 400 / 100 = 4
        $this->assertSame(4.0, $result['roas']);
    }

    public function test_normalize_insight_roas_zero_when_no_spend(): void
    {
        $result = $this->service()->normalizeInsight(
            ['date_start' => '2026-06-01', 'spend' => '0'],
            'ad',
            1,
            1
        );

        $this->assertSame(0, $result['roas']);
        $this->assertSame(0, $result['conversions']);
    }
}
