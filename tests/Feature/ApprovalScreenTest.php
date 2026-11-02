<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Tela interna de Aprovações (BUG-04) — a tela tinha zero teste.
 *
 * O que estava quebrado e estes testes travam:
 *   - a lista e as mutações varriam `$_SESSION['client_ids']`, que só tem
 *     vínculo explícito de `client_user_access`. Quem alcança a carteira por
 *     `clients.view_all` (social media, designer, gestor de tráfego) via
 *     "Tudo em dia!" com plano parado esperando — e levava 403 ao aprovar;
 *   - o feedback do item exigia `approvals.approved|changes_requested|rejected`,
 *     três permissões que não existem no catálogo: ninguém aprovava item;
 *   - nada garantia que plano de outra agência (ou de cliente fora do alcance)
 *     não abrisse.
 */
class ApprovalScreenTest extends FeatureTestCase
{
    /** @return array{plan:int,item:int,client:int,token:string} */
    private function seedPlan(
        int $agencyId,
        string $status = 'sent',
        string $title = 'Semana 06/07',
        ?string $notes = null,
    ): array {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, notes, sent_at, created_at)
             VALUES (:a, :c, :t, '2026-07-06', '2026-07-12', :s, :n, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':t' => $title, ':s' => $status, ':n' => $notes]);
        $planId = (int) $stmt->fetchColumn();

        $item = $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, platform, content_type, title, status, sort_order, created_at)
             VALUES (:p, :c, '2026-07-08', 'instagram', 'Feed', 'Post de quarta', 'draft', 0, NOW())
             RETURNING id"
        );
        $item->execute([':p' => $planId, ':c' => $client['id']]);

        return [
            'plan'   => $planId,
            'item'   => (int) $item->fetchColumn(),
            'client' => (int) $client['id'],
            // O portal do cliente entra pelo token: é lá que se vê o que a
            // tela interna deixa vazar.
            'token'  => (string) $client['portal_token'],
        ];
    }

    /** Papel de equipe: alcança a carteira por view_all, sem vínculo por cliente. */
    private function teamPermissions(string ...$extra): array
    {
        return array_merge(['approvals.view', 'clients.view_all'], $extra);
    }

    private function planNotes(int $planId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT notes FROM content_plans WHERE id = :id');
        $stmt->execute([':id' => $planId]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    private function planStatus(int $planId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM content_plans WHERE id = :id');
        $stmt->execute([':id' => $planId]);

        return (string) $stmt->fetchColumn();
    }

    private function itemStatus(int $itemId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM content_plan_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);

        return (string) $stmt->fetchColumn();
    }

    /** Origem gravada no feedback do item ('client' = portal, 'agency' = equipe). */
    private function feedbackSource(int $itemId): string
    {
        $stmt = $this->pdo->prepare('SELECT source FROM content_feedbacks WHERE content_plan_item_id = :i ORDER BY id DESC LIMIT 1');
        $stmt->execute([':i' => $itemId]);

        return (string) $stmt->fetchColumn();
    }

    // ── Lista ────────────────────────────────────────────────────────────────

    public function test_equipe_com_view_all_ve_o_plano_enviado_na_lista(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        // Nenhum client_user_access: é exatamente o caso do social media.
        $this->actingAs($user['id'], permissions: $this->teamPermissions());

        $response = $this->get('/aprovacoes');

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Semana 06/07', $response->getBody());
        $this->assertStringNotContainsString('Tudo em dia!', $response->getBody());
    }

    public function test_lista_nao_mostra_plano_de_cliente_fora_do_alcance(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->seedPlan($agencyId);
        // Sem view_all e sem vínculo: alcança zero clientes.
        $this->actingAs($user['id'], permissions: ['approvals.view']);

        $body = $this->get('/aprovacoes')->getBody();

        $this->assertStringNotContainsString('Semana 06/07', $body);
        // E a tela explica o porquê em vez de mentir "Tudo em dia!".
        $this->assertStringContainsString('Seu perfil não alcança nenhum cliente', $body);
    }

    public function test_lista_nao_vaza_plano_de_outra_agencia(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $this->seedPlan($agencyB, title: 'Semana da Agência B');
        $this->actingAs($user['id'], permissions: $this->teamPermissions());

        $body = $this->get('/aprovacoes')->getBody();

        $this->assertStringNotContainsString('Semana da Agência B', $body);
    }

    public function test_lista_nao_mostra_rascunho(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->seedPlan($agencyId, status: 'draft', title: 'Rascunho da semana');
        $this->actingAs($user['id'], permissions: $this->teamPermissions());

        $this->assertStringNotContainsString('Rascunho da semana', $this->get('/aprovacoes')->getBody());
    }

    public function test_sem_permissao_de_aprovacoes_a_lista_responde_403(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: ['content.view', 'clients.view_all']);

        $this->assertSame(403, $this->get('/aprovacoes')->getStatus());
    }

    // ── Detalhe ──────────────────────────────────────────────────────────────

    public function test_equipe_com_view_all_abre_o_plano(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions());

        $response = $this->get("/aprovacoes/{$seed['plan']}");

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Post de quarta', $response->getBody());
    }

    public function test_plano_de_outra_agencia_nao_abre(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $seed    = $this->seedPlan($agencyB);
        $this->actingAs($user['id'], permissions: $this->teamPermissions());

        $this->assertSame(404, $this->get("/aprovacoes/{$seed['plan']}")->getStatus());
    }

    /**
     * Perfil que aprova mas não comenta (as duas permissões são independentes)
     * só vê o chip "Aprovar". A view precisa passar esse tipo para o componente
     * Alpine: nascer em 'comment' manda o POST para o único tipo que o
     * controller recusa com 403 (ver ApprovalItemDefaultTypeTest para o lado JS).
     */
    public function test_perfil_que_so_aprova_recebe_o_tipo_approved_no_componente(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $body = $this->get("/aprovacoes/{$seed['plan']}")->getBody();

        $this->assertStringContainsString("approvalItem(", $body);
        $this->assertStringContainsString(", 'approved')", $body);
        $this->assertStringNotContainsString(", 'comment')", $body);
    }

    /**
     * Quem aprova **e** comenta (o social media padrão) nasce em 'comment'.
     *
     * Pré-selecionar o primeiro chip deixava 'approved' armado: o usuário abria
     * "Dar Feedback" para deixar um recado, clicava no botão genérico "Enviar
     * feedback" e aprovava o post — e aprovar o último post fecha o plano e
     * dispara a confirmação de aprovação para o cliente no WhatsApp.
     */
    public function test_perfil_que_aprova_e_comenta_nasce_em_comentar(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve', 'approvals.comment'));

        $body = $this->get("/aprovacoes/{$seed['plan']}")->getBody();

        $this->assertStringContainsString(", 'comment')", $body);
        $this->assertStringNotContainsString(", 'approved')", $body);
    }

    public function test_plano_de_cliente_fora_do_alcance_nao_abre(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: ['approvals.view']);

        $this->assertSame(404, $this->get("/aprovacoes/{$seed['plan']}")->getStatus());
    }

    // ── Aprovar o plano ──────────────────────────────────────────────────────

    public function test_social_media_aprova_o_plano_sem_403(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve', 'approvals.comment'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('approved', $this->planStatus($seed['plan']));
    }

    public function test_sem_permissao_de_aprovar_o_plano_nao_muda(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame(403, $response->getStatus());
        $this->assertSame('sent', $this->planStatus($seed['plan']));
    }

    public function test_plano_de_outra_agencia_nao_pode_ser_aprovado(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $seed    = $this->seedPlan($agencyB);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('sent', $this->planStatus($seed['plan']));
    }

    public function test_cliente_fora_do_alcance_nao_pode_ser_aprovado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        // Tem a permissão, não tem o cliente: 404, como plano inexistente.
        $this->actingAs($user['id'], permissions: ['approvals.view', 'approvals.approve']);

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('sent', $this->planStatus($seed['plan']));
    }

    public function test_mutacao_sem_csrf_nao_aprova(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar", withCsrf: false);

        $this->assertNotSame(302, $response->getStatus());
        $this->assertSame('sent', $this->planStatus($seed['plan']));
    }

    // ── Pedir revisão do plano ───────────────────────────────────────────────

    public function test_pede_revisao_do_plano_com_a_nota(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/revisao", ['note' => 'Trocar a capa de quarta.']);

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('revision', $this->planStatus($seed['plan']));
        $this->assertStringContainsString('Trocar a capa de quarta.', (string) $this->planNotes($seed['plan']));
    }

    /**
     * `content_plans.notes` é o campo "Observações para a equipe" do formulário
     * do plano. O modal de revisão não exige texto: a nota vazia gravava ''
     * em cima das observações e o gestor perdia o que havia escrito.
     */
    public function test_revisao_sem_nota_nao_apaga_as_observacoes_da_equipe(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId, notes: 'Cliente só aprova carrossel.');
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $response = $this->post("/aprovacoes/{$seed['plan']}/revisao", ['note' => '   ']);

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('revision', $this->planStatus($seed['plan']));
        $this->assertSame('Cliente só aprova carrossel.', $this->planNotes($seed['plan']));
    }

    public function test_nota_de_revisao_acrescenta_sem_apagar_as_observacoes(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId, notes: 'Cliente só aprova carrossel.');
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.approve'));

        $this->post("/aprovacoes/{$seed['plan']}/revisao", ['note' => 'Trocar a capa de quarta.']);

        $notes = (string) $this->planNotes($seed['plan']);
        $this->assertStringContainsString('Cliente só aprova carrossel.', $notes);
        $this->assertStringContainsString('Trocar a capa de quarta.', $notes);
        // Cabeçalho datado (o texto em si vem de t(), no idioma da requisição).
        $this->assertStringContainsString(date('d/m/Y'), $notes);
    }

    // ── Feedback no item ─────────────────────────────────────────────────────

    public function test_aprovar_item_exige_approvals_approve_e_grava_o_status(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $response = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'approved', 'comment' => 'Fechou.']
        );

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('"success":true', $response->getBody());
        $this->assertSame('approved', $this->itemStatus($seed['item']));
    }

    public function test_pedir_ajuste_no_item_poe_o_item_em_revisao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment'));

        $response = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'changes_requested', 'comment' => 'Capa cortando o produto.']
        );

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('revision', $this->itemStatus($seed['item']));

        $stmt = $this->pdo->prepare('SELECT comment FROM content_feedbacks WHERE content_plan_item_id = :i');
        $stmt->execute([':i' => $seed['item']]);
        $this->assertSame('Capa cortando o produto.', $stmt->fetchColumn());
    }

    public function test_reprovar_item_exige_approvals_reject(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment'));

        $negada = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'rejected', 'comment' => 'Não serve.']
        );

        $this->assertSame(403, $negada->getStatus());
        $this->assertSame('draft', $this->itemStatus($seed['item']));

        // Positivo: com a permissão, a mesma rota reprova.
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.reject'));
        $permitida = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'rejected', 'comment' => 'Não serve.']
        );

        $this->assertSame(200, $permitida->getStatus());
        $this->assertSame('rejected', $this->itemStatus($seed['item']));
    }

    public function test_tipo_de_feedback_inventado_e_recusado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $response = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'approvals.approve', 'comment' => 'x']
        );

        $this->assertSame(422, $response->getStatus());
        $this->assertSame('draft', $this->itemStatus($seed['item']));
    }

    public function test_item_de_outro_plano_nao_recebe_feedback(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $alvo     = $this->seedPlan($agencyId, title: 'Plano alvo');
        $outro    = $this->seedPlan($agencyId, title: 'Outro plano');
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $response = $this->post(
            "/aprovacoes/{$alvo['plan']}/items/{$outro['item']}",
            ['feedback_type' => 'approved']
        );

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('draft', $this->itemStatus($outro['item']));
    }

    public function test_item_de_outra_agencia_nao_recebe_feedback(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $user    = $this->createUser($agencyA, 'a@test.com');
        $seed    = $this->seedPlan($agencyB);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $response = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'approved']
        );

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('draft', $this->itemStatus($seed['item']));
    }

    /**
     * A anotação interna da equipe é `source = 'agency'` e **não** aparece no
     * portal do cliente.
     *
     * Gravada como 'client' com `client_id` do plano, ela ia para o portal
     * assinada com o nome do próprio cliente (o template lê
     * `fb.client_name || fb.user_name`): o cliente lia "Padaria do Zé — capa
     * horrível, refazer" como se tivesse escrito aquilo.
     */
    public function test_comentario_interno_nao_vaza_para_o_portal_do_cliente(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment'));

        $enviado = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'comment', 'comment' => 'Capa horrível, o cliente vai reclamar — refazer.']
        );

        $this->assertSame(200, $enviado->getStatus());
        $this->assertSame('agency', $this->feedbackSource($seed['item']));
        // Comentário puro da equipe não muda o status do post.
        $this->assertSame('draft', $this->itemStatus($seed['item']));

        // A tela interna continua mostrando a anotação…
        $this->assertStringContainsString('Capa horrível', $this->get("/aprovacoes/{$seed['plan']}")->getBody());

        // …e o portal do cliente, não.
        $portal = $this->get("/portal/{$seed['token']}/planos/{$seed['plan']}");
        $this->assertSame(200, $portal->getStatus());
        $this->assertStringNotContainsString('Capa horr', $portal->getBody());
    }

    /**
     * Plano aprovado não recebe mais feedback de item — a regra existia só na
     * view (que esconde o formulário), e uma aba aberta de antes da aprovação
     * ainda conseguia pôr em revisão um post dentro de um plano "Aprovado".
     */
    public function test_plano_aprovado_nao_recebe_mais_feedback_no_item(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $response = $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'changes_requested', 'comment' => 'Trocar a capa.']
        );

        $this->assertSame(409, $response->getStatus());
        $this->assertSame('draft', $this->itemStatus($seed['item']));
        $this->assertSame('approved', $this->planStatus($seed['plan']));
    }

    public function test_ultimo_item_aprovado_aprova_o_plano(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $seed     = $this->seedPlan($agencyId);
        $this->actingAs($user['id'], permissions: $this->teamPermissions('approvals.comment', 'approvals.approve'));

        $this->post(
            "/aprovacoes/{$seed['plan']}/items/{$seed['item']}",
            ['feedback_type' => 'approved']
        );

        // O plano tem um item só: aprovado ele, o plano inteiro fecha.
        $this->assertSame('approved', $this->planStatus($seed['plan']));
    }
}
