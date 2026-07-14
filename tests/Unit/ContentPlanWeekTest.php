<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ContentPlanService;
use PHPUnit\Framework\TestCase;

/**
 * A agência envia planificação SEMPRE de segunda a domingo. Estes helpers são
 * a âncora de todo o fluxo semanal: criação, edição, duplicação e o nome
 * padrão do plano derivam deles.
 */
class ContentPlanWeekTest extends TestCase
{
    public function test_monday_of_normaliza_qualquer_dia_para_a_segunda(): void
    {
        // 2026-07-14 é terça; a segunda daquela semana é 13/07.
        $this->assertSame('2026-07-13', ContentPlanService::mondayOf('2026-07-14'));
        // Domingo pertence à semana que COMEÇOU na segunda anterior.
        $this->assertSame('2026-07-13', ContentPlanService::mondayOf('2026-07-19'));
        // Segunda-feira fica onde está.
        $this->assertSame('2026-07-13', ContentPlanService::mondayOf('2026-07-13'));
    }

    public function test_sunday_of_e_seis_dias_apos_a_segunda(): void
    {
        $this->assertSame('2026-07-19', ContentPlanService::sundayOf('2026-07-15'));
        $this->assertSame('2026-07-19', ContentPlanService::sundayOf('2026-07-19'));
    }

    public function test_virada_de_mes_e_de_ano_nao_quebra(): void
    {
        // 2026-01-01 é quinta → a segunda daquela semana é 29/12/2025.
        $this->assertSame('2025-12-29', ContentPlanService::mondayOf('2026-01-01'));
        $this->assertSame('2026-01-04', ContentPlanService::sundayOf('2026-01-01'));
    }

    public function test_titulo_automatico_tem_cliente_e_periodo_seg_dom(): void
    {
        $title = ContentPlanService::defaultTitle('Studio Aline', '2026-07-15');

        $this->assertSame('STUDIO ALINE | 13/07 – 19/07', $title);
    }

    public function test_titulo_sem_cliente_ainda_diz_a_semana(): void
    {
        $this->assertSame('Semana 13/07 – 19/07', ContentPlanService::defaultTitle('', '2026-07-13'));
    }
}
