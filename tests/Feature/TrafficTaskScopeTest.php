<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\ClickUpIntegrationRepository;

/**
 * SEC-13 — escopo de agência em tráfego e tarefas.
 *
 * Três tabelas do tráfego (`campaigns`, `ad_sets`, `ads`) não têm `agency_id`:
 * a agência vem pela conta de anúncios. Enquanto o id chegava cru da URL ou do
 * POST, o filtro por agência simplesmente não existia nesse caminho. Nas
 * tarefas era o inverso: a linha nascia com o `agency_id` da sessão, mas com
 * `client_id`/`assigned_to` de outra agência — e os JOINs do kanban, do detalhe
 * e do "Meu dia" iam buscar o nome lá.
 *
 * Toda negativa aqui tem a positiva ao lado: 404 (ou "nada gravado") só prova
 * algo quando a mesma rota, com o mesmo usuário, aceita o que é dele.
 */
class TrafficTaskScopeTest extends FeatureTestCase
{
    /** O que o papel Gestor de Tráfego tem no seeder — incluídas as duas de IA. */
    private const PERM_TRAFEGO = [
        'dashboard.view', 'ads_metrics.view',
        'ads_actions.view', 'ads_actions.request', 'ai_insights.view',
        'ai.generate_report', 'ai.recommend_ads_action',
    ];

    /** O perfil "Analista" que o admin da agência cria achando que é leitura. */
    private const PERM_LEITURA_IA = [
        'dashboard.view', 'ads_metrics.view', 'ads_actions.view', 'ai_insights.view',
    ];

    private const PERM_TAREFAS = [
        'dashboard.view', 'clients.view', 'clients.view_all',
        'tasks.view', 'tasks.create', 'tasks.edit',
    ];

    // ── Fábricas ──────────────────────────────────────────────────────────────

    private function criarConta(int $agencyId, ?int $clientId = null, string $nome = 'Conta de Teste'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ad_accounts (agency_id, client_id, platform, platform_account_id, name, access_token, created_at)
             VALUES (:a, :c, 'meta', :pa, :n, 'token', NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a'  => $agencyId,
            ':c'  => $clientId,
            ':pa' => 'act_' . random_int(100000, 999999),
            ':n'  => $nome,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function criarCampanha(int $accountId, string $nome): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO campaigns (ad_account_id, platform, platform_id, name, status, created_at)
             VALUES (:acc, 'meta', :pid, :n, 'ACTIVE', NOW()) RETURNING id"
        );
        $stmt->execute([':acc' => $accountId, ':pid' => 'c_' . random_int(100000, 999999), ':n' => $nome]);

        return (int) $stmt->fetchColumn();
    }

    private function criarTarefa(
        int $agencyId,
        int $createdBy,
        ?int $clientId,
        ?int $assignedTo,
        string $titulo,
        ?string $dueDate = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tasks (agency_id, client_id, assigned_to, created_by, title, status, priority, due_date, created_at, updated_at)
             VALUES (:a, :c, :u, :cb, :t, 'todo', 'high', :d, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a'  => $agencyId,
            ':c'  => $clientId,
            ':u'  => $assignedTo,
            ':cb' => $createdBy,
            ':t'  => $titulo,
            ':d'  => $dueDate,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** `createUser` nomeia todo mundo igual: aqui o nome é a prova do vazamento. */
    private function criarUsuarioNomeado(int $agencyId, string $email, string $nome): array
    {
        $user = $this->createUser($agencyId, $email);
        $this->pdo->prepare('UPDATE users SET name = :n WHERE id = :id')
            ->execute([':n' => $nome, ':id' => $user['id']]);

        return $user;
    }

    /** Solicitação gravada direto no banco, como as que existem desde antes da validação. */
    private function criarAcaoLegada(int $agencyId, int $accountId, int $campaignId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ads_actions (agency_id, ad_account_id, campaign_id, action_type, description, status, created_at, updated_at)
             VALUES (:a, :acc, :c, 'pause', :d, 'pending', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':acc' => $accountId, ':c' => $campaignId, ':d' => 'Ação legada']);

        return (int) $stmt->fetchColumn();
    }

    private function contarAcoes(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ads_actions')->fetchColumn();
    }

    private function contarTarefas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    }

    private function erroNoFlash(): string
    {
        return (string) ($_SESSION['flash']['error'] ?? '');
    }

    /** O flash sobrevive à requisição: sem limpar, a negativa passa com o erro da anterior. */
    private function limparFlash(): void
    {
        unset($_SESSION['flash']);
    }

