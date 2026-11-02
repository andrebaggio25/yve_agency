<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * SEC-11 — fronteira de tenant nos clientes e no conteúdo.
 *
 * Dois furos diferentes, e os dois passavam pelo mesmo ponto cego:
 *
 *   1. `clients.view_all` liberava cliente de QUALQUER agência, porque nada
 *      verificava de quem era o cliente. O `ClientAccessMiddleware` não era
 *      fronteira de tenant — era só alcance.
 *   2. Dentro da agência, as rotas de conteúdo não olhavam o acesso ao cliente:
 *      quem não tem `view_all` abria a planificação de qualquer cliente pela URL
 *      e filtrava a lista com `?client_id=` alheio.
 *
 * Cada negativa aqui tem a positiva ao lado: um 404 só prova algo quando a
 * mesma rota, com o mesmo usuário, responde 200 para o que é dele.
 */
class TenantBoundaryTest extends FeatureTestCase
{
    /** Papel de conteúdo depois do backfill (migration 28): tem view_all. */
    private const PERM_CONTEUDO = [
        'dashboard.view', 'clients.view', 'clients.view_all',
        'content.view', 'content.create', 'content.edit', 'content.delete',
    ];

    /** Gestor sem `view_all`: só alcança os clientes vinculados a ele. */
    private const PERM_SEM_VIEW_ALL = [
        'dashboard.view', 'clients.view', 'clients.edit',
        'content.view', 'content.create', 'content.edit', 'content.delete',
    ];

    // ── 1. Outra agência ──────────────────────────────────────────────────────

