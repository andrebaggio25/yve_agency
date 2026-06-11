<?php view_layout('app'); view_start('title'); ?><?= e($task['title']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels   = ['todo' => 'A fazer', 'in_progress' => 'Em andamento', 'review' => 'Revisão', 'done' => 'Concluído'];
$statusColors   = [
    'todo'        => 'bg-gray-500/15 text-gray-300',
    'in_progress' => 'bg-blue-500/15 text-blue-300',
    'review'      => 'bg-yellow-500/15 text-yellow-300',
    'done'        => 'bg-green-500/15 text-green-300',
];
$priorityColors = [
    'urgent' => 'text-red-400 bg-red-500/10',
    'high'   => 'text-orange-400 bg-orange-500/10',
    'medium' => 'text-yellow-400 bg-yellow-500/10',
    'low'    => 'text-gray-400 bg-gray-500/10',
];
$priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
$overdue = $task['due_date'] && $task['status'] !== 'done' && strtotime($task['due_date']) < strtotime('today');
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="/tarefas" class="hover:text-gray-300">Tarefas</a>
  <span>/</span>
  <span class="text-gray-300 truncate max-w-xs"><?= e($task['title']) ?></span>
</nav>

<div class="max-w-2xl">
  <div class="card p-6 mb-4">
    <!-- Badges topo -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium <?= $statusColors[$task['status']] ?>">
        <?= $statusLabels[$task['status']] ?>
      </span>
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $priorityColors[$task['priority']] ?>">
        <?= $priorityLabels[$task['priority']] ?>
      </span>
      <?php if ($overdue): ?>
      <span class="text-xs text-red-400 font-semibold">⚠ Atrasada</span>
      <?php endif; ?>
    </div>

    <h1 class="text-xl font-semibold text-white mb-4"><?= e($task['title']) ?></h1>

    <?php if ($task['description']): ?>
    <div class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap mb-6">
      <?= e($task['description']) ?>
    </div>
    <?php endif; ?>

    <!-- Metadados -->
    <div class="grid grid-cols-2 gap-4 text-sm border-t border-white/[0.06] pt-5">
      <div>
        <p class="text-xs text-gray-500 mb-1">Cliente</p>
        <p class="text-gray-300"><?= e($task['client_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-500 mb-1">Responsável</p>
        <p class="text-gray-300"><?= e($task['assigned_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-500 mb-1">Criada por</p>
        <p class="text-gray-300"><?= e($task['created_by_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-500 mb-1">Data de entrega</p>
        <p class="<?= $overdue ? 'text-red-400 font-medium' : 'text-gray-300' ?>">
          <?= $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '—' ?>
        </p>
      </div>
      <div>
        <p class="text-xs text-gray-500 mb-1">Criada em</p>
        <p class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($task['created_at'])) ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-500 mb-1">Atualizada em</p>
        <p class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($task['updated_at'])) ?></p>
      </div>
    </div>
  </div>

  <!-- Ações -->
  <div class="flex items-center gap-3 flex-wrap">
    <?php if (\App\Support\Auth::can('tasks.edit')): ?>
    <a href="/tarefas/<?= $task['id'] ?>/editar" class="btn-secondary text-sm px-4 py-2">Editar</a>
    <?php endif; ?>
    <?php if (\App\Support\Auth::can('tasks.edit')): ?>
    <!-- Mudança rápida de status -->
    <div x-data="{open: false}" class="relative">
      <button @click="open = !open" class="btn-secondary text-sm px-4 py-2">
        Mover para &darr;
      </button>
      <div x-show="open" @click.outside="open = false"
           class="absolute left-0 mt-1 w-44 card shadow-xl z-20 py-1 text-sm">
        <?php foreach ($statusLabels as $v => $l): ?>
        <?php if ($v !== $task['status']): ?>
        <form method="POST" action="/tarefas/<?= $task['id'] ?>/status">
          <input type="hidden" name="_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="status" value="<?= $v ?>">
          <button class="w-full text-left px-4 py-2 hover:bg-white/[0.05] text-gray-300"><?= $l ?></button>
        </form>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (\App\Support\Auth::can('tasks.delete')): ?>
    <form method="POST" action="/tarefas/<?= $task['id'] ?>" class="ml-auto"
          onsubmit="return confirm('Excluir esta tarefa?')">
      <input type="hidden" name="_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="_method" value="DELETE">
      <button class="text-sm text-red-400 hover:text-red-300">Excluir</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php view_end(); ?>
