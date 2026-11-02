<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * BUG-05 — máquina de estados do plano de conteúdo.
 *
 * O que estava quebrado e estes testes travam:
 *   - plano já aprovado recebia post novo e perdia post: o selo "Aprovado"
 *     passava a afirmar que a cliente autorizou conteúdo que nunca viu (ou
 *     escondia o que ela aprovou);
 *   - aprovar item a item chamava `approvePlan` sem olhar o status do plano —
 *     o guard do portal ("só plano enviado é aprovado") era contornado, e
 *     plano em revisão virava aprovado sem reenvio;
 *   - "Aprovar Tudo"/"Pedir Revisão" em plano já aprovado era aceito,
 *     regravava `approved_at` e re-disparava a notificação;
 *   - trocar a semana do plano deixava todo post com data fora da semana nova,
 *     e `assertDateInPlanWeek` passava a recusar qualquer edição deles.
 */
class ContentPlanStateMachineTest extends FeatureTestCase
{
    /** @return array{plan:int,item:int,client:int,token:string} */
    private function seedPlan(int $agencyId, string $status = 'sent'): array
    {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans
                (agency_id, client_id, title, week_start, week_end, status, sent_at, approved_at, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', :s, NOW(),
                     CASE WHEN :s2 = 'approved' THEN TIMESTAMP '2026-07-05 10:00:00' ELSE NULL END, NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':s' => $status, ':s2' => $status]);
        $planId = (int) $stmt->fetchColumn();

