<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Admin\SubscriptionPlanController;
use App\Core\HttpException;
use App\Core\Request;
use PDO;

/**
 * SEC-15 — trilha de auditoria onde faltava, pela HTTP real.
 *
 * `AuditTrailCoverageTest` (unitário) garante que a chamada ao `ActivityLogger`
 * não desapareça do código. Este prova o que o código estático não alcança: que
 * a linha **chega ao banco** com a agência, o cliente e o usuário certos — em
 * particular nos dois casos em que a sessão não responde de quem é a ação:
 *
 *   - **portal do cliente**: não há usuário logado; sem a agência explícita, a
 *     escrita no Drive cairia em `agency_id = 0` e sumiria da trilha da agência;
 *   - **admin da plataforma**: não tem `agency_id` próprio, e a ação é sobre um
 *     tenant — é na trilha dele que precisa aparecer.
 *
 * Cada negativa (nada gravado / 403) vem com a positiva ao lado: "não gravou"
 * só prova algo quando a mesma rota, feita certo, grava.
 */
class AuditTrailTest extends FeatureTestCase
{
    /** Permissões do administrador da agência para as rotas exercitadas aqui. */
    private const PERM = [
        'dashboard.view', 'clients.view', 'clients.view_all', 'clients.edit',
        'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.send',
        'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete',
        'payments.view', 'payments.create', 'payments.delete',
        'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete',
        'content.view', 'content.create', 'content.edit',
    ];

    /** Os 4 planos semeados pela migration — o que passar daí é sujeira deste teste. */
    private const SEEDED_PLANS = ['free', 'starter', 'pro', 'enterprise'];

    protected function tearDown(): void
    {
        // `subscription_plans` fica fora do TRUNCATE do FeatureTestCase (é
        // catálogo semeado). Sem esta limpeza o plano criado aqui sobreviveria e
        // a rodada seguinte bateria no índice único do slug.
        if (isset($this->pdo)) {
            $in = implode(',', array_fill(0, count(self::SEEDED_PLANS), '?'));
            $this->pdo->prepare("DELETE FROM subscription_plans WHERE slug NOT IN ({$in})")
                ->execute(self::SEEDED_PLANS);
        }

        parent::tearDown();
    }

    // ── Financeiro ───────────────────────────────────────────────────────────

    public function test_criar_fatura_grava_na_trilha(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();

        $this->post('/faturas', $this->dadosFatura($clientId));

        $log = $this->umLog('invoice.created');
        $this->assertSame($agencyId, (int) $log['agency_id']);
        $this->assertSame($clientId, (int) $log['client_id']);
        $this->assertSame($userId,   (int) $log['user_id']);
        $this->assertSame('finance', $log['module']);

        $meta = json_decode((string) $log['metadata_json'], true);
        $this->assertGreaterThan(0, (int) $meta['invoice_id']);
    }

    /**
     * A negativa do mesmo fluxo: fatura recusada (desconto maior que o
     * subtotal) não pode deixar linha na trilha — senão a auditoria passa a
     * registrar o que nunca existiu.
     */
    public function test_fatura_recusada_nao_grava_na_trilha(): void
    {
        [, , $clientId] = $this->agencyComUsuarioECliente();

        $this->post('/faturas', $this->dadosFatura($clientId, ['discount' => '999.999,00']));

        $this->assertSame(0, $this->contarLogs('invoice.created'));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
    }

    public function test_excluir_fatura_grava_na_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();
        $fatura = $this->criarFatura($agencyId, $clientId, 'FAT-9001');

        $this->post("/faturas/{$fatura}", ['_method' => 'DELETE']);

