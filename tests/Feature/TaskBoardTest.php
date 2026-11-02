<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * BUG-06 — tarefas: validação do que o formulário manda e o quadro dizendo a
 * verdade.
 *
 * Três defeitos vivos: `status`, `priority` e `title` iam crus do POST para o
 * banco (um `status=foo` tirava a tarefa do kanban para sempre e quebrava o
 * detalhe com "Undefined array key"; título vazio criava card sem nome); e os
 * contadores das colunas vinham de um COUNT por agência, ignorando o filtro da
 * tela.
 *
 * Toda negativa tem a positiva da mesma rota ao lado: um "não gravou" só vale
 * quando o mesmo POST, com dado bom, grava.
 */
class TaskBoardTest extends FeatureTestCase
{
    private const PERMS = ['dashboard.view', 'tasks.view', 'tasks.create', 'tasks.edit'];

    private function login(int $agencyId, array $permissions = self::PERMS): int
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: $permissions);

        return $user['id'];
    }

    /** @return array<string,mixed> Campos válidos; o teste sobrescreve o que quer quebrar. */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title'       => 'Gravar o reels da semana',
            'description' => 'Roteiro aprovado',
            'status'      => 'todo',
            'priority'    => 'medium',
            'due_date'    => '',
            'client_id'   => '',
            'assigned_to' => '',
        ], $override);
    }

    private function criarTarefa(int $agencyId, int $createdBy, string $titulo, string $status = 'todo', string $priority = 'medium'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tasks (agency_id, created_by, title, status, priority, created_at, updated_at)
             VALUES (:a, :u, :t, :s, :p, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':u' => $createdBy, ':t' => $titulo, ':s' => $status, ':p' => $priority]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * POST como o arrastar do kanban faz: `fetch` com `X-Requested-With`, que é
     * o que separa o cliente de JSON do `<form>` nativo do detalhe.
     */
    private function postAjax(string $uri, array $data = []): \App\Core\Response
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        try {
            return $this->post($uri, $data);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
    }

    /** @return list<array<string,mixed>> */
    private function tarefas(int $agencyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE agency_id = :a ORDER BY id');
        $stmt->execute([':a' => $agencyId]);

        return $stmt->fetchAll();
    }

    // ── Criar: o que é aceito e o que é recusado ─────────────────────────────

    public function test_cria_tarefa_com_dados_validos(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $response = $this->post('/tarefas', $this->payload(['status' => 'review', 'priority' => 'urgent', 'due_date' => '2026-12-24']));

        $this->assertSame(302, $response->getStatus());
        $tarefas = $this->tarefas($agencyId);
        $this->assertCount(1, $tarefas);
        $this->assertSame('review', $tarefas[0]['status']);
        $this->assertSame('urgent', $tarefas[0]['priority']);
        $this->assertSame('2026-12-24', $tarefas[0]['due_date']);
    }

    public function test_status_fora_do_vocabulario_nao_grava(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $this->post('/tarefas', $this->payload(['status' => 'foo']));

        $this->assertSame([], $this->tarefas($agencyId), 'Gravou tarefa com status inválido — ela desaparece do kanban.');
        $this->assertSame(t('tasks.status_invalid'), $_SESSION['flash']['error'] ?? null);
    }

    public function test_prioridade_invalida_nao_grava(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $this->post('/tarefas', $this->payload(['priority' => 'altissima']));

        $this->assertSame([], $this->tarefas($agencyId));
        $this->assertSame(t('tasks.priority_invalid'), $_SESSION['flash']['error'] ?? null);
    }

    public function test_titulo_vazio_nao_grava(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $this->post('/tarefas', $this->payload(['title' => '   ']));

        $this->assertSame([], $this->tarefas($agencyId), 'Criou tarefa sem título: card em branco no quadro.');
        $this->assertSame(t('tasks.title_required'), $_SESSION['flash']['error'] ?? null);
    }

    public function test_titulo_maior_que_a_coluna_nao_grava(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        // Sem a validação, o Postgres estourava (VARCHAR(255)) e virava 500.
        $this->post('/tarefas', $this->payload(['title' => str_repeat('a', 256)]));

        $this->assertSame([], $this->tarefas($agencyId));
        $this->assertSame(t('tasks.title_too_long'), $_SESSION['flash']['error'] ?? null);
    }

    public function test_data_de_entrega_invalida_nao_grava(): void
    {
        $agencyId = $this->createAgency();
        $this->login($agencyId);

        $response = $this->post('/tarefas', $this->payload(['due_date' => '24/12/2026']));

        $this->assertSame(302, $response->getStatus(), 'Data inválida virou erro 500 em vez de aviso.');
        $this->assertSame([], $this->tarefas($agencyId));
        $this->assertSame(t('tasks.due_date_invalid'), $_SESSION['flash']['error'] ?? null);
    }

    // ── Editar: a mesma régua ────────────────────────────────────────────────

    public function test_edicao_aceita_status_valido(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Original');

        $response = $this->post("/tarefas/{$id}", $this->payload(['_method' => 'PUT', 'title' => 'Tarefa Renomeada', 'status' => 'done']));

        $this->assertSame(302, $response->getStatus());
        $tarefa = $this->tarefas($agencyId)[0];
        $this->assertSame('Tarefa Renomeada', $tarefa['title']);
        $this->assertSame('done', $tarefa['status']);
    }

    public function test_edicao_recusa_status_invalido_e_nao_toca_na_linha(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Original');

        $this->post("/tarefas/{$id}", $this->payload(['_method' => 'PUT', 'title' => 'Tarefa Renomeada', 'status' => 'arquivado']));

        $tarefa = $this->tarefas($agencyId)[0];
        $this->assertSame('todo', $tarefa['status']);
        $this->assertSame('Tarefa Original', $tarefa['title'], 'A edição recusada gravou o resto dos campos.');
        $this->assertSame(t('tasks.status_invalid'), $_SESSION['flash']['error'] ?? null);
    }

    public function test_edicao_recusa_titulo_vazio(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Original');

        $this->post("/tarefas/{$id}", $this->payload(['_method' => 'PUT', 'title' => '']));

        $this->assertSame('Tarefa Original', $this->tarefas($agencyId)[0]['title']);
        $this->assertSame(t('tasks.title_required'), $_SESSION['flash']['error'] ?? null);
    }

    // ── Mudança rápida de status (arrastar no kanban) ─────────────────────────

    public function test_arrastar_para_outra_coluna_grava_e_status_invalido_da_422(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Arrastada');

        $ok = $this->postAjax("/tarefas/{$id}/status", ['status' => 'in_progress']);
        $this->assertSame(200, $ok->getStatus());
        $this->assertStringContainsString('"success":true', $ok->getBody());
        $this->assertSame('in_progress', $this->tarefas($agencyId)[0]['status']);

        $erro = $this->postAjax("/tarefas/{$id}/status", ['status' => 'foo']);
        $this->assertSame(422, $erro->getStatus());
        $this->assertSame('in_progress', $this->tarefas($agencyId)[0]['status']);
    }

    /**
     * O menu "Mover para" do detalhe é um `<form method="POST">` e ninguém
     * intercepta o submit: a resposta JSON trocava a tela da tarefa por
     * `{"success":true}` numa página em branco.
     */
    public function test_mover_para_pelo_detalhe_volta_para_a_tarefa(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa do Detalhe');

        $response = $this->post("/tarefas/{$id}/status", ['status' => 'done']);

        $this->assertSame(302, $response->getStatus(), 'O form nativo recebeu JSON em vez de voltar para a tarefa.');
        $this->assertSame("/tarefas/{$id}", $response->getHeaders()['Location'] ?? null);
        $this->assertStringNotContainsString('"success"', $response->getBody());
        $this->assertSame('done', $this->tarefas($agencyId)[0]['status']);
        $this->assertSame(t('tasks.status_updated'), $_SESSION['flash']['success'] ?? null);
    }

    public function test_mover_para_com_status_invalido_avisa_na_tela(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa do Detalhe');

        $response = $this->post("/tarefas/{$id}/status", ['status' => 'foo']);

        $this->assertSame(302, $response->getStatus(), 'A recusa chegou como JSON cru numa página em branco.');
        $this->assertSame("/tarefas/{$id}", $response->getHeaders()['Location'] ?? null);
        $this->assertSame(t('tasks.status_invalid'), $_SESSION['flash']['error'] ?? null);
        $this->assertSame('todo', $this->tarefas($agencyId)[0]['status']);
    }

    // ── Contadores do quadro ─────────────────────────────────────────────────

    public function test_contadores_das_colunas_seguem_o_filtro(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);

        $this->criarTarefa($agencyId, $userId, 'Urgente A', 'todo', 'urgent');
        $this->criarTarefa($agencyId, $userId, 'Calma B',   'todo', 'low');
        $this->criarTarefa($agencyId, $userId, 'Calma C',   'done', 'low');

        $semFiltro = $this->get('/tarefas')->getBody();
        $this->assertStringContainsString('data-task-total="3"', $semFiltro);
        $this->assertStringContainsString('data-count="todo">2<', $semFiltro);
        $this->assertStringContainsString('data-count="done">1<', $semFiltro);

        $filtrado = $this->get('/tarefas?priority=urgent')->getBody();
        $this->assertStringContainsString('data-task-total="1"', $filtrado, 'O cabeçalho ainda conta as tarefas que o filtro escondeu.');
        $this->assertStringContainsString('data-count="todo">1<', $filtrado, 'A coluna "A fazer" ainda conta as tarefas que o filtro escondeu.');
        $this->assertStringContainsString('data-count="done">0<', $filtrado);
        $this->assertStringContainsString('Urgente A', $filtrado);
        $this->assertStringNotContainsString('Calma B', $filtrado);
    }

    public function test_tarefa_com_status_desconhecido_continua_visivel_no_quadro(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);

        // Linha antiga, ou status vindo do mapa do ClickUp: não passa mais pelo
        // formulário, mas quem já tem no banco não pode ficar invisível.
        $this->criarTarefa($agencyId, $userId, 'Tarefa Orfa', 'arquivado', 'medium');

        $body = $this->get('/tarefas')->getBody();

        $this->assertStringContainsString('Tarefa Orfa', $body, 'Tarefa com status fora das colunas desapareceu do kanban.');
        $this->assertStringContainsString('data-task-total="1"', $body);
        $this->assertStringContainsString('data-count="todo">1<', $body);
    }

    public function test_detalhe_abre_com_status_e_prioridade_desconhecidos(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Orfa', 'arquivado', 'altissima');

        $response = $this->get("/tarefas/{$id}");

        $this->assertSame(200, $response->getStatus());
        // Sem rótulo conhecido, mostra o valor cru em vez de um selo em branco.
        $this->assertStringContainsString('arquivado', $response->getBody());
        $this->assertStringContainsString('altissima', $response->getBody());
    }

    /**
     * O quadro blindou o STATUS desconhecido, mas o selo de PRIORIDADE seguia
     * lendo o rótulo cru: "Undefined array key" por card e selo sem texto.
     */
    public function test_quadro_abre_com_prioridade_desconhecida(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $this->criarTarefa($agencyId, $userId, 'Tarefa Orfa', 'todo', 'altissima');

        $body = $this->get('/tarefas')->getBody();

        $this->assertStringContainsString('Tarefa Orfa', $body);
        $this->assertStringContainsString('altissima', $body, 'O selo de prioridade do card saiu sem rótulo.');
        $this->assertStringNotContainsString('Undefined array key', $body);
    }

    /**
     * Tela de editar com valor fora do vocabulário: a opção dele existe e está
     * marcada, e salvar outro campo não regrava a primeira opção do `<select>`
     * (prioridade virava "Urgente", calada).
     */
    public function test_edicao_mantem_prioridade_fora_do_padrao(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Orfa', 'todo', 'altissima');

        $form = $this->get("/tarefas/{$id}/editar");
        $this->assertSame(200, $form->getStatus());
        $this->assertStringContainsString('value="altissima" selected', $form->getBody(), 'Nenhuma opção marcada: o navegador cai na primeira.');
        $this->assertStringContainsString(t('tasks.legacy_value', ['value' => 'altissima']), $form->getBody());

        $response = $this->post("/tarefas/{$id}", $this->payload([
            '_method'  => 'PUT',
            'title'    => 'Tarefa Renomeada',
            'priority' => 'altissima',
        ]));

        $this->assertSame(302, $response->getStatus());
        $tarefa = $this->tarefas($agencyId)[0];
        $this->assertSame('Tarefa Renomeada', $tarefa['title'], 'A edição legítima foi recusada.');
        $this->assertSame('altissima', $tarefa['priority'], 'Salvar o título regravou a prioridade.');
    }

    public function test_edicao_nao_inventa_prioridade_nova_fora_do_padrao(): void
    {
        $agencyId = $this->createAgency();
        $userId   = $this->login($agencyId);
        $id       = $this->criarTarefa($agencyId, $userId, 'Tarefa Orfa', 'todo', 'altissima');

        // A tolerância é só para o valor que já está na linha: outro valor fora
        // do vocabulário continua recusado.
        $this->post("/tarefas/{$id}", $this->payload([
            '_method'  => 'PUT',
            'title'    => 'Tarefa Renomeada',
            'priority' => 'inventada',
        ]));

        $tarefa = $this->tarefas($agencyId)[0];
        $this->assertSame('altissima', $tarefa['priority']);
        $this->assertSame('Tarefa Orfa', $tarefa['title']);
        $this->assertSame(t('tasks.priority_invalid'), $_SESSION['flash']['error'] ?? null);
    }
}