        // Quarta-feira da semana do plano.
        $item = $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, publish_time, platform, content_type, title, status, sort_order, created_at)
             VALUES (:p, :c, '2026-07-08', '10:30', 'instagram', 'Feed Estático', 'Post de quarta', 'draft', 0, NOW())
             RETURNING id"
        );
        $item->execute([':p' => $planId, ':c' => $client['id']]);

        return [
            'plan'   => $planId,
            'item'   => (int) $item->fetchColumn(),
            'client' => (int) $client['id'],
            'token'  => (string) $client['portal_token'],
        ];
    }

    /** Post extra no plano — para provar o que a aprovação em bloco faz com cada um. */
    private function addItem(int $planId, int $clientId, string $date, string $status): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, platform, content_type, title, status, sort_order, created_at)
             VALUES (:p, :c, :d, 'instagram', 'Story', 'Post extra', :s, 1, NOW())
             RETURNING id"
        );
        $stmt->execute([':p' => $planId, ':c' => $clientId, ':d' => $date, ':s' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /** Status do post direto no banco — o estado de onde o teste parte. */
    private function setItemStatus(int $itemId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE content_plan_items SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $itemId]);
    }

    /** Equipe de conteúdo com alcance por `clients.view_all`. */
    private function actingAsEditor(int $agencyId, string $email = 'user@test.com'): void
    {
        $user = $this->createUser($agencyId, $email);
        $this->actingAs($user['id'], permissions: [
            'content.view', 'content.create', 'content.edit', 'content.delete', 'clients.view_all',
        ]);
    }

    private function actingAsApprover(int $agencyId): void
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: [
            'approvals.view', 'approvals.approve', 'approvals.comment', 'clients.view_all',
        ]);
    }

    /** @return array<string,mixed> */
    private function plan(int $planId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_plans WHERE id = :id');
        $stmt->execute([':id' => $planId]);

        return $stmt->fetch() ?: [];
    }

    private function itemCount(int $planId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM content_plan_items WHERE content_plan_id = :p');
        $stmt->execute([':p' => $planId]);

        return (int) $stmt->fetchColumn();
    }

    private function itemDate(int $itemId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT publish_date FROM content_plan_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    private function itemPlan(int $itemId): int
    {
        $stmt = $this->pdo->prepare('SELECT content_plan_id FROM content_plan_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,int> status → quantos posts do plano */
    private function itemStatuses(int $planId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) FROM content_plan_items WHERE content_plan_id = :p GROUP BY status'
        );
        $stmt->execute([':p' => $planId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['status']] = (int) $row['count'];
        }

        return $map;
    }

    private function planCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM content_plans')->fetchColumn();
    }

    private function logCount(string $action): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE action = :a');
        $stmt->execute([':a' => $action]);

        return (int) $stmt->fetchColumn();
    }

    /** As rotas de item respondem JSON; o Accept precisa dizer isso. */
    private function postJson(string $uri, array $data = []): \App\Core\Response
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        try {
            return $this->post($uri, $data);
        } finally {
            unset($_SERVER['HTTP_ACCEPT']);
        }
    }

    /** @return array<string,mixed> */
    private function json(\App\Core\Response $response): array
    {
        $decoded = json_decode($response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function newPostPayload(): array
    {
        return [
            'publish_date' => '2026-07-09',
            'platform'     => 'instagram',
            'content_type' => 'Reels / Vídeo',
            'title'        => 'Post de quinta',
        ];
    }

    // ── Post em plano aprovado ───────────────────────────────────────────────

    public function test_plano_enviado_ainda_aceita_post_novo(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        $response = $this->postJson("/conteudo/{$seed['plan']}/items", $this->newPostPayload());

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
        $this->assertSame(2, $this->itemCount($seed['plan']));
    }

    public function test_plano_aprovado_nao_recebe_post_novo(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        $response = $this->postJson("/conteudo/{$seed['plan']}/items", $this->newPostPayload());

        $this->assertSame(422, $response->getStatus());
        $this->assertFalse($this->json($response)['success'] ?? true);
        $this->assertNotEmpty($this->json($response)['error'] ?? '', 'A recusa tem de dizer por quê.');
        $this->assertSame(1, $this->itemCount($seed['plan']), 'Semana fechada com a cliente não ganha post por trás.');
    }

    public function test_plano_enviado_ainda_exclui_post(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", ['_method' => 'DELETE']);

        $this->assertTrue($this->json($response)['success'] ?? false);
        $this->assertSame(0, $this->itemCount($seed['plan']));
    }

    public function test_plano_aprovado_nao_perde_post(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", ['_method' => 'DELETE']);

        $this->assertSame(422, $response->getStatus());
        $this->assertNotEmpty($this->json($response)['error'] ?? '');
        $this->assertSame(1, $this->itemCount($seed['plan']), 'O post que a cliente aprovou não pode desaparecer.');
    }

    public function test_a_tela_do_plano_aprovado_nao_oferece_adicionar_nem_excluir_post(): void
    {
        $agencyId = $this->createAgency();
        $aberto   = $this->seedPlan($agencyId);
        $fechado  = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Positivo: no plano aberto as duas ações estão na tela.
        $abertoBody = $this->get("/conteudo/{$aberto['plan']}")->getBody();
        $this->assertStringContainsString('openAddPost(', $abertoBody);
        $this->assertStringContainsString('deleteItem()', $abertoBody);

        $fechadoBody = $this->get("/conteudo/{$fechado['plan']}")->getBody();
        $this->assertStringNotContainsString('openAddPost(', $fechadoBody, 'Plano aprovado não oferece adicionar post.');
        $this->assertStringNotContainsString('deleteItem()', $fechadoBody, 'Plano aprovado não oferece excluir post.');
        $this->assertStringContainsString('Semana aprovada pela cliente', $fechadoBody, 'A tela explica por que as ações sumiram.');
        // O "Reagendar" continua lá: é a saída da semana fechada — o post muda
        // de data (dentro ou fora dela) a pedido da cliente, sem desaparecer.
        $this->assertStringContainsString('reschedule()', $fechadoBody);
    }

    public function test_a_tela_do_plano_aprovado_nao_oferece_o_menu_de_status_do_post(): void
    {
        $agencyId = $this->createAgency();
        $aberto   = $this->seedPlan($agencyId);
        $fechado  = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Positivo: no plano aberto o menu está lá — mas sem "Aprovado", que é
        // decisão do fluxo de aprovação (portal ou tela de Aprovações).
        $abertoBody = $this->get("/conteudo/{$aberto['plan']}")->getBody();
        $this->assertStringContainsString("changeStatus('revision')", $abertoBody);
        $this->assertStringNotContainsString("changeStatus('approved')", $abertoBody);

        $fechadoBody = $this->get("/conteudo/{$fechado['plan']}")->getBody();
        $this->assertStringNotContainsString('changeStatus(', $fechadoBody, 'Em semana fechada o status do post não muda mais.');
    }

    // ── Status do post por edição livre ──────────────────────────────────────

    public function test_menu_de_status_ainda_pede_revisao_do_post(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        // Positivo da mesma rota: o sinal interno de "refazer" continua valendo.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", [
            '_method' => 'PUT',
            'status'  => 'revision',
        ]);

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
        $this->assertSame(['revision' => 1], $this->itemStatuses($seed['plan']));
    }

    public function test_menu_de_status_nao_marca_o_post_como_aprovado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        // Era a última porta para a mentira do BUG-05: marcar "Aprovado" na mão
        // num plano enviado faz a contagem do plano fechar a semana no primeiro
        // "Aprovar" da cliente, afirmando que ela aprovou o que nunca viu.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", [
            '_method' => 'PUT',
            'status'  => 'approved',
        ]);

        $this->assertSame(422, $response->getStatus());
        $this->assertNotEmpty($this->json($response)['error'] ?? '');
        $this->assertSame(['draft' => 1], $this->itemStatuses($seed['plan']));
    }

    public function test_plano_aprovado_ainda_edita_o_conteudo_do_post(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Positivo da mesma rota em semana fechada: a equipe ainda anexa a peça
        // pronta e corrige a legenda depois da aprovação (decisão consciente,
        // registrada no Plano Mestre) — o que fechou foi o STATUS.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", [
            '_method' => 'PUT',
            'caption' => 'Legenda final.',
        ]);

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
    }

    public function test_plano_aprovado_nao_devolve_post_para_rascunho(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->setItemStatus($seed['item'], 'approved');
        $this->actingAsEditor($agencyId);

        // Devolver para rascunho recriava a contradição: selo "Aprovado" ao lado
        // de "0/1 aprovados", post "Pendente" no portal — e sem volta, porque o
        // feedback de item em plano aprovado responde 409 nas duas telas.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", [
            '_method' => 'PUT',
            'status'  => 'draft',
        ]);

        $this->assertSame(422, $response->getStatus());
        $this->assertNotEmpty($this->json($response)['error'] ?? '');
        $this->assertSame(['approved' => 1], $this->itemStatuses($seed['plan']));
    }

    // ── Tirar post da semana aprovada (reagendar) ────────────────────────────

    public function test_plano_aprovado_ainda_remaneja_post_dentro_da_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Positivo: dentro da própria semana aprovada o post só troca de dia —
        // continua no plano que a cliente fechou.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}/reagendar", [
            'publish_date' => '2026-07-10',
        ]);

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
        $this->assertSame('2026-07-10', $this->itemDate($seed['item']));
        $this->assertSame($seed['plan'], $this->itemPlan($seed['item']));
    }

    public function test_plano_aprovado_libera_o_post_para_outra_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->setItemStatus($seed['item'], 'approved');
        $this->actingAsEditor($agencyId);

        // É o caso que o CONT-07 existe para resolver: a cliente aprovou a
        // semana e pediu para publicar o post na seguinte. Bloquear a ORIGEM
        // aprovada deixava a semana sem nenhuma saída e devolvia o retrabalho
        // de recriar o post à mão. O post não desaparece como em `deleteItem`:
        // muda de data inteiro, com o movimento no `activity_logs`.
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}/reagendar", [
            'publish_date' => '2026-07-15',
        ]);

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
        $this->assertSame('2026-07-15', $this->itemDate($seed['item']));
        $this->assertNotSame($seed['plan'], $this->itemPlan($seed['item']), 'O post muda de plano.');
        $this->assertSame(2, $this->planCount(), 'A semana de destino nasce em rascunho.');

        $destino = $this->plan($this->itemPlan($seed['item']));
        $this->assertSame('draft', $destino['status']);
        $this->assertSame('approved', $this->plan($seed['plan'])['status'], 'A semana de origem segue aprovada.');
        // O status do post é preservado: adiar não desfaz a decisão da cliente.
        $this->assertSame(['approved' => 1], $this->itemStatuses((int) $destino['id']));
    }

    // ── Aprovação item a item (portal) ───────────────────────────────────────

    public function test_ultimo_item_aprovado_no_portal_fecha_o_plano_enviado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);

        $response = $this->post(
            "/portal/{$seed['token']}/planos/{$seed['plan']}/items/{$seed['item']}/feedback",
            ['feedback_type' => 'approved']
        );

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('approved', $this->plan($seed['plan'])['status']);

        // A tela precisa saber que o plano fechou NESTE envio: o formulário
        // dos outros posts continua montado e o próximo clique levaria 409.
        $this->assertTrue($this->json($response)['plan_closed'] ?? false);
    }

    public function test_plano_aprovado_nao_recebe_feedback_de_item_no_portal(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');

        // A régua existia só na view do portal — e a view não é re-renderizada
        // depois que o plano fecha (o envio é fetch). O segundo clique punha um
        // post em revisão dentro de um plano "Aprovado", sem saída no sistema.
        $response = $this->post(
            "/portal/{$seed['token']}/planos/{$seed['plan']}/items/{$seed['item']}/feedback",
            ['feedback_type' => 'changes_requested', 'comment' => 'Refazer a capa.']
        );

        $this->assertSame(409, $response->getStatus());
        $this->assertNotEmpty($this->json($response)['error'] ?? '');
        $this->assertSame(['draft' => 1], $this->itemStatuses($seed['plan']), 'O post não pode ir para revisão.');

        $feedbacks = (int) $this->pdo->query('SELECT COUNT(*) FROM content_feedbacks')->fetchColumn();
        $this->assertSame(0, $feedbacks, 'Nem o registro do feedback pode entrar.');
    }

    public function test_aprovar_o_plano_no_portal_aprova_os_posts_pendentes(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->addItem($seed['plan'], $seed['client'], '2026-07-09', 'draft');

        $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame('approved', $this->plan($seed['plan'])['status']);
        // Sem isto a tela mostrava o selo "Aprovado" ao lado de "0/2 aprovados"
        // e o portal exibia cada post como "Pendente" — e, com o feedback de
        // item fechado em plano aprovado, não havia como reconciliar.
        $this->assertSame(['approved' => 2], $this->itemStatuses($seed['plan']));
    }

    public function test_aprovar_o_plano_no_portal_preserva_o_pedido_de_alteracao(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->addItem($seed['plan'], $seed['client'], '2026-07-09', 'revision');

        // A cliente pediu "refazer a capa" no post 2 e, para encerrar a semana,
        // clicou em "Aprovar plano". Aprovar em bloco apagava o pedido dela: o
        // chip virava "Aprovado", a contagem dizia 2/2 e o designer perdia o
        // único sinal de que aquele post tem de ser refeito — e ela não
        // consegue repetir o pedido (feedback de item em plano aprovado = 409).
        $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame('approved', $this->plan($seed['plan'])['status'], 'A semana fecha, sim.');
        $this->assertSame(['approved' => 1, 'revision' => 1], $this->itemStatuses($seed['plan']));
    }

    public function test_item_aprovado_no_portal_nao_fecha_plano_em_revisao(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'revision');

        $response = $this->post(
            "/portal/{$seed['token']}/planos/{$seed['plan']}/items/{$seed['item']}/feedback",
            ['feedback_type' => 'approved']
        );

        // O feedback vale (o item fica aprovado); o plano é que não fecha —
        // ele espera a agência corrigir e REENVIAR.
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('revision', $this->plan($seed['plan'])['status']);
        $this->assertSame(0, $this->logCount('content_plan.approved'));

        $status = $this->pdo->query("SELECT status FROM content_plan_items WHERE id = {$seed['item']}")->fetchColumn();
        $this->assertSame('approved', $status);
    }

    // ── Aprovar / pedir revisão do plano (tela interna) ──────────────────────

    public function test_aprovar_tudo_fecha_o_plano_enviado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsApprover($agencyId);

        $response = $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('approved', $this->plan($seed['plan'])['status']);
        $this->assertSame(1, $this->logCount('content_plan.approved'));
    }

    public function test_aprovar_tudo_aprova_os_posts_pendentes_do_plano(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->addItem($seed['plan'], $seed['client'], '2026-07-09', 'draft');
        $this->actingAsApprover($agencyId);

        $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame('approved', $this->plan($seed['plan'])['status']);
        $this->assertSame(['approved' => 2], $this->itemStatuses($seed['plan']), 'O post que ninguém decidiu vai com o plano.');
    }

    public function test_aprovar_tudo_nao_sobrescreve_o_pedido_de_alteracao_da_cliente(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->addItem($seed['plan'], $seed['client'], '2026-07-09', 'revision');
        $this->actingAsApprover($agencyId);

        // O "Aprovar Tudo" interno some com o pedido da cliente sem avisar
        // ninguém — e depois dele não há volta pela interface.
        $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame('approved', $this->plan($seed['plan'])['status']);
        $this->assertSame(['approved' => 1, 'revision' => 1], $this->itemStatuses($seed['plan']));
    }

    public function test_aprovar_tudo_em_plano_ja_aprovado_nao_reaprova(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsApprover($agencyId);

        $antes = $this->plan($seed['plan']);
        $this->post("/aprovacoes/{$seed['plan']}/aprovar");
        $depois = $this->plan($seed['plan']);

        $this->assertSame('approved', $depois['status']);
        $this->assertSame($antes['approved_at'], $depois['approved_at'], 'A data de aprovação não pode ser regravada.');
        $this->assertSame(0, $this->logCount('content_plan.approved'), 'Nada de segunda aprovação (e de segunda notificação).');
    }

    public function test_aprovar_tudo_em_plano_em_revisao_exige_reenvio(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'revision');
        $this->actingAsApprover($agencyId);

        $this->post("/aprovacoes/{$seed['plan']}/aprovar");

        $this->assertSame('revision', $this->plan($seed['plan'])['status']);
        $this->assertNull($this->plan($seed['plan'])['approved_at']);
    }

    public function test_aprovacoes_avisa_quando_a_cliente_aprovou_tudo_em_plano_em_revisao(): void
    {
        $agencyId = $this->createAgency();
        $pendente = $this->seedPlan($agencyId, status: 'revision');
        $fechavel = $this->seedPlan($agencyId, status: 'revision');
        $this->setItemStatus($fechavel['item'], 'approved');
        $this->actingAsApprover($agencyId);

        // Contraste: ainda há post sem decisão da cliente — nada a avisar.
        $this->assertStringNotContainsString(
            'aprovou todos os posts',
            $this->get("/aprovacoes/{$pendente['plan']}")->getBody()
        );

        // Aprovar item a item não fecha mais plano em revisão (ele espera o
        // reenvio). Sem este aviso a semana ficava parada sem ninguém saber que
        // a cliente já tinha decidido: a cobrança automática só olha 'sent' e o
        // botão de plano da tela interna só aparece em 'sent'.
        $this->assertStringContainsString(
            'aprovou todos os posts',
            $this->get("/aprovacoes/{$fechavel['plan']}")->getBody()
        );
    }

    public function test_pedir_revisao_em_plano_ja_aprovado_nao_reabre_a_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsApprover($agencyId);

        $this->post("/aprovacoes/{$seed['plan']}/revisao", ['note' => 'Trocar a capa.']);

        $depois = $this->plan($seed['plan']);
        $this->assertSame('approved', $depois['status'], 'Semana fechada não volta para revisão por POST solto.');
        $this->assertNotNull($depois['approved_at']);
        $this->assertSame(0, $this->logCount('content_plan.revision_requested'));
    }

    // ── Trocar a semana do plano ─────────────────────────────────────────────

    public function test_trocar_a_semana_leva_os_posts_mantendo_o_dia_da_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        // Post extra sem data: não tem dia para manter, logo não se move.
        $semData = $this->pdo->prepare(
            "INSERT INTO content_plan_items (content_plan_id, client_id, platform, status, sort_order, created_at)
             VALUES (:p, :c, 'instagram', 'draft', 1, NOW()) RETURNING id"
        );
        $semData->execute([':p' => $seed['plan'], ':c' => $seed['client']]);
        $semDataId = (int) $semData->fetchColumn();

        $response = $this->post("/conteudo/{$seed['plan']}", [
            '_method'    => 'PUT',
            'week_start' => '2026-07-15',   // quarta → encaixa em 13/07
        ]);

        $this->assertSame(302, $response->getStatus());

        $plan = $this->plan($seed['plan']);
        $this->assertSame('2026-07-13', $plan['week_start']);
        $this->assertSame('2026-07-19', $plan['week_end']);

        // Quarta 08/07 → quarta 15/07.
        $this->assertSame('2026-07-15', $this->itemDate($seed['item']));
        $this->assertNull($this->itemDate($semDataId), 'Post sem data continua sem data.');
        $this->assertStringContainsString('posts', (string) ($_SESSION['flash']['success'] ?? ''));
    }

    public function test_plano_aprovado_nao_troca_de_semana(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Com os posts sendo arrastados pela semana, trocar `week_start` num
        // plano aprovado re-datava em silêncio tudo o que a cliente fechou —
        // status 'approved' e `approved_at` intactos, nenhum aviso a ela.
        $response = $this->post("/conteudo/{$seed['plan']}", [
            '_method'    => 'PUT',
            'week_start' => '2026-07-13',
        ]);

        $this->assertSame(302, $response->getStatus());
        $this->assertNotEmpty($_SESSION['flash']['error'] ?? null, 'A recusa tem de aparecer na tela.');
        $this->assertArrayNotHasKey('success', $_SESSION['flash'] ?? []);

        $plan = $this->plan($seed['plan']);
        $this->assertSame('2026-07-06', $plan['week_start'], 'A semana aprovada fica onde está.');
        $this->assertSame('2026-07-12', $plan['week_end']);
        $this->assertSame('2026-07-08', $this->itemDate($seed['item']), 'E os posts dela também.');
    }

    public function test_plano_aprovado_ainda_pode_ser_renomeado(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // O guard é só da semana: título e notas internas não mexem no que a
        // cliente aprovou.
        $this->post("/conteudo/{$seed['plan']}", [
            '_method' => 'PUT',
            'title'   => 'Semana 06/07 (aprovada)',
            'notes'   => 'Produzir na segunda.',
        ]);

        $plan = $this->plan($seed['plan']);
        $this->assertSame('Semana 06/07 (aprovada)', $plan['title']);
        $this->assertSame('Produzir na segunda.', $plan['notes']);
        $this->assertNotEmpty($_SESSION['flash']['success'] ?? null);
    }

    public function test_a_tela_de_editar_nao_oferece_a_semana_do_plano_aprovado(): void
    {
        $agencyId = $this->createAgency();
        $aberto   = $this->seedPlan($agencyId);
        $fechado  = $this->seedPlan($agencyId, status: 'approved');
        $this->actingAsEditor($agencyId);

        // Positivo: no plano aberto o campo da semana está lá, editável.
        $abertoBody = $this->get("/conteudo/{$aberto['plan']}/editar")->getBody();
        $this->assertStringContainsString('name="week_start"', $abertoBody);

        $fechadoBody = $this->get("/conteudo/{$fechado['plan']}/editar")->getBody();
        $this->assertStringNotContainsString('name="week_start"', $fechadoBody, 'Plano aprovado não oferece trocar a semana.');
        $this->assertStringContainsString('Semana aprovada pela cliente', $fechadoBody, 'A tela explica por quê.');
    }

    public function test_depois_de_trocar_a_semana_o_post_continua_editavel(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        $this->post("/conteudo/{$seed['plan']}", ['_method' => 'PUT', 'week_start' => '2026-07-13']);

        // Antes, o post ficava com data da semana antiga e QUALQUER edição dele
        // levava 422 de "a data precisa estar dentro da semana do plano".
        $response = $this->postJson("/conteudo/{$seed['plan']}/items/{$seed['item']}", [
            '_method' => 'PUT',
            'title'   => 'Post de quarta (revisado)',
        ]);

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($this->json($response)['success'] ?? false);
    }

    public function test_update_de_plano_de_outra_agencia_responde_404(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $seedB   = $this->seedPlan($agencyB);
        $this->actingAsEditor($agencyA, 'a@test.com');

        $response = $this->post("/conteudo/{$seedB['plan']}", [
            '_method'    => 'PUT',
            'title'      => 'Invadido',
            'week_start' => '2026-08-03',
        ]);

        $this->assertSame(404, $response->getStatus());

        $plan = $this->plan($seedB['plan']);
        $this->assertSame('Semana 06/07', $plan['title']);
        $this->assertSame('2026-07-06', $plan['week_start']);
        $this->assertSame('2026-07-08', $this->itemDate($seedB['item']), 'Nem os posts podem ser deslocados de fora.');
    }

    public function test_update_sem_nada_para_gravar_nao_diz_que_salvou(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedPlan($agencyId);
        $this->actingAsEditor($agencyId);

        // Positivo primeiro: a rota funciona e confirma o que gravou.
        $this->post("/conteudo/{$seed['plan']}", ['_method' => 'PUT', 'title' => 'Semana 06/07 (v2)']);
        $this->assertSame('Semana 06/07 (v2)', $this->plan($seed['plan'])['title']);
        $this->assertNotEmpty($_SESSION['flash']['success'] ?? null);

        $_SESSION['flash'] = [];
        $this->post("/conteudo/{$seed['plan']}", ['_method' => 'PUT']);

        $this->assertArrayNotHasKey('success', $_SESSION['flash'] ?? []);
        $this->assertNotEmpty($_SESSION['flash']['error'] ?? null, 'Sem campo nenhum, a tela não pode dizer "atualizado".');
        $this->assertSame('Semana 06/07 (v2)', $this->plan($seed['plan'])['title']);
    }
}
