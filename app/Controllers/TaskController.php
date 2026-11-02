<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\ActivityLogger;
use App\Support\Auth;
use App\Repositories\TaskRepository;
use App\Repositories\ClientRepository;
use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use App\Services\ClickUpService;
use App\Services\NotificationService;

class TaskController extends Controller
{
    /** Colunas do kanban — e o único vocabulário aceito em `tasks.status`. */
    public const STATUSES = ['todo', 'in_progress', 'review', 'done'];

    /** Prioridades aceitas (a ordenação do repositório depende delas). */
    public const PRIORITIES = ['urgent', 'high', 'medium', 'low'];

    private const TITLE_MAX = 255;   // tamanho da coluna

    public function __construct(
        private readonly TaskRepository      $repo,
        private readonly ClientRepository    $clientRepo,
        private readonly UserRepository      $userRepo,
        private readonly NotificationService $notifications,
        private readonly ClickUpService      $clickup,
        private readonly JobRepository       $jobs,
    ) {}

    private function enqueueClickUp(int $taskId, int $agencyId, string $action): void
    {
        if (!$this->clickup->isConfigured($agencyId)) return;

        $this->jobs->enqueue($agencyId, 'clickup', [
            'job'  => \App\Jobs\ClickUpPushJob::class,
            'data' => ['task_id' => $taskId, 'agency_id' => $agencyId, 'action' => $action],
        ]);
    }

    // ------------------------------------------------------------------- index

    public function index(Request $request): Response
    {
        Auth::requirePermission('tasks.view');
        $agencyId = Auth::agencyId();

        $filters = [
            'status'      => $request->query('status', ''),
            'client_id'   => $request->query('client_id', ''),
            'assigned_to' => $request->query('assigned_to', ''),
            'priority'    => $request->query('priority', ''),
        ];

        $tasks   = $this->repo->listByAgency((int) $agencyId, array_filter($filters));
        $clients = $this->clientRepo->findByAgency($agencyId);
        $users   = $this->userRepo->findByAgency($agencyId);

        // Agrupar por status para o kanban. Duas correções do BUG-06:
        // 1. status fora do vocabulário (linha antiga ou status vindo do mapa do
        //    ClickUp) cai em "A fazer" em vez de desaparecer do quadro;
        // 2. os contadores saem desta lista — já filtrada —, não de um COUNT por
        //    agência: com filtro aplicado o cabeçalho e as colunas mostravam o
        //    total de todas as tarefas, sem relação com os cards na tela.
        $board  = array_fill_keys(self::STATUSES, []);
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($tasks as $t) {
            $col = in_array($t['status'], self::STATUSES, true) ? $t['status'] : self::STATUSES[0];
            $board[$col][] = $t;
            $counts[$col]++;
        }

        return $this->view('tasks.index', compact('tasks', 'board', 'counts', 'clients', 'users', 'filters'));
    }

    // ------------------------------------------------------------------ create

    public function create(Request $request): Response
    {
        Auth::requirePermission('tasks.create');
        $agencyId = Auth::agencyId();
        $clients  = $this->clientRepo->findByAgency($agencyId);
        $users    = $this->userRepo->findByAgency($agencyId);

        $prefill = [
            'client_id'   => $request->query('client_id', ''),
            'assigned_to' => $request->query('assigned_to', ''),
            'status'      => $request->query('status', 'todo'),
        ];

        return $this->view('tasks.create', compact('clients', 'users', 'prefill'));
    }

    /**
     * `client_id` e `assigned_to` chegam do formulário (SEC-13): sem conferir a
     * agência, a tarefa nasce com `agency_id` da sessão e cliente/responsável de
     * outra — e o kanban, o detalhe e o "Meu dia" passam a exibir o nome deles.
     * Devolve a mensagem do que não bate, ou null se está tudo na agência.
     */
    private function alvoForaDaAgencia(int $agencyId, ?int $clientId, ?int $assignedTo): ?string
    {
        if ($clientId !== null && !$this->clientRepo->findByIdAndAgency($clientId, $agencyId)) {
            return t('tasks.client_out_of_agency');
        }
        if ($assignedTo !== null && !$this->userRepo->findByIdAndAgency($assignedTo, $agencyId)) {
            return t('tasks.assignee_out_of_agency');
        }

        return null;
    }