        $log = $this->umLog('invoice.deleted');
        $this->assertSame($clientId, (int) $log['client_id']);
        $this->assertSame($fatura, (int) json_decode((string) $log['metadata_json'], true)['invoice_id']);
    }

    public function test_pagamento_registrado_e_removido_gravam_na_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();
        $fatura = $this->criarFatura($agencyId, $clientId, 'FAT-9002');

        $this->post('/pagamentos', [
            'invoice_id'     => (string) $fatura,
            'amount'         => '250,00',
            'payment_method' => 'pix',
            'payment_date'   => date('Y-m-d'),
        ]);

        $registro = $this->umLog('payment.recorded');
        $this->assertSame($clientId, (int) $registro['client_id']);
        $meta = json_decode((string) $registro['metadata_json'], true);
        $this->assertSame(250.0, (float) $meta['amount']);
        $this->assertSame('pix', $meta['payment_method']);

        $pagamentoId = (int) $meta['payment_id'];
        $this->post("/pagamentos/{$pagamentoId}", ['_method' => 'DELETE']);

        $remocao = $this->umLog('payment.deleted');
        $this->assertSame($pagamentoId, (int) json_decode((string) $remocao['metadata_json'], true)['payment_id']);
    }

    /** Pagamento recusado (acima do saldo) não entra na trilha. */
    public function test_pagamento_recusado_nao_grava_na_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();
        $fatura = $this->criarFatura($agencyId, $clientId, 'FAT-9003', '100.00');

        $this->post('/pagamentos', [
            'invoice_id'     => (string) $fatura,
            'amount'         => '5.000,00',
            'payment_method' => 'pix',
            'payment_date'   => date('Y-m-d'),
        ]);

        $this->assertSame(0, $this->contarLogs('payment.recorded'));
    }

    public function test_contrato_criado_e_excluido_gravam_na_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();

        $this->post('/contratos', [
            'client_id'     => (string) $clientId,
            'title'         => 'Contrato mensal',
            'description'   => '',
            'value'         => '1.500,00',
            'currency_code' => 'BRL',
            'status'        => 'active',
            'start_date'    => date('Y-m-d'),
            'end_date'      => '',
            'signed_at'     => '',
            'recurrence'    => 'monthly',
            'notes'         => '',
        ]);

        $criacao = $this->umLog('contract.created');
        $this->assertSame($clientId, (int) $criacao['client_id']);
        $contratoId = (int) json_decode((string) $criacao['metadata_json'], true)['contract_id'];
        $this->assertGreaterThan(0, $contratoId);

        $this->post("/contratos/{$contratoId}", ['_method' => 'DELETE']);

        $exclusao = $this->umLog('contract.deleted');
        $this->assertSame($contratoId, (int) json_decode((string) $exclusao['metadata_json'], true)['contract_id']);
    }

    // ── Tarefas ──────────────────────────────────────────────────────────────

    public function test_tarefa_criada_movida_e_excluida_gravam_na_trilha(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();

        $this->post('/tarefas', [
            'client_id' => (string) $clientId,
            'title'     => 'Gravar o vídeo da semana',
            'status'    => 'todo',
            'priority'  => 'high',
            'due_date'  => '',
        ]);

        $criacao = $this->umLog('task.created');
        $this->assertSame($agencyId, (int) $criacao['agency_id']);
        $this->assertSame($clientId, (int) $criacao['client_id']);
        $this->assertSame($userId,   (int) $criacao['user_id']);
        $tarefaId = (int) json_decode((string) $criacao['metadata_json'], true)['task_id'];

        $this->post("/tarefas/{$tarefaId}/status", ['status' => 'done']);

        $mudanca = json_decode((string) $this->umLog('task.status_changed')['metadata_json'], true);
        $this->assertSame('todo', $mudanca['status_before']);
        $this->assertSame('done', $mudanca['status']);

        $this->post("/tarefas/{$tarefaId}", ['_method' => 'DELETE']);

        $exclusao = json_decode((string) $this->umLog('task.deleted')['metadata_json'], true);
        $this->assertSame('Gravar o vídeo da semana', $exclusao['title']);
    }

    /**
     * Status inválido é recusado pelo controller (BUG-06) — e não pode virar
     * linha de "mudou o status" na trilha.
     */
    public function test_status_invalido_nao_grava_na_trilha(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();
        $tarefaId = $this->criarTarefa($agencyId, $clientId, $userId);

        $this->post("/tarefas/{$tarefaId}/status", ['status' => 'inventado']);

        $this->assertSame(0, $this->contarLogs('task.status_changed'));
    }

    /** Tarefa de outra agência: o 404 não pode deixar rastro de exclusão. */
    public function test_tarefa_de_outra_agencia_nao_grava_exclusao(): void
    {
        $this->agencyComUsuarioECliente();

        $outraAgencia = $this->createAgency('Agência B');
        $clienteB     = $this->createClient($outraAgencia, 'Cliente da B');
        $userB        = $this->createUser($outraAgencia, 'b@test.com');
        $tarefaDaB    = $this->criarTarefa($outraAgencia, (int) $clienteB['id'], $userB['id']);

        $this->post("/tarefas/{$tarefaDaB}", ['_method' => 'DELETE']);

        $this->assertSame(0, $this->contarLogs('task.deleted'));
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $tarefaDaB]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /** A positiva da edição: salvar a própria tarefa grava o antes e o depois. */
    public function test_editar_tarefa_grava_na_trilha(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();
        $tarefaId = $this->criarTarefa($agencyId, $clientId, $userId);

        $this->post("/tarefas/{$tarefaId}", [
            '_method'   => 'PUT',
            'client_id' => (string) $clientId,
            'title'     => 'Tarefa renomeada',
            'status'    => 'in_progress',
            'priority'  => 'medium',
            'due_date'  => '',
        ]);

        $meta = json_decode((string) $this->umLog('task.updated')['metadata_json'], true);
        $this->assertSame($tarefaId, (int) $meta['task_id']);
        $this->assertSame('todo',        $meta['status_before']);
        $this->assertSame('in_progress', $meta['status']);
        $this->assertSame('Tarefa renomeada', (string) $this->pdo->query("SELECT title FROM tasks WHERE id = {$tarefaId}")->fetchColumn());
    }

    /**
     * A negativa que faltava: o PUT em tarefa de outra agência é um no-op (o
     * UPDATE é escopado por `agency_id`) — e um no-op não pode virar linha de
     * "tarefa editada", com título escolhido por quem chamou.
     */
    public function test_tarefa_de_outra_agencia_nao_grava_edicao(): void
    {
        $this->agencyComUsuarioECliente();

        $outraAgencia = $this->createAgency('Agência B');
        $clienteB     = $this->createClient($outraAgencia, 'Cliente da B');
        $userB        = $this->createUser($outraAgencia, 'b@test.com');
        $tarefaDaB    = $this->criarTarefa($outraAgencia, (int) $clienteB['id'], $userB['id']);

        $this->post("/tarefas/{$tarefaDaB}", [
            '_method'  => 'PUT',
            'title'    => 'Reunião cancelada',
            'status'   => 'done',
            'priority' => 'medium',
            'due_date' => '',
        ]);

        $this->assertSame(0, $this->contarLogs('task.updated'));

        $stmt = $this->pdo->prepare('SELECT title, status FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $tarefaDaB]);
        $this->assertSame(['title' => 'Tarefa', 'status' => 'todo'], $stmt->fetch(PDO::FETCH_ASSOC));
    }

    // ── Portal do cliente (sem usuário de sessão) ───────────────────────────

    /**
     * O caso que justifica o parâmetro `$agencyId` do `ActivityLogger`: a
     * escrita vem do portal, onde `Auth::agencyId()` é nulo. Mover um arquivo
     * que já está na pasta de destino é "movido" sem nenhuma chamada ao Google —
     * é por aí que o fluxo é exercitado de verdade.
     */
    public function test_escrita_do_portal_grava_cliente_agencia_e_ip(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $pasta    = $this->criarPasta($agencyId, (int) $client['id'], 'Setembro');
        $arquivo  = $this->criarArquivo($agencyId, (int) $client['id'], $pasta, 'reels.mp4');

        $resp = $this->post("/portal/{$client['portal_token']}/drive/move", [
            'file_ids'         => [$arquivo],
            'target_folder_id' => $pasta,
        ]);

        $this->assertSame(200, $resp->getStatus());

        $log = $this->umLog('drive.items_moved');
        $this->assertSame($agencyId, (int) $log['agency_id'], 'A linha do portal tem de cair na agência dona do cliente, não na 0.');
        $this->assertSame((int) $client['id'], (int) $log['client_id']);
        $this->assertNull($log['user_id'], 'O portal não tem usuário de sessão.');
        $this->assertSame('127.0.0.1', $log['ip_address']);
        $this->assertSame('portal', json_decode((string) $log['metadata_json'], true)['source']);
    }

    /** Token do portal inválido não escreve nada — nem na trilha. */
    public function test_portal_com_token_invalido_nao_grava_na_trilha(): void
    {
        $this->post('/portal/token-que-nao-existe/drive/move', ['file_ids' => [1]]);

        $this->assertSame(0, $this->contarLogs('drive.items_moved'));
    }

    /**
     * A ação de portal mais relevante do produto: a cliente aprova a semana.
     * Sem a agência explícita a linha nasce em `agency_id = 0` (não há sessão) e
     * a aprovação desaparece da trilha da agência dona — enquanto a escrita do
     * MESMO cliente no Drive, pelo mesmo portal, cai na agência certa.
     */
    public function test_aprovacao_do_plano_pelo_portal_cai_na_agencia_dona(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $plano    = $this->criarPlanoEnviado($agencyId, (int) $client['id']);

        $resp = $this->post("/portal/{$client['portal_token']}/planos/{$plano}/aprovar");
        $this->assertSame(302, $resp->getStatus());

        $log = $this->umLog('content_plan.approved');
        $this->assertSame($agencyId, (int) $log['agency_id'], 'A aprovação do portal tem de cair na agência dona, não na 0.');
        $this->assertSame((int) $client['id'], (int) $log['client_id']);
        $this->assertNull($log['user_id'], 'O portal não tem usuário de sessão.');
        $this->assertSame($plano, (int) json_decode((string) $log['metadata_json'], true)['plan_id']);
    }

    /** Mesma régua para "pedir alteração" e para o feedback post a post. */
    public function test_revisao_e_feedback_do_portal_caem_na_agencia_dona(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $plano    = $this->criarPlanoEnviado($agencyId, (int) $client['id']);
        $item     = $this->criarItemDePlano($plano, (int) $client['id']);
        $token    = $client['portal_token'];

        $resp = $this->post("/portal/{$token}/planos/{$plano}/items/{$item}/feedback", [
            'feedback_type' => 'comment',
            'comment'       => 'Trocar a capa de quarta.',
        ]);
        $this->assertSame(200, $resp->getStatus());

        $feedback = $this->umLog('approval.feedback_added');
        $this->assertSame($agencyId, (int) $feedback['agency_id']);
        $this->assertSame((int) $client['id'], (int) $feedback['client_id']);
        $this->assertSame($item, (int) json_decode((string) $feedback['metadata_json'], true)['item_id']);

        $resp = $this->post("/portal/{$token}/planos/{$plano}/revisao", ['comment' => 'Refazer a capa.']);
        $this->assertSame(302, $resp->getStatus());

        $revisao = $this->umLog('content_plan.revision_requested');
        $this->assertSame($agencyId, (int) $revisao['agency_id']);
        $this->assertSame((int) $client['id'], (int) $revisao['client_id']);
    }

    /**
     * A aprovação da cliente pode disparar a automação que cria o rascunho da
     * semana seguinte — e essas linhas nascem no MESMO request, no mesmo lugar
     * sem sessão. Sem a agência explícita, "quem criou o plano da semana que
     * vem?" caía em `agency_id = 0` enquanto a aprovação que o disparou caía na
     * agência certa: trilha partida dentro de um clique.
     */
    public function test_plano_criado_pela_automacao_do_portal_cai_na_agencia_dona(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $gestor   = $this->createUser($agencyId, 'gestor@test.com');
        // `created_by` preenchido de propósito: é o gestor que a trilha credita
        // por engano quando a cópia nasce do clique da cliente.
        $plano = $this->criarPlanoEnviado($agencyId, (int) $client['id'], $gestor['id']);
        $this->criarItemDePlano($plano, (int) $client['id']);
        $this->ligarAutomacaoDeProximoPlano($agencyId, (int) $client['id']);

        $resp = $this->post("/portal/{$client['portal_token']}/planos/{$plano}/aprovar");
        $this->assertSame(302, $resp->getStatus());

        $novo = (int) $this->pdo->query(
            "SELECT id FROM content_plans WHERE id <> {$plano} ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $this->assertGreaterThan(0, $novo, 'A automação tem de ter criado o plano da semana seguinte.');

        $auto = $this->umLog('content_plan.auto_created');
        $this->assertSame($agencyId, (int) $auto['agency_id'], 'O plano nascido da aprovação tem de cair na agência dona, não na 0.');
        $this->assertSame((int) $client['id'], (int) $auto['client_id']);
        $this->assertNull($auto['user_id'], 'O portal não tem usuário de sessão.');
        $this->assertSame($novo, (int) json_decode((string) $auto['metadata_json'], true)['new_plan_id']);

        // Sem modelo semanal do cliente a cópia passa pelo `duplicate`, que
        // grava a segunda linha do mesmo request — também na agência 0 e, pior,
        // no nome do gestor que criou o plano de origem.
        $copia = $this->umLog('content_plan.duplicated');
        $this->assertSame($agencyId, (int) $copia['agency_id']);
        $this->assertSame((int) $client['id'], (int) $copia['client_id']);
        $this->assertNull($copia['user_id'], 'Quem disparou foi a cliente: creditar o gestor é afirmar o que ele não fez.');
        $this->assertTrue(json_decode((string) $copia['metadata_json'], true)['automated']);
    }

    /**
     * A positiva do mesmo log pelo painel: ali existe sessão e a cópia é mesmo
     * de quem clicou — `user_id` nulo aqui seria perder o autor.
     */
    public function test_duplicar_pelo_painel_credita_o_usuario_da_sessao(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();
        $plano = $this->criarPlanoEnviado($agencyId, $clientId);

        $resp = $this->post("/conteudo/{$plano}/duplicar");
        $this->assertSame(302, $resp->getStatus());

        $log = $this->umLog('content_plan.duplicated');
        $this->assertSame($agencyId, (int) $log['agency_id']);
        $this->assertSame($clientId, (int) $log['client_id']);
        $this->assertSame($userId,   (int) $log['user_id']);
        $this->assertFalse(json_decode((string) $log['metadata_json'], true)['automated']);
    }

    /** Ligar e desligar o portal pela ficha do cliente também deixa rastro. */
    public function test_ligar_e_desligar_o_portal_na_ficha_grava_na_trilha(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Teste', portal: false);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM, clientIds: [(int) $client['id']]);

        // O cliente nasce sem token nesta fábrica: marcar o checkbox gera o link.
        $this->pdo->prepare('UPDATE clients SET portal_token = NULL WHERE id = :id')
            ->execute([':id' => $client['id']]);

        $base = ['_method' => 'PUT', 'name' => 'Cliente Teste'];

        $this->post('/clientes/' . $client['id'], $base + ['enable_portal' => '1']);
        $this->assertSame(1, $this->contarLogs('portal_access_granted'));

        $this->post('/clientes/' . $client['id'], $base);
        $this->assertSame(1, $this->contarLogs('portal_access_revoked'));

        // Salvar de novo sem mexer no portal não repete a linha (o checkbox vem
        // em todo salvamento; só a mudança é auditável).
        $this->post('/clientes/' . $client['id'], $base);
        $this->assertSame(1, $this->contarLogs('portal_access_revoked'));
    }

    // ── Drive pelo painel ───────────────────────────────────────────────────

    /**
     * `drive_files` não tem coluna de autor (só `uploaded_via`): quem responde
     * "quem subiu esse arquivo?" é a trilha. O envio pelo portal já gravava; o
     * da equipe, não — e a mesma pergunta ficava sem resposta justamente quando
     * a origem era a agência.
     */
    public function test_upload_pelo_painel_grava_na_trilha(): void
    {
        [$agencyId, $userId, $clientId] = $this->agencyComUsuarioECliente();
        $pasta = $this->criarPasta($agencyId, $clientId, 'Setembro');

        $driveFolderId = (string) $this->pdo->query("SELECT drive_folder_id FROM drive_folders WHERE id = {$pasta}")->fetchColumn();
        $this->container->instance(
            \App\Services\GoogleDriveApiService::class,
            new FakeUploadDriveApi($driveFolderId),
        );

        $resp = $this->post("/clientes/{$clientId}/conteudos/upload/complete", [
            'drive_file_id' => 'DRIVE-FILE-1',
            'folder_id'     => (string) $pasta,
        ]);

        $this->assertSame(200, $resp->getStatus(), $resp->getBody());

        $log = $this->umLog('drive.file_uploaded');
        $this->assertSame($agencyId, (int) $log['agency_id']);
        $this->assertSame($clientId, (int) $log['client_id']);
        $this->assertSame($userId,   (int) $log['user_id'], 'No painel a autoria é o usuário logado.');
        $meta = json_decode((string) $log['metadata_json'], true);
        $this->assertSame($pasta, (int) $meta['folder_id']);
        $this->assertSame('reels.mp4', $meta['name']);
    }

    /** A negativa: arquivo que o Drive não confirma não entra na trilha. */
    public function test_upload_nao_confirmado_no_drive_nao_grava_na_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();
        $pasta = $this->criarPasta($agencyId, $clientId, 'Setembro');

        // O fake responde metadados de OUTRA pasta: o `completeDirect` recusa.
        $this->container->instance(
            \App\Services\GoogleDriveApiService::class,
            new FakeUploadDriveApi('pasta-que-nao-e-a-do-cliente'),
        );

        $resp = $this->post("/clientes/{$clientId}/conteudos/upload/complete", [
            'drive_file_id' => 'DRIVE-FILE-1',
            'folder_id'     => (string) $pasta,
        ]);

        $this->assertSame(422, $resp->getStatus());
        $this->assertSame(0, $this->contarLogs('drive.file_uploaded'));
    }

    // ── Bytes inválidos no que o cliente manda ──────────────────────────────

    /**
     * O `ActivityLogger` engole exceção de propósito (log não pode derrubar a
     * ação) — então um header fora de UTF-8 fazia o Postgres recusar o INSERT
     * (SQLSTATE 22021) e a ação acontecia **sem nenhuma linha** na trilha. Quem
     * quisesse agir sem rastro só precisava escolher o próprio User-Agent.
     */
    public function test_user_agent_fora_de_utf8_nao_apaga_a_linha_da_trilha(): void
    {
        [$agencyId, , $clientId] = $this->agencyComUsuarioECliente();
        $fatura = $this->criarFatura($agencyId, $clientId, 'FAT-9010');

        $_SERVER['HTTP_USER_AGENT'] = "Mozilla/5.0 \xC3\x28 (sem rastro)";

        try {
            $this->post("/faturas/{$fatura}", ['_method' => 'DELETE']);
        } finally {
            unset($_SERVER['HTTP_USER_AGENT']);
        }

        $log = $this->umLog('invoice.deleted');
        $this->assertSame($clientId, (int) $log['client_id']);
        $this->assertSame($fatura, (int) json_decode((string) $log['metadata_json'], true)['invoice_id']);
        $this->assertTrue(
            mb_check_encoding((string) $log['user_agent'], 'UTF-8'),
            'O user_agent gravado tem de ser UTF-8 válido.',
        );
    }

    /**
     * O mesmo pelo `metadata_json`: nome de arquivo ou título fora de UTF-8 não
     * pode apagar a linha. Chamada direta porque todo caminho de HTTP grava o
     * texto numa coluna antes de logar — o INSERT da ação já estouraria lá, e aí
     * não há ação para auditar.
     */
    public function test_metadata_fora_de_utf8_nao_apaga_a_linha_da_trilha(): void
    {
        $agencyId = $this->createAgency();

        \App\Support\ActivityLogger::log(
            'drive.file_uploaded',
            'drive',
            null,
            null,
            ['name' => "capa \xC3\x28.jpg"],
            $agencyId,
        );

        $log = $this->umLog('drive.file_uploaded');
        $this->assertSame($agencyId, (int) $log['agency_id']);
        $meta = json_decode((string) $log['metadata_json'], true);
        $this->assertIsArray($meta, 'O metadata gravado tem de continuar sendo JSON legível.');
        $this->assertArrayHasKey('name', $meta);
    }

    // ── Admin da plataforma ─────────────────────────────────────────────────

    /**
     * O admin da plataforma não tem `agency_id`: a troca de plano tem de cair na
     * trilha do **tenant**, senão a linha vai para a agência 0 e o dono do
     * tenant nunca vê que o plano dele mudou.
     */
    public function test_troca_de_assinatura_entra_na_trilha_do_tenant(): void
    {
        $tenantId = $this->createAgency('Tenant do Cliente');
        $admin    = $this->createUser($this->createAgency('Plataforma'), 'root@test.com');
        $this->comoAdminDaPlataforma($admin['id']);

        $planoId = (int) $this->pdo->query("SELECT id FROM subscription_plans WHERE slug = 'pro'")->fetchColumn();

        $resp = $this->post("/admin/assinaturas/{$tenantId}", [
            'plan_id'       => (string) $planoId,
            'billing_cycle' => 'monthly',
            'status'        => 'active',
        ]);

        $this->assertSame(302, $resp->getStatus());

        $log = $this->umLog('admin.subscription_changed');
        $this->assertSame($tenantId, (int) $log['agency_id']);
        $this->assertSame($admin['id'], (int) $log['user_id']);
        $this->assertSame($planoId, (int) json_decode((string) $log['metadata_json'], true)['plan_id']);
    }

    public function test_plano_criado_pelo_admin_entra_na_trilha(): void
    {
        $admin = $this->createUser($this->createAgency('Plataforma'), 'root@test.com');
        $this->comoAdminDaPlataforma($admin['id']);

        // `subscription_plans` é catálogo da plataforma (semeado por migration)
        // e **não** entra no TRUNCATE do FeatureTestCase: o slug precisa ser
        // único por execução, e o plano sai no tearDown — senão a segunda
        // rodada da suíte bateria no índice único e o teste mentiria.
        $slug = 'plano-trilha-' . bin2hex(random_bytes(4));

        $this->post('/admin/planos', [
            'name'          => 'Plano Teste',
            'slug'          => $slug,
            'description'   => '',
            'price_monthly' => '199.90',
            'price_yearly'  => '1999.00',
            'features'      => "Suporte\nRelatórios",
            'is_active'     => '1',
            'sort_order'    => '9',
        ]);

        $meta = json_decode((string) $this->umLog('admin.plan_created')['metadata_json'], true);
        $this->assertSame($slug, $meta['slug']);
        $this->assertSame(199.9, (float) $meta['price_monthly']);
    }

    /**
     * SEC-15: `SubscriptionPlanController` era o único controller de `/admin`
     * sem guarda no método — dependia só do grupo em `routes/web.php`.
     */
    public function test_planos_da_plataforma_negados_a_usuario_de_tenant(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->assertSame(403, $this->get('/admin/planos')->getStatus());
        $this->assertSame(403, $this->get('/admin/assinaturas')->getStatus());
    }

    /** A positiva: com o admin da plataforma a mesma rota abre. */
    public function test_planos_da_plataforma_abrem_para_o_admin(): void
    {
        $admin = $this->createUser($this->createAgency('Plataforma'), 'root@test.com');
        $this->comoAdminDaPlataforma($admin['id']);

        $this->assertSame(200, $this->get('/admin/planos')->getStatus());
        $this->assertSame(200, $this->get('/admin/assinaturas')->getStatus());
    }

    /**
     * A guarda vale **no método**, não só no grupo de rotas: chamado direto (uma
     * rota registrada fora do grupo, por exemplo), continua recusando.
     */
    public function test_a_guarda_do_metodo_recusa_sem_o_middleware(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        /** @var SubscriptionPlanController $controller */
        $controller = $this->container->make(SubscriptionPlanController::class);

        try {
            $controller->plans(Request::fromGlobals());
            $this->fail('O método devia recusar usuário de tenant mesmo sem o PlatformAdminMiddleware.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getResponse()->getStatus());
        }

        // Positiva do mesmo caminho: com o admin da plataforma o método responde.
        $this->comoAdminDaPlataforma($user['id']);
        $this->assertSame(200, $controller->plans(Request::fromGlobals())->getStatus());
    }

    // ── Fábricas e consultas ────────────────────────────────────────────────

    /** @return array{0:int,1:int,2:int} agência, usuário logado, cliente */
    private function agencyComUsuarioECliente(): array
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM, clientIds: [(int) $client['id']]);

        return [$agencyId, $user['id'], (int) $client['id']];
    }

    private function comoAdminDaPlataforma(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        $_SESSION['user']              = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user']['agency_id'] = null;   // o admin da plataforma não tem agência
        $_SESSION['permissions']       = [];
        $_SESSION['client_ids']        = [];
        $_SESSION['is_platform_admin'] = true;
    }

    /** @return array<string,mixed> a única linha esperada para a ação */
    private function umLog(string $action): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM activity_logs WHERE action = :a ORDER BY id'
        );
        $stmt->execute([':a' => $action]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows, "Esperava exatamente uma linha de \"{$action}\" em activity_logs.");

        return $rows[0];
    }

    private function contarLogs(string $action): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE action = :a');
        $stmt->execute([':a' => $action]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,string> */
    private function dadosFatura(int $clientId, array $extra = []): array
    {
        return array_merge([
            'client_id'     => (string) $clientId,
            'contract_id'   => '',
            'title'         => 'Serviços do mês',
            'status'        => 'draft',
            'due_date'      => date('Y-m-d', strtotime('+10 days')),
            'currency_code' => 'BRL',
            'discount'      => '0',
            'tax'           => '0',
            'notes'         => '',
            'items'         => [
                ['description' => 'Gestão de redes', 'quantity' => '1', 'unit_price' => '1.000,00'],
            ],
        ], $extra);
    }

    private function criarFatura(int $agencyId, int $clientId, string $number, string $total = '1000.00'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, due_date,
                                   subtotal, total, amount_paid, currency_code, created_at, updated_at)
             VALUES (:a, :c, :n, 'Serviços do mês', 'sent', CURRENT_DATE + 10, :t, :t2, 0, 'BRL', NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':n' => $number, ':t' => $total, ':t2' => $total]);

        return (int) $stmt->fetchColumn();
    }

    private function criarTarefa(int $agencyId, int $clientId, int $createdBy, string $status = 'todo'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tasks (agency_id, client_id, created_by, title, status, priority, created_at, updated_at)
             VALUES (:a, :c, :u, 'Tarefa', :s, 'medium', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':u' => $createdBy, ':s' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /** Plano já enviado à cliente — o que o portal pode aprovar ou devolver. */
    private function criarPlanoEnviado(int $agencyId, int $clientId, ?int $createdBy = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_by, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', 'sent', NOW(), :u, NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':u' => $createdBy]);

        return (int) $stmt->fetchColumn();
    }

    /** Regra da agência + opt-in do cliente: o que a automação exige para rodar. */
    private function ligarAutomacaoDeProximoPlano(int $agencyId, int $clientId): void
    {
        $this->pdo->prepare(
            "INSERT INTO automation_rules (agency_id, automation_key, status)
             VALUES (:a, 'content.approved_create_next_plan', 'active')"
        )->execute([':a' => $agencyId]);

        $this->pdo->prepare(
            "INSERT INTO client_automation_settings (agency_id, client_id, automation_key, enabled)
             VALUES (:a, :c, 'content.approved_create_next_plan', TRUE)"
        )->execute([':a' => $agencyId, ':c' => $clientId]);
    }

    private function criarItemDePlano(int $planId, int $clientId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plan_items
                (content_plan_id, client_id, publish_date, platform, content_type, title, status, sort_order, created_at)
             VALUES (:p, :c, '2026-07-08', 'instagram', 'Feed', 'Post de quarta', 'draft', 0, NOW())
             RETURNING id"
        );
        $stmt->execute([':p' => $planId, ':c' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    private function criarPasta(int $agencyId, int $clientId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_folders (agency_id, client_id, parent_id, drive_folder_id, name, created_at)
             VALUES (:a, :c, NULL, :d, :n, NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a' => $agencyId, ':c' => $clientId,
            ':d' => 'drive-folder-' . bin2hex(random_bytes(6)), ':n' => $name,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function criarArquivo(int $agencyId, int $clientId, ?int $folderId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_files (agency_id, client_id, folder_id, drive_file_id, name, mime_type, uploaded_via, created_at)
             VALUES (:a, :c, :f, :d, :n, 'video/mp4', 'portal', NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a' => $agencyId, ':c' => $clientId, ':f' => $folderId,
            ':d' => 'drive-file-' . bin2hex(random_bytes(6)), ':n' => $name,
        ]);

        return (int) $stmt->fetchColumn();
    }
}

/**
 * Dublê do Google Drive para o upload direto: confirma o arquivo na pasta
 * informada no construtor, sem rede. Pasta diferente = arquivo não confirmado,
 * que é o caso da negativa.
 */
final class FakeUploadDriveApi extends \App\Services\GoogleDriveApiService
{
    public function __construct(private readonly string $parentDriveId) {}

    public function isConnected(int $agencyId): bool
    {
        return true;
    }

    public function fileMeta(int $agencyId, string $fileId): array
    {
        return [
            'id'            => $fileId,
            'name'          => 'reels.mp4',
            'mimeType'      => 'video/mp4',
            'size'          => 1024,
            'parents'       => [$this->parentDriveId],
            'thumbnailLink' => 'https://drive.example/thumb',
            'webViewLink'   => 'https://drive.example/view',
        ];
    }

    public function setAnyoneReader(int $agencyId, string $fileId): void
    {
        // o dublê não fala com o Google
    }
}
