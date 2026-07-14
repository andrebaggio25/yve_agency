<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\TaskRepository;
use App\Repositories\ClientRepository;
use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use App\Services\ClickUpService;
use App\Services\NotificationService;

class TaskController extends Controller
{
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
        $counts  = $this->repo->countByStatus((int) $agencyId);
        $clients = $this->clientRepo->findByAgency($agencyId);
        $users   = $this->userRepo->findByAgency($agencyId);

        // Agrupar por status para o kanban
        $board = ['todo' => [], 'in_progress' => [], 'review' => [], 'done' => []];
        foreach ($tasks as $t) {
            $board[$t['status']][] = $t;
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

    public function store(Request $request): Response
    {
        Auth::requirePermission('tasks.create');

        $assignedTo = (int) $request->post('assigned_to', 0) ?: null;
        $title      = trim((string) $request->post('title', ''));

        $id = $this->repo->create([
            'agency_id'   => Auth::agencyId(),
            'created_by'  => Auth::id(),
            'client_id'   => $request->post('client_id', ''),
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => trim((string) $request->post('description', '')),
            'status'      => $request->post('status', 'todo'),
            'priority'    => $request->post('priority', 'medium'),
            'due_date'    => $request->post('due_date', ''),
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

    public function edit(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $agencyId = (int) Auth::agencyId();
        $task     = $this->repo->findByIdAndAgency((int) $request->param('id'), $agencyId);
        if (!$task) return Response::view('errors.404', [], 404);

        $clients = $this->clientRepo->findByAgency($agencyId);
        $users   = $this->userRepo->findByAgency($agencyId);

        return $this->view('tasks.edit', compact('task', 'clients', 'users'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $id       = (int) $request->param('id');
        $agencyId = (int) Auth::agencyId();

        $prevTask   = $this->repo->findByIdAndAgency($id, $agencyId);
        $assignedTo = (int) $request->post('assigned_to', 0) ?: null;
        $title      = trim((string) $request->post('title', ''));

        $this->repo->updateTask($id, $agencyId, [
            'client_id'   => $request->post('client_id', ''),
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => trim((string) $request->post('description', '')),
            'status'      => $request->post('status', 'todo'),
            'priority'    => $request->post('priority', 'medium'),
            'due_date'    => $request->post('due_date', ''),
        ]);

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

    // ----------------------------------------------------------- status (AJAX)

    public function updateStatus(Request $request): Response
    {
        Auth::requirePermission('tasks.edit');
        $id     = (int) $request->param('id');
        $status = $request->post('status', '');
        $valid  = ['todo', 'in_progress', 'review', 'done'];

        if (!in_array($status, $valid)) {
            return Response::json(['error' => 'Status inválido'], 422);
        }

        $agencyId = (int) Auth::agencyId();
        $this->repo->updateStatus($id, $agencyId, $status);
        $this->enqueueClickUp($id, $agencyId, 'update');
        return Response::json(['success' => true]);
    }

    // ----------------------------------------------------------------- destroy

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('tasks.delete');
        $this->repo->deleteById((int) $request->param('id'), (int) Auth::agencyId());
        $this->withSuccess('Tarefa removida.');
        return $this->redirect('/tarefas');
    }
}