    /**
     * Valida o que o formulário manda em texto livre (BUG-06). Sem isto um POST
     * com `status=foo` gravava: a tarefa saía do kanban (nenhuma das quatro
     * colunas a mostra) e o detalhe quebrava na tabela de rótulos; título vazio
     * criava card sem nome e `due_date` fora do formato ISO estourava o
     * Postgres com 500. Devolve a mensagem do primeiro problema, ou null.
     *
     * `$atual` é a linha que está sendo editada, quando houver: linha antiga
     * (mapa do ClickUp, POST anterior a esta validação) pode ter status ou
     * prioridade fora do vocabulário, e a tela de editar a mostra como opção
     * marcada. A edição pode **preservar** o valor que já está na linha — nunca
     * inventar um novo —, senão salvar só o título regravava calado a primeira
     * opção do `<select>` (prioridade virava "Urgente").
     *
     * @param array<string,mixed>|null $atual
     */
    private function campoInvalido(string $title, string $status, string $priority, string $dueDate, ?array $atual = null): ?string
    {
        if ($title === '') {
            return t('tasks.title_required');
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            return t('tasks.title_too_long');
        }
        if (!in_array($status, self::STATUSES, true) && $status !== ($atual['status'] ?? null)) {
            return t('tasks.status_invalid');
        }
        if (!in_array($priority, self::PRIORITIES, true) && $priority !== ($atual['priority'] ?? null)) {
            return t('tasks.priority_invalid');
        }
        // Aceita vazio (tarefa sem prazo) ou data real no formato do <input type="date">.
        if ($dueDate !== '') {
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
            if (!$data || $data->format('Y-m-d') !== $dueDate) {
                return t('tasks.due_date_invalid');
            }
        }

        return null;
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('tasks.create');

        $agencyId   = (int) Auth::agencyId();
        $clientId   = (int) $request->post('client_id', 0) ?: null;
        $assignedTo = (int) $request->post('assigned_to', 0) ?: null;
        $title      = trim((string) $request->post('title', ''));
        $status     = trim((string) $request->post('status', 'todo'));
        $priority   = trim((string) $request->post('priority', 'medium'));
        $dueDate    = trim((string) $request->post('due_date', ''));

        if ($erro = $this->campoInvalido($title, $status, $priority, $dueDate)) {
            $this->withError($erro);
            return $this->back();
        }

        if ($erro = $this->alvoForaDaAgencia($agencyId, $clientId, $assignedTo)) {
            $this->withError($erro);
            return $this->back();
        }

        $id = $this->repo->create([
            'agency_id'   => $agencyId,
            'created_by'  => Auth::id(),
            'client_id'   => $clientId,
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => trim((string) $request->post('description', '')),
            'status'      => $status,
            'priority'    => $priority,
            'due_date'    => $dueDate,
        ]);

        ActivityLogger::log('task.created', 'tasks', null, $clientId, [
            'task_id'     => $id,
            'title'       => $title,
            'status'      => $status,
            'priority'    => $priority,
            'assigned_to' => $assignedTo,
        ]);

        if ($assignedTo && $assignedTo !== (int) Auth::id()) {
            $this->notifications->notifyEvent('task.assigned', (int) Auth::agencyId(), [
                'task_id'     => $id,
                'task_title'  => $title,
                'assigned_to' => $assignedTo,
                'assigned_by' => Auth::user()['name'] ?? 'Alguém',
            ]);
        }

        $this->enqueueClickUp($id, (int) Auth::agencyId(), 'create');

        $this->withSuccess('Tarefa criada.');
        return $this->redirect('/tarefas/' . $id);
    }

    // -------------------------------------------------------------------- show

    public function show(Request $request): Response
    {
        Auth::requirePermission('tasks.view');
        $task = $this->repo->findByIdAndAgency((int) $request->param('id'), (int) Auth::agencyId());
        if (!$task) return Response::view('errors.404', [], 404);

        return $this->view('tasks.show', compact('task'));
    }

    // -------------------------------------------------------------------- edit

    /**
     * O seletor precisa conter o cliente da tarefa aberta, mesmo quando ele não
     * aparece na lista (cliente arquivado não entra em seletor). Sem a opção
     * dele nenhuma fica `selected`, o navegador marca a primeira — "— Sem
     * cliente —" — e salvar desfaz o vínculo, calado e sem volta pela interface.
     *
     * @param array<int,array<string,mixed>> $clients
     * @return array<int,array<string,mixed>>
     */
    private function withCurrentClient(array $clients, int $clientId, int $agencyId): array
    {
        if ($clientId <= 0) {
            return $clients;
        }

        foreach ($clients as $client) {
            if ((int) $client['id'] === $clientId) {
                return $clients;
            }
        }

        $current = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);

        return $current ? [...$clients, $current] : $clients;
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $agencyId = (int) Auth::agencyId();
        $task     = $this->repo->findByIdAndAgency((int) $request->param('id'), $agencyId);
        if (!$task) return Response::view('errors.404', [], 404);

        $clients = $this->withCurrentClient(
            $this->clientRepo->findByAgency($agencyId),
            (int) ($task['client_id'] ?? 0),
            $agencyId,
        );
        $users   = $this->userRepo->findByAgency($agencyId);

