<?php view_layout('app'); view_start('title'); ?>Tarefas<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels   = ['todo' => 'A fazer', 'in_progress' => 'Em andamento', 'review' => 'Revisão', 'done' => 'Concluído'];
$statusColors   = [
    'todo'        => 'bg-gray-500/15 text-gray-300 border-gray-500/30',
    'in_progress' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
    'review'      => 'bg-yellow-500/15 text-yellow-300 border-yellow-500/30',
    'done'        => 'bg-green-500/15 text-green-300 border-green-500/30',
];
$priorityColors = [
    'urgent' => 'text-red-400 bg-red-500/10',
    'high'   => 'text-orange-400 bg-orange-500/10',
    'medium' => 'text-yellow-400 bg-yellow-500/10',
    'low'    => 'text-gray-400 bg-gray-500/10',
];
$priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Tarefas</h1>
    <p class="text-sm text-gray-400 mt-0.5">
      <?= array_sum($counts) ?> tarefas &mdash;
      <span class="text-blue-400"><?= $counts['in_progress'] ?> em andamento</span>
    </p>
  </div>
  <?php if (\App\Support\Auth::can('tasks.create')): ?>
  <a href="/tarefas/nova" class="btn-primary text-sm px-4 py-2">+ Nova tarefa</a>
  <?php endif; ?>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <select name="status" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="">Todos os status</option>
    <?php foreach ($statusLabels as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="client_id" class="input-field text-sm py-1.5 px-3 w-48">
    <option value="">Todos os clientes</option>
    <?php foreach ($clients as $c): ?>
    <option value="<?= $c['id'] ?>" <?= $filters['client_id'] === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="assigned_to" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="">Qualquer responsável</option>
    <?php foreach ($users as $u): ?>
    <option value="<?= $u['id'] ?>" <?= $filters['assigned_to'] === $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="priority" class="input-field text-sm py-1.5 px-3 w-36">
    <option value="">Qualquer prioridade</option>
    <?php foreach ($priorityLabels as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filters['priority'] === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
  <?php if (array_filter($filters)): ?>
  <a href="/tarefas" class="text-xs text-gray-500 hover:text-gray-300">Limpar</a>
  <?php endif; ?>
</form>

<!-- Kanban board -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4"
     x-data="kanbanBoard()"
     x-init="init()">

  <?php foreach (['todo', 'in_progress', 'review', 'done'] as $col): ?>
  <div class="flex flex-col min-h-[400px]"
       x-on:dragover.prevent
       x-on:drop.prevent="drop($event, '<?= $col ?>')">

    <!-- Cabeçalho da coluna -->
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border <?= $statusColors[$col] ?>">
          <?= $statusLabels[$col] ?>
        </span>
        <span class="text-xs text-gray-600 font-medium"><?= $counts[$col] ?></span>
      </div>
      <?php if (\App\Support\Auth::can('tasks.create')): ?>
      <a href="/tarefas/nova?status=<?= $col ?>"
         class="w-6 h-6 rounded-md bg-white/[0.05] hover:bg-white/[0.1] flex items-center justify-center text-gray-400 hover:text-white transition-colors text-base leading-none">+</a>
      <?php endif; ?>
    </div>

    <!-- Cards -->
    <div class="flex-1 space-y-2.5 rounded-xl p-2 border border-dashed border-white/[0.06] bg-white/[0.01]"
         data-column="<?= $col ?>">

      <?php foreach ($board[$col] as $t): ?>
      <?php
        $overdue = $t['due_date'] && $t['status'] !== 'done' && strtotime($t['due_date']) < strtotime('today');
      ?>
      <div class="card p-3.5 cursor-grab active:cursor-grabbing select-none group"
           draggable="true"
           x-on:dragstart="dragStart($event, '<?= $t['id'] ?>', '<?= $col ?>')"
           x-on:dragend="dragEnd($event)"
           data-task-id="<?= $t['id'] ?>">

        <!-- Prioridade + menu -->
        <div class="flex items-center justify-between mb-2">
          <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium <?= $priorityColors[$t['priority']] ?>">
            <?= $priorityLabels[$t['priority']] ?>
          </span>
          <a href="/tarefas/<?= $t['id'] ?>"
             class="opacity-0 group-hover:opacity-100 transition-opacity text-xs text-violet-400 hover:text-violet-300">
            Ver
          </a>
        </div>

        <!-- Título -->
        <p class="text-sm font-medium text-white leading-snug mb-2">
          <?= e($t['title']) ?>
        </p>

        <!-- Cliente -->
        <?php if ($t['client_name']): ?>
        <p class="text-xs text-gray-500 mb-2 flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          <?= e($t['client_name']) ?>
        </p>
        <?php endif; ?>

        <!-- Footer: data + responsável -->
        <div class="flex items-center justify-between pt-2 border-t border-white/[0.05]">
          <?php if ($t['due_date']): ?>
          <span class="text-[11px] <?= $overdue ? 'text-red-400 font-semibold' : 'text-gray-500' ?>">
            <?= $overdue ? '⚠ ' : '' ?><?= date('d/m', strtotime($t['due_date'])) ?>
          </span>
          <?php else: ?>
          <span></span>
          <?php endif; ?>
          <?php if ($t['assigned_name']): ?>
          <div class="w-5 h-5 rounded-full bg-violet-500/30 flex items-center justify-center text-[10px] font-bold text-violet-200"
               title="<?= e($t['assigned_name']) ?>">
            <?= mb_strtoupper(mb_substr($t['assigned_name'], 0, 1)) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($board[$col])): ?>
      <div class="flex-1 flex items-center justify-center py-8 text-xs text-gray-700">
        Arraste cards aqui
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php view_start('scripts'); ?>
<script>
function kanbanBoard() {
  return {
    dragging: null,
    fromCol: null,

    init() {},

    dragStart(event, taskId, col) {
      this.dragging = taskId;
      this.fromCol  = col;
      event.dataTransfer.effectAllowed = 'move';
      event.target.classList.add('opacity-50');
    },

    dragEnd(event) {
      event.target.classList.remove('opacity-50');
    },

    drop(event, toCol) {
      if (!this.dragging || toCol === this.fromCol) return;

      fetch(`/tarefas/${this.dragging}/status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({
          _token: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          status: toCol,
        }),
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // Mover o card no DOM sem recarregar
          const card = document.querySelector(`[data-task-id="${this.dragging}"]`);
          const target = event.currentTarget.querySelector('[data-column="' + toCol + '"]')
            ?? event.target.closest('[data-column]');
          if (card && target) {
            target.insertBefore(card, target.querySelector('.flex-1.flex') ?? null);
          } else {
            window.location.reload();
          }
          this.fromCol = toCol;
        }
      })
      .catch(() => window.location.reload());
    },
  }
}
</script>
<?php view_end(); ?>

<?php view_end(); ?>
