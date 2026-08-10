<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * O Relatório Executivo exigia apenas `dashboard.view` — permissão que
 * social_media, designer e até os papéis de CLIENTE possuem. Resultado: quem
 * só produz conteúdo enxergava faturamento, recebido, em aberto e a receita
 * mês a mês da agência inteira.
 *
 * Estes testes fixam as duas regras novas:
 *   1. a tela é uma visão da agência → exige alguma permissão de ver cliente;
 *   2. dinheiro só aparece com permissão financeira;
 *   3. o relatório POR CLIENTE respeita o acesso ao cliente (era IDOR).
 */
class ExecutiveReportAccessTest extends FeatureTestCase
{
    private const PERM_CONTEUDO  = ['dashboard.view', 'clients.view', 'clients.view_all', 'content.view'];

    /** Papel `financial` depois do backfill (migration 28): ganhou view_all/view_basic. */
    private const PERM_FINANCEIRO = [
        'dashboard.view', 'clients.view_basic', 'clients.view_all',
        'invoices.view', 'financial_reports.view',
    ];

    public function test_perfil_de_cliente_com_dashboard_view_nao_abre_o_relatorio_executivo(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        // client_admin: dashboard.view + approvals + content.view, nenhuma clients.*
        $this->actingAs($user['id'], permissions: ['dashboard.view', 'approvals.view', 'content.view', 'drive_assets.view']);

        $this->assertSame(403, $this->get('/relatorio-executivo')->getStatus());
        $this->assertSame(403, $this->get('/executive-report')->getStatus());
    }

    public function test_social_media_abre_o_relatorio_mas_sem_nenhum_valor_financeiro(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->createClient($agencyId, 'Cliente Alfa');

        $this->actingAs($user['id'], permissions: self::PERM_CONTEUDO);

        $response = $this->get('/relatorio-executivo');
        $body     = $response->getBody();

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Cliente Alfa', $body, 'A parte operacional continua visível.');

        foreach (['Faturado Total', 'Recebido', 'Em Aberto', 'Receita — últimos 12 meses', 'Pendente'] as $rotuloFinanceiro) {
            $this->assertStringNotContainsString(
                $rotuloFinanceiro,
                $body,
                "O rótulo financeiro \"{$rotuloFinanceiro}\" não pode aparecer sem permissão financeira."
            );
        }
    }

    public function test_perfil_financeiro_continua_vendo_os_numeros(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->createClient($agencyId);

        $this->actingAs($user['id'], permissions: self::PERM_FINANCEIRO);

        $response = $this->get('/relatorio-executivo');

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Faturado Total', $response->getBody());
        $this->assertStringContainsString('Receita — últimos 12 meses', $response->getBody());
    }

    /** IDOR: o relatório por cliente não tinha ClientAccessMiddleware. */
    public function test_relatorio_de_cliente_sem_vinculo_e_negado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Cliente de Outro Gestor');

        // Tem permissão de ver cliente, mas nenhum vínculo e nenhum view_all.
        $this->actingAs($user['id'], permissions: ['dashboard.view', 'clients.view'], clientIds: []);

        $html = $this->get('/relatorio-executivo/cliente/' . $client['id']);
        $pdf  = $this->get('/relatorio-executivo/cliente/' . $client['id'] . '/pdf');

        $this->assertSame(403, $html->getStatus());
        $this->assertSame(403, $pdf->getStatus());
        $this->assertStringNotContainsString('Cliente de Outro Gestor', $html->getBody());
    }

    public function test_relatorio_de_cliente_esconde_o_bloco_financeiro_de_quem_nao_pode_ver(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Cliente Beta');

        $this->actingAs($user['id'], permissions: self::PERM_CONTEUDO, clientIds: [$client['id']]);

        $response = $this->get('/relatorio-executivo/cliente/' . $client['id']);
        $body     = $response->getBody();

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Cliente Beta', $body);
        $this->assertStringNotContainsString('Total Faturado', $body);
        $this->assertStringNotContainsString('Total Recebido', $body);
    }

    /**
     * Investimento em mídia é dinheiro: o relatório por cliente não pode ser
     * um atalho para quem não tem `ads_metrics.view`.
     */
    public function test_investimento_em_midia_so_aparece_com_ads_metrics_view(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Cliente Delta');
        $this->criarGastoDeMidia($agencyId, (int) $client['id'], '4321.99');

        // Equipe de conteúdo: sem financeiro e sem tráfego.
        $this->actingAs($user['id'], permissions: self::PERM_CONTEUDO, clientIds: []);
        $semTrafego = $this->get('/relatorio-executivo/cliente/' . $client['id'])->getBody();

        $this->assertStringNotContainsString('Investimento', $semTrafego);
        $this->assertStringNotContainsString('4.321,99', $semTrafego);

        // Gestor de tráfego continua vendo.
        $this->actingAs($user['id'], permissions: [...self::PERM_CONTEUDO, 'ads_metrics.view'], clientIds: []);
        $comTrafego = $this->get('/relatorio-executivo/cliente/' . $client['id'])->getBody();

        $this->assertStringContainsString('4.321,99', $comTrafego);
    }

    public function test_perfil_financeiro_abre_o_relatorio_do_cliente_sem_vinculo_manual(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Cliente Epsilon');

        $this->actingAs($user['id'], permissions: self::PERM_FINANCEIRO, clientIds: []);

        $this->assertSame(200, $this->get('/relatorio-executivo/cliente/' . $client['id'])->getStatus());
        $this->assertSame(200, $this->get('/relatorio-executivo/cliente/' . $client['id'] . '/pdf')->getStatus());
    }

    /** A tela de plano/cobrança não tinha guarda nenhuma. */
    public function test_assinatura_exige_settings_manage(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: self::PERM_CONTEUDO);
        $this->assertSame(403, $this->get('/subscription')->getStatus());

        $this->actingAs($user['id'], permissions: ['settings.manage']);
        $this->assertSame(200, $this->get('/subscription')->getStatus());
    }

    /** Conta de anúncios + um dia de métrica no período padrão (últimos 30 dias). */
    private function criarGastoDeMidia(int $agencyId, int $clientId, string $spend): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ad_accounts (agency_id, client_id, platform, platform_account_id, name, access_token, created_at)
             VALUES (:a, :c, 'meta', :pa, 'Conta de Teste', 'token', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':pa' => 'act_' . random_int(100000, 999999)]);
        $accountId = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO ad_daily_metrics (ad_account_id, entity_type, entity_id, date, platform,
                                           impressions, clicks, spend, conversions, conversion_value)
             VALUES (:acc, 'campaign', 1, CURRENT_DATE - 1, 'meta', 15000, 300, :spend, 12, 9000)"
        )->execute([':acc' => $accountId, ':spend' => $spend]);
    }
}