        return $this->view('tasks.edit', compact('task', 'clients', 'users'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $id       = (int) $request->param('id');
        $agencyId = (int) Auth::agencyId();

        $prevTask   = $this->repo->findByIdAndAgency($id, $agencyId);
        $clientId   = (int) $request->post('client_id', 0) ?: null;
        $assignedTo = (int) $request->post('assigned_to', 0) ?: null;
        $title      = trim((string) $request->post('title', ''));
        $status     = trim((string) $request->post('status', 'todo'));
        $priority   = trim((string) $request->post('priority', 'medium'));
        $dueDate    = trim((string) $request->post('due_date', ''));

        if ($erro = $this->campoInvalido($title, $status, $priority, $dueDate, $prevTask)) {
            $this->withError($erro);
            return $this->back();
        }

        if ($erro = $this->alvoForaDaAgencia($agencyId, $clientId, $assignedTo)) {
            $this->withError($erro);
            return $this->back();
        }

        $this->repo->updateTask($id, $agencyId, [
            'client_id'   => $clientId,
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => trim((string) $request->post('description', '')),
            'status'      => $status,
            'priority'    => $priority,
            'due_date'    => $dueDate,
        ]);

        // Mesma guarda de `updateStatus` e `destroy`: o UPDATE é escopado por
        // agência e é no-op quando a tarefa não existe (ou é de outra agência) —
        // registrar aí faria a trilha afirmar uma edição que o banco não recebeu,
        // com o título escolhido por quem chamou.
        if ($prevTask) {
            ActivityLogger::log('task.updated', 'tasks', null, $clientId, [
                'task_id'          => $id,
                'title'            => $title,
                'status_before'    => $prevTask['status'] ?? null,
                'status'           => $status,
                'assigned_before'  => $prevTask['assigned_to'] ?? null,
                'assigned_to'      => $assignedTo,
            ]);
        }

        // Notificar se a atribuição mudou e é para outra pessoa
        $prevAssigned = (int) ($prevTask['assigned_to'] ?? 0);
        if ($assignedTo && $assignedTo !== $prevAssigned && $assignedTo !== (int) Auth::id()) {
            $this->notifications->notifyEvent('task.assigned', $agencyId, [
                'task_id'     => $id,
                'task_title'  => $title,
                'assigned_to' => $assignedTo,
                'assigned_by' => Auth::user()['name'] ?? 'Alguém',
            ]);
        }

        $this->enqueueClickUp($id, $agencyId, 'update');

        $this->withSuccess('Tarefa atualizada.');
        return $this->redirect('/tarefas/' . $id);
    }

    // ------------------------------------------- status (arrastar ou form nativo)

    /**
     * Dois clientes para a mesma rota (BUG-06): o arrastar do kanban chama por
     * `fetch` e espera JSON, mas o menu "Mover para" do detalhe é um `<form>`
     * nativo — e nenhum JS troca esse submit por fetch (o ouvinte global de
     * `partials/form_loading.php` só bloqueia duplo envio). Respondendo JSON, o
     * navegador saía da tarefa e mostrava `{"success":true}` numa página em
     * branco (e a recusa chegava como `{"error":…}`). Quem não pede JSON volta
     * para o detalhe com a mensagem no flash.
     */
    public function updateStatus(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $id     = (int) $request->param('id');
        $status = $request->post('status', '');

        if (!in_array($status, self::STATUSES, true)) {
            if (!$request->wantsJson()) {
                $this->withError(t('tasks.status_invalid'));
                return $this->redirect('/tarefas/' . $id);
            }
            return Response::json(['error' => t('tasks.status_invalid')], 422);
        }

        $agencyId = (int) Auth::agencyId();
        // Lê antes de gravar só para a trilha (SEC-15): o UPDATE já é escopado
        // por agência e continua sendo um no-op quando a tarefa não existe —
        // mas aí também não há o que registrar.
        $prevTask = $this->repo->findByIdAndAgency($id, $agencyId);
        $this->repo->updateStatus($id, $agencyId, $status);
        $this->enqueueClickUp($id, $agencyId, 'update');

        if ($prevTask) {
            ActivityLogger::log('task.status_changed', 'tasks', null, (int) ($prevTask['client_id'] ?? 0) ?: null, [
                'task_id'       => $id,
                'status_before' => $prevTask['status'] ?? null,
                'status'        => $status,
            ]);
        }

        if (!$request->wantsJson()) {
            $this->withSuccess(t('tasks.status_updated'));
            return $this->redirect('/tarefas/' . $id);
        }

        return Response::json(['success' => true]);
    }

    // ----------------------------------------------------------------- destroy

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('tasks.delete');
        $id       = (int) $request->param('id');
        $agencyId = (int) Auth::agencyId();

        $task = $this->repo->findByIdAndAgency($id, $agencyId);
        $this->repo->deleteById($id, $agencyId);

        if ($task) {
            ActivityLogger::log('task.deleted', 'tasks', null, (int) ($task['client_id'] ?? 0) ?: null, [
                'task_id' => $id,
                'title'   => $task['title'] ?? null,
                'status'  => $task['status'] ?? null,
            ]);
        }

        $this->withSuccess('Tarefa removida.');
        return $this->redirect('/tarefas');
    }
}