    // ── 1. Campanhas por conta (id na URL) ────────────────────────────────────

    public function test_campanhas_de_conta_de_outra_agencia_dao_404(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $contaA   = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB   = $this->criarConta($agenciaB, null, 'Conta da B');
        $this->criarCampanha($contaA, 'Campanha da A');
        $this->criarCampanha($contaB, 'Campanha da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: a rota funciona para a conta da própria agência.
        $propria = $this->get("/trafego/contas/{$contaA}/campanhas");
        $this->assertSame(200, $propria->getStatus());
        $this->assertStringContainsString('Campanha da A', $propria->getBody());

        // Negativa: o mesmo usuário não atravessa a agência pela URL.
        $alheia = $this->get("/trafego/contas/{$contaB}/campanhas");
        $this->assertSame(404, $alheia->getStatus());
        $this->assertStringNotContainsString('Campanha da B', $alheia->getBody());
    }

    // ── 2. Ação manual: conta e alvo vindos do POST ───────────────────────────

    public function test_acao_manual_so_aceita_conta_da_propria_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $contaA   = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB   = $this->criarConta($agenciaB, null, 'Conta da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: conta da agência cria a solicitação.
        $this->post('/trafego/acoes', [
            'ad_account_id' => $contaA,
            'action_type'   => 'pause',
            'description'   => 'Pausar a conta inteira',
        ]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: conta de outra agência não grava nada.
        $this->limparFlash();
        $this->post('/trafego/acoes', [
            'ad_account_id' => $contaB,
            'action_type'   => 'pause',
            'description'   => 'Pausar a conta da B',
        ]);
        $this->assertSame(1, $this->contarAcoes());
        $this->assertStringContainsString('não encontrada', $this->erroNoFlash());
    }

    public function test_acao_manual_recusa_campanha_de_outra_agencia(): void
    {
        $agenciaA  = $this->createAgency('Agência A');
        $agenciaB  = $this->createAgency('Agência B');
        $contaA    = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB    = $this->criarConta($agenciaB, null, 'Conta da B');
        $campanhaA = $this->criarCampanha($contaA, 'Campanha da A');
        $campanhaB = $this->criarCampanha($contaB, 'Campanha da B');
        $userA     = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: campanha da própria conta.
        $this->post('/trafego/acoes', [
            'ad_account_id' => $contaA,
            'campaign_id'   => $campanhaA,
            'action_type'   => 'pause',
            'description'   => 'Pausar campanha própria',
        ]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: conta certa, campanha de outra agência.
        $this->limparFlash();
        $this->post('/trafego/acoes', [
            'ad_account_id' => $contaA,
            'campaign_id'   => $campanhaB,
            'action_type'   => 'pause',
            'description'   => 'Pausar campanha alheia',
        ]);
        $this->assertSame(1, $this->contarAcoes());
        $this->assertStringContainsString('não é desta conta', $this->erroNoFlash());
    }

    public function test_lista_de_acoes_nao_exibe_alvo_de_outra_agencia(): void
    {
        $agenciaA  = $this->createAgency('Agência A');
        $agenciaB  = $this->createAgency('Agência B');
        $contaA    = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB    = $this->criarConta($agenciaB, null, 'Conta da B');
        $campanhaA = $this->criarCampanha($contaA, 'Campanha da A');
        $campanhaB = $this->criarCampanha($contaB, 'Campanha da B');
        $userA     = $this->createUser($agenciaA, 'a@test.com');

        // Uma solicitação legítima e uma legada apontando para a campanha da B.
        $this->criarAcaoLegada($agenciaA, $contaA, $campanhaA);
        $legada = $this->criarAcaoLegada($agenciaA, $contaA, $campanhaB);

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        $lista = $this->get('/trafego/acoes');
        $this->assertSame(200, $lista->getStatus());
        $this->assertStringContainsString('Campanha da A', $lista->getBody());
        $this->assertStringNotContainsString('Campanha da B', $lista->getBody());

        $detalhe = $this->get("/trafego/acoes/{$legada}");
        $this->assertSame(200, $detalhe->getStatus());
        $this->assertStringNotContainsString('Campanha da B', $detalhe->getBody());
    }

    /**
     * Variante do anterior em que a linha legada aponta para a **conta** de
     * outra agência: aí o alvo está de fato dentro dela, então antes da correção
     * tanto o nome da conta quanto o da campanha alheia voltavam a aparecer.
     */
    public function test_lista_de_acoes_nao_exibe_conta_de_outra_agencia(): void
    {
        $agenciaA  = $this->createAgency('Agência A');
        $agenciaB  = $this->createAgency('Agência B');
        $contaA    = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB    = $this->criarConta($agenciaB, null, 'Conta da B');
        $campanhaA = $this->criarCampanha($contaA, 'Campanha da A');
        $campanhaB = $this->criarCampanha($contaB, 'Campanha da B');
        $userA     = $this->createUser($agenciaA, 'a@test.com');

        $this->criarAcaoLegada($agenciaA, $contaA, $campanhaA);
        $legada = $this->criarAcaoLegada($agenciaA, $contaB, $campanhaB);

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        $lista = $this->get('/trafego/acoes');
        $this->assertSame(200, $lista->getStatus());
        // Positiva: a solicitação da própria conta segue com conta e campanha.
        $this->assertStringContainsString('Conta da A', $lista->getBody());
        $this->assertStringContainsString('Campanha da A', $lista->getBody());
        $this->assertStringNotContainsString('Conta da B', $lista->getBody());
        $this->assertStringNotContainsString('Campanha da B', $lista->getBody());
        // A linha legada não desaparece da lista — dá para rejeitá-la.
        $this->assertSame(2, substr_count($lista->getBody(), 'Ação legada'));

        $detalhe = $this->get("/trafego/acoes/{$legada}");
        $this->assertSame(200, $detalhe->getStatus());
        $this->assertStringContainsString('Ação legada', $detalhe->getBody());
        $this->assertStringNotContainsString('Conta da B', $detalhe->getBody());
        $this->assertStringNotContainsString('Campanha da B', $detalhe->getBody());
    }

    // ── 3. Sugestões da IA (ids no JSON do POST) ──────────────────────────────

    public function test_sugestao_da_ia_com_alvo_de_outra_agencia_nao_vira_acao(): void
    {
        $agenciaA  = $this->createAgency('Agência A');
        $agenciaB  = $this->createAgency('Agência B');
        $contaA    = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB    = $this->criarConta($agenciaB, null, 'Conta da B');
        $campanhaA = $this->criarCampanha($contaA, 'Campanha da A');
        $campanhaB = $this->criarCampanha($contaB, 'Campanha da B');
        $userA     = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        $sugestao = fn (int $campaignId): string => (string) json_encode([
            'action_type'  => 'decrease_budget',
            'description'  => 'Reduzir orçamento',
            'campaign_id'  => $campaignId,
        ]);

        // Positiva: sugestão com campanha da própria conta.
        $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $contaA,
            'suggestions' => [$sugestao($campanhaA)],
        ]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: campanha de outra agência enfiada no JSON.
        $this->limparFlash();
        $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $contaA,
            'suggestions' => [$sugestao($campanhaB)],
        ]);
        $this->assertSame(1, $this->contarAcoes());
        $this->assertStringContainsString('recusada', $this->erroNoFlash());

        // Negativa: conta de outra agência.
        $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $contaB,
            'suggestions' => [$sugestao($campanhaB)],
        ]);
        $this->assertSame(1, $this->contarAcoes());
    }

    public function test_recomendacoes_nao_analisam_conta_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $contaA   = $this->criarConta($agenciaA, null, 'Conta da A');
        $contaB   = $this->criarConta($agenciaB, null, 'Conta da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: com a conta da agência a tela segue o fluxo normal (aqui para
        // na checagem de IA configurada, que no teste é falsa — nada de rede).
        $propria = $this->get("/ia/recomendacoes?account_id={$contaA}");
        $this->assertSame(200, $propria->getStatus());
        $this->assertStringContainsString('IA ainda não foi configurada', $propria->getBody());

        // Negativa: conta de outra agência nem chega a ser analisada.
        $this->limparFlash();
        $alheia = $this->get("/ia/recomendacoes?account_id={$contaB}");
        $this->assertSame(200, $alheia->getStatus());
        $this->assertStringContainsString('Conta de anúncios não encontrada', $alheia->getBody());
        $this->assertStringNotContainsString('IA ainda não foi configurada', $alheia->getBody());
    }

    // ── 3b. Conta de anúncios: client_id do POST ──────────────────────────────

    /**
     * O caminho do OAuth não revalida nada na Meta (o token já está na sessão),
     * então o POST adulterado gravava na hora — e a conta passava a exibir o
     * nome do cliente de outra agência na lista, no painel e no insight.
     */
    public function test_conta_de_anuncios_nao_aceita_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        // Pelo formulário de token manual: a recusa vem antes de qualquer
        // chamada à Meta — por isso o teste não fala com a rede. Vem primeiro
        // porque o plano da agência limita quantas contas podem ser conectadas.
        $this->post('/trafego/contas', [
            'access_token'        => 'token-de-teste',
            'platform_account_id' => '333333',
            'client_id'           => $clienteB['id'],
        ]);
        $this->assertSame(0, $this->contarContas());
        $this->assertStringContainsString('não é da sua agência', $this->erroNoFlash());

        // Positiva: cliente da própria agência conecta e fica vinculado.
        $this->limparFlash();
        $_SESSION['meta_oauth_token'] = 'token-de-teste';
        $this->post('/trafego/contas/oauth/salvar', [
            'account_id'   => 'act_111111',
            'account_name' => 'Conta própria',
            'client_id'    => $clienteA['id'],
        ]);
        $this->assertSame(1, $this->contarContas());
        $this->assertSame((int) $clienteA['id'], $this->clienteDaConta('act_111111'));

        // Negativa: cliente de outra agência não grava conta nenhuma.
        $this->limparFlash();
        $_SESSION['meta_oauth_token'] = 'token-de-teste';
        $this->post('/trafego/contas/oauth/salvar', [
            'account_id'   => 'act_222222',
            'account_name' => 'Conta sequestrada',
            'client_id'    => $clienteB['id'],
        ]);
        $this->assertSame(1, $this->contarContas());
        $this->assertStringContainsString('não é da sua agência', $this->erroNoFlash());
    }

    private function contarContas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ad_accounts')->fetchColumn();
    }

    private function clienteDaConta(string $platformAccountId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT client_id FROM ad_accounts WHERE platform_account_id = :p');
        $stmt->execute([':p' => ltrim($platformAccountId, 'act_')]);
        $value = $stmt->fetchColumn();

        return $value === null || $value === false ? null : (int) $value;
    }

    public function test_telas_de_trafego_nao_exibem_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        // Contas da agência A — uma legada, com o cliente de outra agência.
        $propria = $this->criarConta($agenciaA, (int) $clienteA['id'], 'Conta própria');
        $legada  = $this->criarConta($agenciaA, (int) $clienteB['id'], 'Conta legada');
        $this->criarCampanha($propria, 'Campanha própria');
        $this->criarCampanha($legada, 'Campanha legada');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        $contas = $this->get('/trafego/contas');
        $this->assertSame(200, $contas->getStatus());
        $this->assertStringContainsString('Conta legada', $contas->getBody());
        $this->assertStringContainsString('Cliente da A', $contas->getBody());
        $this->assertStringNotContainsString('Cliente da B', $contas->getBody());

        $painel = $this->get('/trafego');
        $this->assertSame(200, $painel->getStatus());
        $this->assertStringContainsString('Campanha legada', $painel->getBody());
        $this->assertStringContainsString('Cliente da A', $painel->getBody());
        $this->assertStringNotContainsString('Cliente da B', $painel->getBody());
    }

    public function test_insight_nao_exibe_conta_nem_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $contaA   = $this->criarConta($agenciaA, (int) $clienteA['id'], 'Conta da A');
        $contaB   = $this->criarConta($agenciaB, (int) $clienteB['id'], 'Conta da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $this->criarInsight($agenciaA, $contaA, (int) $clienteA['id'], 'Insight próprio');
        $legado = $this->criarInsight($agenciaA, $contaB, (int) $clienteB['id'], 'Insight legado');

        $this->actingAs($userA['id'], permissions: self::PERM_TRAFEGO);

        $lista = $this->get('/ia');
        $this->assertSame(200, $lista->getStatus());
        $this->assertStringContainsString('Conta da A', $lista->getBody());
        $this->assertStringNotContainsString('Conta da B', $lista->getBody());

        $detalhe = $this->get("/ia/{$legado}");
        $this->assertSame(200, $detalhe->getStatus());
        $this->assertStringContainsString('Insight legado', $detalhe->getBody());
        $this->assertStringNotContainsString('Conta da B', $detalhe->getBody());
        $this->assertStringNotContainsString('Cliente da B', $detalhe->getBody());
    }

    private function criarInsight(int $agencyId, int $accountId, ?int $clientId, string $conteudo): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ai_insights (agency_id, client_id, ad_account_id, type, content, created_at)
             VALUES (:a, :c, :acc, 'performance_summary', :ct, NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':acc' => $accountId, ':ct' => $conteudo]);

        return (int) $stmt->fetchColumn();
    }

    // ── 4. Tarefas: client_id e assigned_to do POST ───────────────────────────

    public function test_tarefa_so_aceita_cliente_e_responsavel_da_propria_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');
        $userB    = $this->createUser($agenciaB, 'b@test.com');

        $this->actingAs($userA['id'], permissions: self::PERM_TAREFAS);

        // Positiva: cliente e responsável da própria agência.
        $this->post('/tarefas', [
            'title'       => 'Tarefa legítima',
            'client_id'   => $clienteA['id'],
            'assigned_to' => $userA['id'],
        ]);
        $this->assertSame(1, $this->contarTarefas());

        // Negativa: cliente de outra agência.
        $this->limparFlash();
        $this->post('/tarefas', [
            'title'     => 'Tarefa com cliente alheio',
            'client_id' => $clienteB['id'],
        ]);
        $this->assertSame(1, $this->contarTarefas());
        $this->assertStringContainsString('cliente não é da sua agência', $this->erroNoFlash());

        // Negativa: responsável de outra agência.
        $this->limparFlash();
        $this->post('/tarefas', [
            'title'       => 'Tarefa com responsável alheio',
            'assigned_to' => $userB['id'],
        ]);
        $this->assertSame(1, $this->contarTarefas());
        $this->assertStringContainsString('responsável não é da sua agência', $this->erroNoFlash());
    }

    public function test_edicao_de_tarefa_nao_reatribui_para_cliente_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');
        $tarefa   = $this->criarTarefa($agenciaA, $userA['id'], (int) $clienteA['id'], null, 'Tarefa da A');

        $this->actingAs($userA['id'], permissions: self::PERM_TAREFAS);

        // Positiva: o update normal passa e grava.
        $this->post("/tarefas/{$tarefa}", [
            '_method'   => 'PUT',
            'title'     => 'Tarefa renomeada',
            'client_id' => $clienteA['id'],
            'status'    => 'in_progress',
            'priority'  => 'high',
        ]);
        $this->assertSame('Tarefa renomeada', $this->tituloDaTarefa($tarefa));

        // Negativa: trocar o cliente para um de outra agência não altera nada.
        $this->post("/tarefas/{$tarefa}", [
            '_method'   => 'PUT',
            'title'     => 'Tarefa sequestrada',
            'client_id' => $clienteB['id'],
            'status'    => 'in_progress',
            'priority'  => 'high',
        ]);
        $this->assertSame('Tarefa renomeada', $this->tituloDaTarefa($tarefa));
        $this->assertSame((int) $clienteA['id'], $this->clienteDaTarefa($tarefa));
    }

    /**
     * Cliente arquivado não entra em seletor, mas o da tarefa aberta precisa
     * estar lá: sem a opção dele nenhuma fica `selected`, o navegador marca a
     * primeira — "— Sem cliente —" — e salvar a prioridade desfazia o vínculo,
     * calado e sem volta pela interface (o arquivado nunca volta à lista).
     */
    public function test_edicao_de_tarefa_mantem_cliente_arquivado_no_seletor(): void
    {
        $agenciaA   = $this->createAgency('Agência A');
        $ativo      = $this->createClient($agenciaA, 'Cliente Ativo');
        $arquivado  = $this->createClient($agenciaA, 'Cliente Arquivado');
        $this->pdo->prepare("UPDATE clients SET status = 'cancelled' WHERE id = :id")
            ->execute([':id' => $arquivado['id']]);

        $userA      = $this->createUser($agenciaA, 'a@test.com');
        $comAtivo   = $this->criarTarefa($agenciaA, $userA['id'], (int) $ativo['id'], null, 'Tarefa do ativo');
        $comArquivado = $this->criarTarefa($agenciaA, $userA['id'], (int) $arquivado['id'], null, 'Tarefa do arquivado');

        $this->actingAs($userA['id'], permissions: self::PERM_TAREFAS);

        // Positiva: cliente ativo vem marcado no seletor.
        $telaAtivo = $this->get("/tarefas/{$comAtivo}/editar");
        $this->assertSame(200, $telaAtivo->getStatus());
        $this->assertStringContainsString('value="' . $ativo['id'] . '" selected', $telaAtivo->getBody());

        // O arquivado não está na lista do seletor, mas o da tarefa aparece e
        // vem marcado — é o que impede o POST de mandar "sem cliente".
        $telaArquivado = $this->get("/tarefas/{$comArquivado}/editar");
        $this->assertSame(200, $telaArquivado->getStatus());
        $this->assertStringContainsString('Cliente Arquivado', $telaArquivado->getBody());
        $this->assertStringContainsString('value="' . $arquivado['id'] . '" selected', $telaArquivado->getBody());

        // E salvar com o que a tela entrega mantém o vínculo.
        $this->post("/tarefas/{$comArquivado}", [
            '_method'   => 'PUT',
            'title'     => 'Tarefa do arquivado',
            'client_id' => $arquivado['id'],
            'status'    => 'todo',
            'priority'  => 'urgent',
        ]);
        $this->assertSame((int) $arquivado['id'], $this->clienteDaTarefa($comArquivado));
    }

    private function tituloDaTarefa(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT title FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (string) $stmt->fetchColumn();
    }

    private function clienteDaTarefa(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT client_id FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $value = $stmt->fetchColumn();

        return $value === null || $value === false ? null : (int) $value;
    }

    // ── 5. Telas de tarefa: nome vindo do JOIN ────────────────────────────────

    public function test_kanban_e_detalhe_nao_exibem_cliente_nem_responsavel_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->criarUsuarioNomeado($agenciaA, 'ana@test.com', 'Ana da Agência A');
        $userB    = $this->criarUsuarioNomeado($agenciaB, 'bruno@test.com', 'Bruno da Agência B');

        // Linha legada: gravada antes da validação, com alvo de outra agência.
        $legada   = $this->criarTarefa($agenciaA, $userA['id'], (int) $clienteB['id'], $userB['id'], 'Tarefa legada');
        $propria  = $this->criarTarefa($agenciaA, $userA['id'], (int) $clienteA['id'], $userA['id'], 'Tarefa própria');

        $this->actingAs($userA['id'], permissions: self::PERM_TAREFAS);

        $kanban = $this->get('/tarefas');
        $this->assertSame(200, $kanban->getStatus());
        $this->assertStringContainsString('Cliente da A', $kanban->getBody());
        $this->assertStringContainsString('Ana da Agência A', $kanban->getBody());
        $this->assertStringNotContainsString('Cliente da B', $kanban->getBody());
        $this->assertStringNotContainsString('Bruno da Agência B', $kanban->getBody());

        $detalheProprio = $this->get("/tarefas/{$propria}");
        $this->assertSame(200, $detalheProprio->getStatus());
        $this->assertStringContainsString('Cliente da A', $detalheProprio->getBody());
        $this->assertStringContainsString('Ana da Agência A', $detalheProprio->getBody());

        $detalheLegado = $this->get("/tarefas/{$legada}");
        $this->assertSame(200, $detalheLegado->getStatus());
        $this->assertStringNotContainsString('Cliente da B', $detalheLegado->getBody());
        $this->assertStringNotContainsString('Bruno da Agência B', $detalheLegado->getBody());
    }

    public function test_meu_dia_nao_exibe_cliente_de_outra_agencia_em_tarefa_atrasada(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');

        $atrasada = date('Y-m-d', strtotime('-3 days'));
        $this->criarTarefa($agenciaA, $userA['id'], (int) $clienteB['id'], null, 'Atrasada legada', $atrasada);
        $this->criarTarefa($agenciaA, $userA['id'], (int) $clienteA['id'], null, 'Atrasada própria', $atrasada);

        $this->actingAs($userA['id'], permissions: self::PERM_TAREFAS);

        $dashboard = $this->get('/dashboard');
        $this->assertSame(200, $dashboard->getStatus());
        $this->assertStringContainsString('Atrasada legada', $dashboard->getBody());
        $this->assertStringContainsString('Cliente da A', $dashboard->getBody());
        $this->assertStringNotContainsString('Cliente da B', $dashboard->getBody());
    }

    // ── 6. Sync do ClickUp escrevendo em tasks ────────────────────────────────

    public function test_clickup_nao_grava_external_id_em_tarefa_de_outra_agencia(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $userA    = $this->createUser($agenciaA, 'a@test.com');
        $tarefaA  = $this->criarTarefa($agenciaA, $userA['id'], null, null, 'Tarefa da A');

        $repo = new ClickUpIntegrationRepository();

        // Negativa: o payload do job vem com outra agência — nada é escrito.
        $repo->updateExternalId($tarefaA, $agenciaB, 'clickup-999');
        $this->assertNull($this->externalIdDaTarefa($tarefaA));

        // Positiva: com a agência dona, o id externo é gravado.
        $repo->updateExternalId($tarefaA, $agenciaA, 'clickup-123');
        $this->assertSame('clickup-123', $this->externalIdDaTarefa($tarefaA));
    }

    private function externalIdDaTarefa(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT external_id FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $value = $stmt->fetchColumn();

        return $value === null || $value === false ? null : (string) $value;
    }

    // ── 7. action_type / type: allowlist na escrita e escape na leitura ───────

    /**
     * `action_type` e `ai_insights.type` são rótulo na tela: o `<select>` era a
     * única barreira e a view imprimia o valor do banco sem `e()` no fallback.
     * O payload é fatiável entre linhas, então o limite da coluna não protege.
     */
    public function test_acao_manual_recusa_tipo_fora_da_lista(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: tipo do catálogo grava.
        $this->post('/trafego/acoes', [
            'ad_account_id' => $conta,
            'action_type'   => 'pause',
            'description'   => 'Pausar a campanha',
        ]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: tipo inventado (aqui, com HTML dentro) não chega ao banco.
        $this->limparFlash();
        $this->post('/trafego/acoes', [
            'ad_account_id' => $conta,
            'action_type'   => '<script>alert(1)</script>',
            'description'   => 'Payload',
        ]);
        $this->assertSame(1, $this->contarAcoes());
        $this->assertStringContainsString('Tipo de ação inválido', $this->erroNoFlash());
    }

    public function test_sugestao_da_ia_recusa_tipo_fora_da_lista(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        $sugestao = fn (string $tipo): string => (string) json_encode([
            'action_type' => $tipo,
            'description' => 'Sugestão da IA',
        ]);

        // Positiva: tipo do catálogo vira solicitação.
        $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $conta,
            'suggestions' => [$sugestao('pause')],
        ]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: o JSON do hidden é editável como qualquer campo do POST.
        $this->limparFlash();
        $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $conta,
            'suggestions' => [$sugestao('<svg onload=alert(1)>')],
        ]);
        $this->assertSame(1, $this->contarAcoes());
        $this->assertStringContainsString('recusada', $this->erroNoFlash());
    }

    public function test_lista_e_detalhe_de_acoes_escapam_tipo_gravado_antes_da_validacao(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');
        $acao    = $this->criarAcaoComTipo($agencia, $conta, '<script>alert(1)</script>');

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        foreach (['/trafego/acoes', "/trafego/acoes/{$acao}"] as $rota) {
            $resposta = $this->get($rota);
            $this->assertSame(200, $resposta->getStatus(), $rota);
            // Positiva: a linha aparece (o escape não escondeu a ação).
            $this->assertStringContainsString('Ação legada', $resposta->getBody(), $rota);
            $this->assertStringContainsString('&lt;script&gt;', $resposta->getBody(), $rota);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $resposta->getBody(), $rota);
        }
    }

    /** Mesma classe no painel: `campaigns.status` vem do sync e saía sem `e()`. */
    public function test_painel_de_trafego_escapa_status_da_campanha(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');
        $this->criarCampanha($conta, 'Campanha da A');
        $this->pdo->prepare("UPDATE campaigns SET status = '<script>alert(1)</script>' WHERE ad_account_id = :a")
            ->execute([':a' => $conta]);

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        $painel = $this->get('/trafego');
        $this->assertSame(200, $painel->getStatus());
        // Positiva: a campanha continua na tabela do painel.
        $this->assertStringContainsString('Campanha da A', $painel->getBody());
        $this->assertStringContainsString('&lt;script&gt;', $painel->getBody());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $painel->getBody());
    }

    public function test_lista_e_detalhe_de_insights_escapam_tipo_gravado_antes_da_validacao(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');
        $insight = $this->criarInsightComTipo($agencia, $conta, '<svg onload=alert(1)>');

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        foreach (['/ia', "/ia/{$insight}"] as $rota) {
            $resposta = $this->get($rota);
            $this->assertSame(200, $resposta->getStatus(), $rota);
            // Positiva: o insight continua listado/aberto.
            $this->assertStringContainsString('Conta da A', $resposta->getBody(), $rota);
            $this->assertStringContainsString('&lt;svg', $resposta->getBody(), $rota);
            $this->assertStringNotContainsString('<svg onload=', $resposta->getBody(), $rota);
        }
    }

    public function test_gerar_insight_recusa_tipo_fora_da_lista(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');

        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);

        // Positiva: tipo do catálogo passa da validação e segue para o próximo
        // portão (a IA não está configurada no teste — nada de rede).
        $this->post('/ia/gerar', ['ad_account_id' => $conta, 'type' => 'alert']);
        $this->assertStringContainsString('IA ainda não foi configurada', $this->erroNoFlash());

        // Negativa: tipo inventado para antes, sem gravar nem chamar a IA.
        $this->limparFlash();
        $this->post('/ia/gerar', ['ad_account_id' => $conta, 'type' => '<svg onload=alert(1)>']);
        $this->assertStringContainsString('Tipo de análise inválido', $this->erroNoFlash());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM ai_insights')->fetchColumn());
    }

    // ── 8. Permissão das rotas de IA que escrevem ou gastam a chave ───────────

    /**
     * `/ia/recomendacoes/salvar` grava em `ads_actions` — a mesma escrita que
     * `/trafego/acoes` cobra com `ads_actions.request`. Com só `ai_insights.view`
     * um perfil de leitura enfileirava solicitação para o aprovador executar na
     * Meta. E `/ia/gerar` + `/ia/recomendacoes` queimam a chave da plataforma:
     * as permissões próprias existiam no catálogo e não eram verificadas.
     */
    public function test_rotas_de_ia_que_escrevem_ou_chamam_a_ia_exigem_permissao_propria(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');

        $sugestao = (string) json_encode(['action_type' => 'pause', 'description' => 'Pausar tudo']);

        // Positiva: o Gestor de Tráfego alcança as três rotas.
        $this->actingAs($user['id'], permissions: self::PERM_TRAFEGO);
        $this->assertSame(200, $this->get('/ia/gerar')->getStatus());
        $this->assertSame(200, $this->get('/ia/recomendacoes')->getStatus());
        $this->post('/ia/recomendacoes/salvar', ['account_id' => $conta, 'suggestions' => [$sugestao]]);
        $this->assertSame(1, $this->contarAcoes());

        // Negativa: o perfil de leitura leva 403 e não grava nada.
        $this->limparFlash();
        $this->actingAs($user['id'], permissions: self::PERM_LEITURA_IA);
        $this->assertSame(200, $this->get('/ia')->getStatus(), 'a lista continua aberta para quem só lê');
        $this->assertSame(403, $this->get('/ia/gerar')->getStatus());
        $this->assertSame(403, $this->post('/ia/gerar', ['ad_account_id' => $conta])->getStatus());
        $this->assertSame(403, $this->get('/ia/recomendacoes')->getStatus());
        $this->assertSame(
            403,
            $this->post('/ia/recomendacoes/salvar', ['account_id' => $conta, 'suggestions' => [$sugestao]])->getStatus()
        );
        $this->assertSame(1, $this->contarAcoes());
    }

    /** Quem pode recomendar mas não solicitar também não grava a ação. */
    public function test_salvar_recomendacao_exige_ads_actions_request(): void
    {
        $agencia = $this->createAgency('Agência A');
        $conta   = $this->criarConta($agencia, null, 'Conta da A');
        $user    = $this->createUser($agencia, 'a@test.com');

        $sugestao = (string) json_encode(['action_type' => 'pause', 'description' => 'Pausar tudo']);

        $this->actingAs($user['id'], permissions: [
            ...self::PERM_LEITURA_IA, 'ai.recommend_ads_action',
        ]);

        // Positiva: com a permissão de recomendar, a tela abre.
        $this->assertSame(200, $this->get('/ia/recomendacoes')->getStatus());

        // Negativa: sem `ads_actions.request`, salvar é 403 e nada é gravado.
        $resposta = $this->post('/ia/recomendacoes/salvar', [
            'account_id'  => $conta,
            'suggestions' => [$sugestao],
        ]);
        $this->assertSame(403, $resposta->getStatus());
        $this->assertSame(0, $this->contarAcoes());
    }

    /** Solicitação gravada com tipo fora do catálogo, como as de antes da allowlist. */
    private function criarAcaoComTipo(int $agencyId, int $accountId, string $tipo): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ads_actions (agency_id, ad_account_id, action_type, description, status, created_at, updated_at)
             VALUES (:a, :acc, :t, 'Ação legada', 'pending', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':acc' => $accountId, ':t' => $tipo]);

        return (int) $stmt->fetchColumn();
    }

    private function criarInsightComTipo(int $agencyId, int $accountId, string $tipo): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ai_insights (agency_id, ad_account_id, type, content, created_at)
             VALUES (:a, :acc, :t, 'Conteúdo do insight', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':acc' => $accountId, ':t' => $tipo]);

        return (int) $stmt->fetchColumn();
    }
}