    public function test_view_all_nao_alcanca_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_CONTEUDO, clientIds: []);

        // Positiva: a rota funciona para o cliente da própria agência.
        $proprio = $this->get("/clientes/{$clienteA['id']}");
        $this->assertSame(200, $proprio->getStatus());
        $this->assertStringContainsString('Cliente da A', $proprio->getBody());

        // Negativa: o mesmo usuário, com view_all, não atravessa a agência.
        $alheio = $this->get("/clientes/{$clienteB['id']}");
        $this->assertContains($alheio->getStatus(), [403, 404]);
        $this->assertStringNotContainsString('Cliente da B', $alheio->getBody());
    }

    public function test_plano_de_outra_agencia_da_404(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $planoA   = $this->createPlan($agenciaA, (int) $clienteA['id'], 'Plano da A');
        $planoB   = $this->createPlan($agenciaB, (int) $clienteB['id'], 'Plano da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_CONTEUDO, clientIds: []);

        $this->assertSame(200, $this->get("/conteudo/{$planoA}")->getStatus());

        $alheio = $this->get("/conteudo/{$planoB}");
        $this->assertSame(404, $alheio->getStatus());
        $this->assertStringNotContainsString('Plano da B', $alheio->getBody());
    }

    // ── 2. Dentro da agência, sem view_all ────────────────────────────────────

    public function test_sem_view_all_o_plano_de_cliente_alheio_da_404(): void
    {
        $agencyId = $this->createAgency();
        $meu      = $this->createClient($agencyId, 'Meu Cliente');
        $alheio   = $this->createClient($agencyId, 'Cliente do Colega');
        $planoMeu = $this->createPlan($agencyId, (int) $meu['id'], 'Plano Meu');
        $planoDele= $this->createPlan($agencyId, (int) $alheio['id'], 'Plano do Colega');
        $user     = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs($user['id'], permissions: self::PERM_SEM_VIEW_ALL, clientIds: [(int) $meu['id']]);

        $this->assertSame(200, $this->get("/conteudo/{$planoMeu}")->getStatus());

        $negado = $this->get("/conteudo/{$planoDele}");
        $this->assertSame(404, $negado->getStatus());
        $this->assertStringNotContainsString('Plano do Colega', $negado->getBody());

        // E o mesmo vale para editar e excluir.
        $this->assertSame(404, $this->get("/conteudo/{$planoDele}/editar")->getStatus());
        $this->assertSame(404, $this->post("/conteudo/{$planoDele}", ['_method' => 'DELETE', 'title' => 'x'])->getStatus());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM content_plans WHERE id = {$planoDele}")->fetchColumn(),
            'O plano do colega não pode ter sido excluído.',
        );
    }

    public function test_filtro_client_id_nao_vaza_plano_de_cliente_alheio(): void
    {
        $agencyId = $this->createAgency();
        $meu      = $this->createClient($agencyId, 'Meu Cliente');
        $alheio   = $this->createClient($agencyId, 'Cliente do Colega');
        $this->createPlan($agencyId, (int) $meu['id'], 'Plano Meu');
        $this->createPlan($agencyId, (int) $alheio['id'], 'Plano do Colega');
        $user     = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs($user['id'], permissions: self::PERM_SEM_VIEW_ALL, clientIds: [(int) $meu['id']]);

        // Positiva: o filtro do cliente dele funciona.
        $meuFiltro = $this->get('/conteudo?client_id=' . $meu['id']);
        $this->assertSame(200, $meuFiltro->getStatus());
        $this->assertStringContainsString('Plano Meu', $meuFiltro->getBody());

        // Negativa: ?client_id= alheio não devolve o plano do colega.
        $alheioFiltro = $this->get('/conteudo?client_id=' . $alheio['id']);
        $this->assertSame(200, $alheioFiltro->getStatus());
        $this->assertStringNotContainsString('Plano do Colega', $alheioFiltro->getBody());

        // Nem a listagem sem filtro.
        $semFiltro = $this->get('/conteudo');
        $this->assertStringContainsString('Plano Meu', $semFiltro->getBody());
        $this->assertStringNotContainsString('Plano do Colega', $semFiltro->getBody());
    }

    public function test_calendario_nao_vaza_post_de_cliente_alheio(): void
    {
        $agencyId = $this->createAgency();
        $meu      = $this->createClient($agencyId, 'Meu Cliente');
        $alheio   = $this->createClient($agencyId, 'Cliente do Colega');
        $planoMeu = $this->createPlan($agencyId, (int) $meu['id'], 'Plano Meu');
        $planoDele= $this->createPlan($agencyId, (int) $alheio['id'], 'Plano do Colega');
        $this->createItem($planoMeu, (int) $meu['id'], 'Post Meu');
        $this->createItem($planoDele, (int) $alheio['id'], 'Post do Colega');
        $user     = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs($user['id'], permissions: self::PERM_SEM_VIEW_ALL, clientIds: [(int) $meu['id']]);

        $mes  = date('Y-m');
        $body = $this->get("/conteudo/calendario?month={$mes}&client_id=" . $alheio['id'])->getBody();

        $this->assertStringNotContainsString('Post do Colega', $body);
        $this->assertStringContainsString('Post Meu', $this->get("/conteudo/calendario?month={$mes}")->getBody());
    }

    public function test_post_de_cliente_alheio_nao_e_editavel_por_planid_permitido(): void
    {
        $agencyId  = $this->createAgency();
        $meu       = $this->createClient($agencyId, 'Meu Cliente');
        $alheio    = $this->createClient($agencyId, 'Cliente do Colega');
        $planoMeu  = $this->createPlan($agencyId, (int) $meu['id'], 'Plano Meu');
        $planoDele = $this->createPlan($agencyId, (int) $alheio['id'], 'Plano do Colega');
        $itemMeu   = $this->createItem($planoMeu, (int) $meu['id'], 'Post Meu');
        $itemDele  = $this->createItem($planoDele, (int) $alheio['id'], 'Post do Colega');
        $user      = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs($user['id'], permissions: self::PERM_SEM_VIEW_ALL, clientIds: [(int) $meu['id']]);

        // Positiva: o post dele, pelo plano dele, atualiza.
        $ok = $this->post("/conteudo/{$planoMeu}/items/{$itemMeu}", ['_method' => 'PUT', 'title' => 'Post Renomeado']);
        $this->assertSame(200, $ok->getStatus());
        $this->assertSame('Post Renomeado', $this->itemTitle($itemMeu));

        // Negativa: planId permitido + itemId alheio não passa.
        $negado = $this->post("/conteudo/{$planoMeu}/items/{$itemDele}", ['_method' => 'PUT', 'title' => 'Invadido']);
        $this->assertSame(404, $negado->getStatus());
        $this->assertSame('Post do Colega', $this->itemTitle($itemDele));

        // Excluir também não.
        $this->post("/conteudo/{$planoMeu}/items/{$itemDele}", ['_method' => 'DELETE']);
        $this->assertSame('Post do Colega', $this->itemTitle($itemDele));
    }

    // ── 3. Acesso de usuários ao cliente ──────────────────────────────────────

    public function test_nao_vincula_usuario_de_outra_agencia_ao_cliente(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $adminA   = $this->createUser($agenciaA, 'admin@a.test');
        $colegaA  = $this->createUser($agenciaA, 'colega@a.test');
        $estranho = $this->createUser($agenciaB, 'de-fora@b.test');

        $this->actingAs($adminA['id'], permissions: ['clients.view', 'clients.edit', 'clients.view_all']);

        // Positiva: usuário da própria agência entra.
        $this->post("/clientes/{$clienteA['id']}/acesso", ['user_id' => $colegaA['id'], 'access_level' => 'standard']);
        $this->assertSame(1, $this->accessCount((int) $clienteA['id'], $colegaA['id']));

        // Negativa: user_id de outra agência não entra.
        $this->post("/clientes/{$clienteA['id']}/acesso", ['user_id' => $estranho['id'], 'access_level' => 'standard']);
        $this->assertSame(0, $this->accessCount((int) $clienteA['id'], $estranho['id']));

        // E a revogação do vínculo legítimo continua funcionando.
        $this->post("/clientes/{$clienteA['id']}/acesso/{$colegaA['id']}", ['_method' => 'DELETE']);
        $this->assertSame(0, $this->accessCount((int) $clienteA['id'], $colegaA['id']));
    }

    public function test_nao_revoga_acesso_de_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userB    = $this->createUser($agenciaB, 'b@test.com');
        $adminA   = $this->createUser($agenciaA, 'a@test.com');

        $this->pdo->prepare(
            'INSERT INTO client_user_access (client_id, user_id, access_level, created_at) VALUES (:c, :u, :l, NOW())'
        )->execute([':c' => $clienteB['id'], ':u' => $userB['id'], ':l' => 'standard']);

        $this->actingAs($adminA['id'], permissions: ['clients.view', 'clients.edit', 'clients.view_all']);

        $resp = $this->post(
            "/clientes/{$clienteB['id']}/acesso/{$userB['id']}",
            ['_method' => 'DELETE'],
        );

        $this->assertContains($resp->getStatus(), [403, 404]);
        $this->assertSame(1, $this->accessCount((int) $clienteB['id'], $userB['id']), 'O vínculo da outra agência continua.');
    }

    public function test_aprovacoes_nao_abrem_plano_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $planoA   = $this->createPlan($agenciaA, (int) $clienteA['id'], 'Plano da A', 'pending_approval');
        $planoB   = $this->createPlan($agenciaB, (int) $clienteB['id'], 'Plano da B', 'pending_approval');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        // O vínculo indevido que o furo antigo gravou continua no banco: o
        // admin da A concedeu acesso a si mesmo num cliente da B.
        $this->vincula((int) $clienteB['id'], $userA['id']);

        $this->actingAs(
            $userA['id'],
            permissions: ['approvals.view', 'approvals.comment'],
            clientIds: [(int) $clienteA['id'], (int) $clienteB['id']],
        );

        // Positiva: o plano do cliente dele abre.
        $proprio = $this->get("/aprovacoes/{$planoA}");
        $this->assertSame(200, $proprio->getStatus());
        $this->assertStringContainsString('Plano da A', $proprio->getBody());

        // Negativa: /aprovacoes lê client_ids da sessão direto — a lista não
        // pode trazer o vínculo de outra agência.
        $alheio = $this->get("/aprovacoes/{$planoB}");
        $this->assertSame(404, $alheio->getStatus());
        $this->assertStringNotContainsString('Plano da B', $alheio->getBody());
    }

    public function test_revogar_acesso_corta_a_sessao_ja_aberta(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId, 'Cliente Vinculado');
        $plano    = $this->createPlan($agencyId, (int) $cliente['id'], 'Plano Vinculado');
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: self::PERM_SEM_VIEW_ALL, clientIds: [(int) $cliente['id']]);

        // Positiva: com o vínculo, o plano abre.
        $this->assertSame(200, $this->get("/conteudo/{$plano}")->getStatus());

        // O admin revoga o acesso (a rota é coberta em outro teste); a sessão
        // do usuário continua aberta.
        $this->pdo->exec("DELETE FROM client_user_access WHERE client_id = {$cliente['id']} AND user_id = {$user['id']}");

        // Passada a janela de revalidação do AuthMiddleware, a sessão recarrega
        // os vínculos do banco e o plano deixa de abrir.
        $_SESSION['auth_checked_at'] = time() - 3600;

        $this->assertSame(404, $this->get("/conteudo/{$plano}")->getStatus());
        $this->assertSame([], $_SESSION['client_ids'], 'A sessão precisa refletir a revogação.');
    }

    public function test_papel_padrao_de_trafego_ve_o_conteudo_da_agencia(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId, 'Cliente da Agência');
        $this->createPlan($agencyId, (int) $cliente['id'], 'Plano da Agência');
        $user     = $this->createUser($agencyId);

        // Sem vínculo em client_user_access: é o caso normal da equipe, porque
        // o vínculo é manual, por cliente. O papel precisa alcançar a carteira
        // pelas permissões que o seeder entrega.
        $this->actingAs($user['id'], permissions: self::defaultRolePermissions('traffic_manager'), clientIds: []);

        $lista = $this->get('/conteudo');
        $this->assertSame(200, $lista->getStatus());
        $this->assertStringContainsString('Plano da Agência', $lista->getBody());
    }

    // ── 4. Financeiro e portal do cliente ─────────────────────────────────────

    public function test_fatura_nao_aceita_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: ['clients.view', 'clients.view_all', 'invoices.create']);

        // Positiva: a fatura do cliente da própria agência é criada.
        $this->post('/faturas', $this->dadosFatura((int) $clienteA['id']));
        $this->assertSame(1, $this->invoiceCount((int) $clienteA['id']));

        // Negativa: client_id de outra agência não entra na fatura.
        $this->post('/faturas', $this->dadosFatura((int) $clienteB['id']));
        $this->assertSame(0, $this->invoiceCount((int) $clienteB['id']));
    }

    public function test_contrato_nao_aceita_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: ['clients.view', 'clients.view_all', 'contracts.create']);

        $this->post('/contratos', $this->dadosContrato((int) $clienteA['id']));
        $this->assertSame(1, $this->contractCount((int) $clienteA['id']));

        $this->post('/contratos', $this->dadosContrato((int) $clienteB['id']));
        $this->assertSame(0, $this->contractCount((int) $clienteB['id']));
    }


    public function test_contratos_ativos_respeitam_o_acesso_ao_cliente(): void
    {
        $agencyId = $this->createAgency();
        $meu      = $this->createClient($agencyId, 'Meu Cliente');
        $alheio   = $this->createClient($agencyId, 'Cliente do Colega');
        $user     = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs(
            $user['id'],
            permissions: ['clients.view', 'invoices.create'],
            clientIds: [(int) $meu['id']],
        );

        $this->assertSame(200, $this->get("/clientes/{$meu['id']}/contratos-ativos")->getStatus());
        $this->assertContains($this->get("/clientes/{$alheio['id']}/contratos-ativos")->getStatus(), [403, 404]);
    }

    public function test_pagamento_de_fatura_de_cliente_alheio_e_negado(): void
    {
        $agencyId  = $this->createAgency();
        $meu       = $this->createClient($agencyId, 'Meu Cliente');
        $alheio    = $this->createClient($agencyId, 'Cliente do Colega');
        $faturaMin = $this->createInvoice($agencyId, (int) $meu['id'], 'FAT-001');
        $faturaDele= $this->createInvoice($agencyId, (int) $alheio['id'], 'FAT-002');
        $user      = $this->createUser($agencyId);

        $this->vincula((int) $meu['id'], $user['id']);
        $this->actingAs(
            $user['id'],
            permissions: ['clients.view', 'payments.create'],
            clientIds: [(int) $meu['id']],
        );

        $this->assertSame(200, $this->get('/pagamentos/novo?invoice_id=' . $faturaMin)->getStatus());
        $this->assertSame(403, $this->get('/pagamentos/novo?invoice_id=' . $faturaDele)->getStatus());

        $this->post('/pagamentos', ['invoice_id' => $faturaDele, 'amount' => '100,00']);
        $this->assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM payments WHERE invoice_id = {$faturaDele}")->fetchColumn(),
            'Pagamento em fatura de cliente alheio não pode ser gravado.',
        );
    }

    public function test_lista_e_detalhe_de_fatura_respeitam_o_alcance_do_cliente(): void
    {
        $agencyId = $this->createAgency();
        $meu      = $this->createClient($agencyId, 'Meu Cliente');
        $alheio   = $this->createClient($agencyId, 'Cliente do Colega');
        $minha    = $this->createInvoice($agencyId, (int) $meu['id'], 'FAT-100');
        $dela     = $this->createInvoice($agencyId, (int) $alheio['id'], 'FAT-200');
        $user     = $this->createUser($agencyId);

        $this->actingAs(
            $user['id'],
            permissions: ['clients.view', 'invoices.view'],
            clientIds: [(int) $meu['id']],
        );

        // Positiva: a fatura do cliente dele aparece na lista e abre.
        $lista = $this->get('/faturas');
        $this->assertSame(200, $lista->getStatus());
        $this->assertStringContainsString('FAT-100', $lista->getBody());
        $this->assertSame(200, $this->get("/faturas/{$minha}")->getStatus());

        // Negativa: a do colega não entra na lista, nem pelo ?client_id=.
        $this->assertStringNotContainsString('FAT-200', $lista->getBody());
        $this->assertStringNotContainsString('Cliente do Colega', $lista->getBody());
        $this->assertStringNotContainsString(
            'FAT-200',
            $this->get('/faturas?client_id=' . $alheio['id'])->getBody(),
        );

        // E o detalhe pela URL também não.
        $negada = $this->get("/faturas/{$dela}");
        $this->assertSame(403, $negada->getStatus());
        $this->assertStringNotContainsString('FAT-200', $negada->getBody());
    }

    public function test_lista_de_contratos_e_pagamentos_respeita_o_alcance(): void
    {
        $agencyId    = $this->createAgency();
        $meu         = $this->createClient($agencyId, 'Meu Cliente');
        $alheio      = $this->createClient($agencyId, 'Cliente do Colega');
        $contratoMeu = $this->createContract($agencyId, (int) $meu['id'], 'Contrato Meu');
        $contratoDela= $this->createContract($agencyId, (int) $alheio['id'], 'Contrato do Colega');
        $faturaDela  = $this->createInvoice($agencyId, (int) $alheio['id'], 'FAT-300');
        $faturaMinha = $this->createInvoice($agencyId, (int) $meu['id'], 'FAT-400');
        $pagamentoMeu  = $this->createPayment($agencyId, $faturaMinha, '100.00');
        $pagamentoDela = $this->createPayment($agencyId, $faturaDela, '900.00');
        $user        = $this->createUser($agencyId);

        $this->actingAs(
            $user['id'],
            permissions: ['clients.view', 'contracts.view', 'contracts.delete', 'payments.view', 'payments.delete'],
            clientIds: [(int) $meu['id']],
        );

        // Positiva: contrato e pagamento do cliente dele aparecem e abrem.
        $contratos = $this->get('/contratos');
        $this->assertSame(200, $contratos->getStatus());
        $this->assertStringContainsString('Contrato Meu', $contratos->getBody());
        $this->assertSame(200, $this->get("/contratos/{$contratoMeu}")->getStatus());

        $pagamentos = $this->get('/pagamentos');
        $this->assertSame(200, $pagamentos->getStatus());
        $this->assertStringContainsString('FAT-400', $pagamentos->getBody());

        // Negativa: o do colega não aparece na lista nem abre pela URL.
        $this->assertStringNotContainsString('Contrato do Colega', $contratos->getBody());
        $this->assertSame(403, $this->get("/contratos/{$contratoDela}")->getStatus());
        $this->assertStringNotContainsString('FAT-300', $pagamentos->getBody());
        $this->assertStringNotContainsString('Cliente do Colega', $pagamentos->getBody());

        // E excluir o do colega pela URL também não — o dele, sim.
        $this->post("/pagamentos/{$pagamentoDela}", ['_method' => 'DELETE']);
        $this->post("/contratos/{$contratoDela}", ['_method' => 'DELETE']);
        $this->assertSame(1, $this->rowCount('payments', $pagamentoDela), 'Pagamento do colega não pode sair.');
        $this->assertSame(1, $this->rowCount('contracts', $contratoDela), 'Contrato do colega não pode sair.');

        $this->post("/pagamentos/{$pagamentoMeu}", ['_method' => 'DELETE']);
        $this->post("/contratos/{$contratoMeu}", ['_method' => 'DELETE']);
        $this->assertSame(0, $this->rowCount('payments', $pagamentoMeu));
        $this->assertSame(0, $this->rowCount('contracts', $contratoMeu));
    }

    public function test_edicao_de_fatura_nao_troca_o_cliente_sem_querer(): void
    {
        $agencyId  = $this->createAgency();
        $ativo     = $this->createClient($agencyId, 'Cliente Ativo');
        $arquivado = $this->createClient($agencyId, 'Cliente Arquivado');
        $fatura    = $this->createInvoice($agencyId, (int) $arquivado['id'], 'FAT-500');
        $user      = $this->createUser($agencyId);

        // Cliente arquivado não entra em seletor — é o caso em que o seletor
        // da tela não tem opção casando com a fatura aberta.
        $this->pdo->exec("UPDATE clients SET status = 'cancelled' WHERE id = {$arquivado['id']}");

        $this->actingAs(
            $user['id'],
            permissions: ['clients.view', 'clients.view_all', 'invoices.view', 'invoices.edit'],
        );

        // A opção do cliente DA FATURA precisa existir e vir marcada: sem ela
        // o navegador marca a primeira da lista e salvar reatribui a fatura.
        $tela = $this->get("/faturas/{$fatura}/editar");
        $this->assertSame(200, $tela->getStatus());
        $this->assertMatchesRegularExpression(
            '/<option value="' . $arquivado['id'] . '"\s+selected/',
            $tela->getBody(),
        );
        $this->assertStringContainsString('Cliente Ativo', $tela->getBody());

        // Positiva: salvar com o cliente da própria fatura funciona.
        $dados          = $this->dadosFatura((int) $arquivado['id']);
        $dados['_method'] = 'PUT';
        $dados['title']   = 'Fatura Corrigida';
        $this->post("/faturas/{$fatura}", $dados);
        $this->assertSame((int) $arquivado['id'], $this->invoiceClientId($fatura));
        $this->assertSame('Fatura Corrigida', $this->invoiceTitle($fatura));

        // Negativa: POST sem cliente (opção vazia) não reatribui a fatura.
        $vazio              = $this->dadosFatura(0);
        $vazio['client_id'] = '';
        $vazio['_method']   = 'PUT';
        $this->post("/faturas/{$fatura}", $vazio);
        $this->assertSame((int) $arquivado['id'], $this->invoiceClientId($fatura));
    }

    public function test_conteudo_vazio_explica_que_o_perfil_nao_alcanca_cliente(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId, 'Cliente da Agência');
        $semVinculo = $this->createUser($agencyId, 'sem-vinculo@test.com');
        $comVinculo = $this->createUser($agencyId, 'com-vinculo@test.com');

        // Perfil próprio do tenant com content.view e sem clients.view_all: o
        // módulo nasce vazio e nada na tela explicava o porquê (SEC-11).
        $this->actingAs($semVinculo['id'], permissions: ['content.view'], clientIds: []);
        $vazio = $this->get('/conteudo');
        $this->assertSame(200, $vazio->getStatus());
        $this->assertStringContainsString('não alcança nenhum cliente', $vazio->getBody());
        $this->assertStringContainsString(
            'não alcança nenhum cliente',
            $this->get('/conteudo/calendario')->getBody(),
        );

        // Positiva: quem alcança cliente não vê o aviso (vê o estado vazio normal).
        $this->actingAs($comVinculo['id'], permissions: ['content.view'], clientIds: [(int) $cliente['id']]);
        $this->assertStringNotContainsString('não alcança nenhum cliente', $this->get('/conteudo')->getBody());
    }

    public function test_portal_do_cliente_so_muda_com_clients_edit(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Portal');
        $user     = $this->createUser($agencyId);

        // Negativa: clients.view sozinho não liga/desliga portal nem troca o link.
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);
        $this->assertSame(403, $this->post("/clientes/{$client['id']}/portal/toggle")->getStatus());
        $this->assertSame(403, $this->post("/clientes/{$client['id']}/portal/regenerar")->getStatus());
        $this->assertSame($client['portal_token'], $this->portalToken((int) $client['id']));

        // Positiva: com clients.edit, a rota funciona.
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.edit', 'clients.view_all']);
        $this->assertSame(302, $this->post("/clientes/{$client['id']}/portal/regenerar")->getStatus());
        $this->assertNotSame($client['portal_token'], $this->portalToken((int) $client['id']));
    }

    // ── Fábricas locais ───────────────────────────────────────────────────────

    /**
     * Vínculo real em client_user_access — é dele que o login monta a lista de
     * clientes da sessão, e é ele que as listagens consultam.
     */
    private function vincula(int $clientId, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO client_user_access (client_id, user_id, access_level, created_at) VALUES (:c, :u, :l, NOW())'
        )->execute([':c' => $clientId, ':u' => $userId, ':l' => 'standard']);
    }

    private function createPlan(int $agencyId, int $clientId, string $title, string $status = 'draft'): int
    {
        $monday = \App\Services\ContentPlanService::mondayOf(date('Y-m-d'));
        $stmt   = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, created_by, created_at)
             VALUES (:a, :c, :t, :ws, :we, :s, NULL, NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a'  => $agencyId,
            ':c'  => $clientId,
            ':t'  => $title,
            ':s'  => $status,
            ':ws' => $monday,
            ':we' => \App\Services\ContentPlanService::sundayOf($monday),
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Permissões do papel padrão, lidas da fonte da verdade do seeder — se o
     * papel perder o alcance de cliente, este teste cai junto.
     *
     * @return list<string>
     */
    private static function defaultRolePermissions(string $slug): array
    {
        require_once dirname(__DIR__, 2) . '/database/seeders/InitialDataSeeder.php';

        foreach (\InitialDataSeeder::DEFAULT_ROLES as $role) {
            if ($role['slug'] === $slug) {
                return is_array($role['permissions']) ? $role['permissions'] : [];
            }
        }

        self::fail("Papel padrão '{$slug}' não existe no seeder.");
    }

    /** @return array<string,string> */
    private function dadosFatura(int $clientId): array
    {
        return [
            'client_id'     => (string) $clientId,
            'contract_id'   => '',
            'title'         => 'Serviços do mês',
            'status'        => 'draft',
            'due_date'      => date('Y-m-d', strtotime('+10 days')),
            'currency_code' => 'BRL',
            'discount'      => '0',
            'tax'           => '0',
            'notes'         => '',
        ];
    }

    /** @return array<string,string> */
    private function dadosContrato(int $clientId): array
    {
        return [
            'client_id'     => (string) $clientId,
            'title'         => 'Contrato mensal',
            'description'   => '',
            'value'         => '1000,00',
            'currency_code' => 'BRL',
            'status'        => 'draft',
            'start_date'    => date('Y-m-d'),
            'end_date'      => '',
            'signed_at'     => '',
            'recurrence'    => '',
            'notes'         => '',
        ];
    }

    private function invoiceCount(int $clientId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM invoices WHERE client_id = :c');
        $stmt->execute([':c' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    private function contractCount(int $clientId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contracts WHERE client_id = :c');
        $stmt->execute([':c' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    private function createItem(int $planId, int $clientId, string $title): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, publish_date, title, status, sort_order, created_at)
             VALUES (:p, :c, :d, :t, 'draft', 0, NOW()) RETURNING id"
        );
        // Hoje, para o post cair no mês que o calendário abre por padrão.
        $stmt->execute([
            ':p' => $planId,
            ':c' => $clientId,
            ':d' => date('Y-m-d'),
            ':t' => $title,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function createInvoice(int $agencyId, int $clientId, string $number): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, due_date,
                                   subtotal, total, amount_paid, currency_code, created_at)
             VALUES (:a, :c, :n, 'Serviços', 'sent', CURRENT_DATE + 10, 1000.00, 1000.00, 0, 'BRL', NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':n' => $number]);

        return (int) $stmt->fetchColumn();
    }

    private function createContract(int $agencyId, int $clientId, string $title): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contracts (agency_id, client_id, title, value, currency_code, status,
                                    start_date, created_at, updated_at)
             VALUES (:a, :c, :t, 1000.00, 'BRL', 'active', CURRENT_DATE, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':t' => $title]);

        return (int) $stmt->fetchColumn();
    }

    private function createPayment(int $agencyId, int $invoiceId, string $amount): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments (agency_id, invoice_id, amount, payment_method, payment_date, created_at, updated_at)
             VALUES (:a, :i, :v, 'pix', CURRENT_DATE, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':i' => $invoiceId, ':v' => $amount]);

        return (int) $stmt->fetchColumn();
    }

    /** Linha ainda existe? (tabela de allowlist fechada, nunca input externo) */
    private function rowCount(string $table, int $id): int
    {
        if (!in_array($table, ['payments', 'contracts', 'invoices'], true)) {
            self::fail("Tabela '{$table}' fora da allowlist do teste.");
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    private function invoiceClientId(int $invoiceId): int
    {
        $stmt = $this->pdo->prepare('SELECT client_id FROM invoices WHERE id = :id');
        $stmt->execute([':id' => $invoiceId]);

        return (int) $stmt->fetchColumn();
    }

    private function invoiceTitle(int $invoiceId): string
    {
        $stmt = $this->pdo->prepare('SELECT title FROM invoices WHERE id = :id');
        $stmt->execute([':id' => $invoiceId]);

        return (string) $stmt->fetchColumn();
    }

    private function itemTitle(int $itemId): string
    {
        $stmt = $this->pdo->prepare('SELECT title FROM content_plan_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);

        return (string) $stmt->fetchColumn();
    }

    private function accessCount(int $clientId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM client_user_access WHERE client_id = :c AND user_id = :u'
        );
        $stmt->execute([':c' => $clientId, ':u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    private function portalToken(int $clientId): string
    {
        $stmt = $this->pdo->prepare('SELECT portal_token FROM clients WHERE id = :id');
        $stmt->execute([':id' => $clientId]);

        return (string) $stmt->fetchColumn();
    }
}
