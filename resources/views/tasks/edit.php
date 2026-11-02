<?php view_layout('app'); view_start('title'); ?>Editar Tarefa<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels   = ['todo' => 'A fazer', 'in_progress' => 'Em andamento', 'review' => 'Revisão', 'done' => 'Concluído'];
$priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];

// Valor fora do vocabulário (linha antiga, mapa do ClickUp) entra na lista como
// opção marcada: sem ela nenhuma `<option>` ficava `selected`, o navegador
// marcava a primeira — prioridade virava "Urgente" — e salvar só o título
// regravava o valor calado. Mesma correção do seletor de cliente (SEC-13).
if (!isset($statusLabels[$task['status']])) {
    $statusLabels[$task['status']] = t('tasks.legacy_value', ['value' => $task['status']]);
}
if (!isset($priorityLabels[$task['priority']])) {
    $priorityLabels[$task['priority']] = t('tasks.legacy_value', ['value' => $task['priority']]);
}
?>

<div class="max-w-xl mx-auto">
  <div class="mb-6">
    <nav class="flex items-center gap-2 text-sm text-gray-400 mb-3">
      <a href="/tarefas" class="hover:text-gray-300">Tarefas</a>
      <span>/</span>
      <a href="/tarefas/<?= $task['id'] ?>" class="hover:text-gray-300 truncate max-w-xs"><?= e($task['title']) ?></a>
      <span>/</span>
      <span class="text-gray-300">Editar</span>
    </nav>
    <h1 class="text-xl font-semibold text-white">Editar tarefa</h1>
  </div>

  <form method="POST" action="/tarefas/<?= $task['id'] ?>" class="card p-6 space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <div>
      <label class="label-field">Título *</label>
      <input aria-label="Título" type="text" name="title" required value="<?= e($task['title']) ?>"
             class="input-field w-full">
    </div>

    <div>
      <label class="label-field">Descrição</label>
      <textarea name="description" rows="4" class="input-field w-full resize-none"><?= e($task['description'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Status</label>
        <select aria-label="Situação" name="status" class="input-field w-full">
          <?php foreach ($statusLabels as $v => $l): ?>
          <option value="<?= e((string) $v) ?>" <?= (string) $task['status'] === (string) $v ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label-field">Prioridade</label>
        <select aria-label="Prioridade" name="priority" class="input-field w-full">
          <?php foreach ($priorityLabels as $v => $l): ?>
          <option value="<?= e((string) $v) ?>" <?= (string) $task['priority'] === (string) $v ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Cliente</label>
        <select aria-label="Cliente" name="client_id" class="input-field w-full">
          <?php /* A opção vazia é a primeira: sem a do cliente da tarefa marcada,
                    o navegador cai nela e salvar desfaz o vínculo (SEC-13). O
                    controller garante que o cliente arquivado esteja na lista. */ ?>
          <option value="">— Sem cliente —</option>
          <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (string) ($task['client_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label-field">Responsável</label>
        <select aria-label="Responsável" name="assigned_to" class="input-field w-full">
          <option value="">— Sem responsável —</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($task['assigned_to'] ?? '') === $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="label-field">Data de entrega</label>
      <input type="date" name="due_date"
             value="<?= e($task['due_date'] ?? '') ?>"
             class="input-field w-48">
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/tarefas/<?= $task['id'] ?>" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Salvar</button>
    </div>
  </form>
</div>

<?php view_end(); ?>
