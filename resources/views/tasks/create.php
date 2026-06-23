<?php view_layout('app'); view_start('title'); ?>Nova Tarefa<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels   = ['todo' => 'A fazer', 'in_progress' => 'Em andamento', 'review' => 'Revisão', 'done' => 'Concluído'];
$priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
?>

<div class="max-w-xl mx-auto">
  <div class="mb-6">
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <a href="/tarefas" class="hover:text-gray-300">Tarefas</a>
      <span>/</span>
      <span class="text-gray-300">Nova</span>
    </nav>
    <h1 class="text-xl font-semibold text-white">Nova tarefa</h1>
  </div>

  <form method="POST" action="/tarefas" class="card p-6 space-y-5">
    <?= csrf_field() ?>

    <div>
      <label class="label-field">Título *</label>
      <input type="text" name="title" required autofocus placeholder="Descreva a tarefa..."
             class="input-field w-full">
    </div>

    <div>
      <label class="label-field">Descrição</label>
      <textarea name="description" rows="4" placeholder="Detalhes, contexto, links..."
                class="input-field w-full resize-none"></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Status</label>
        <select name="status" class="input-field w-full">
          <?php foreach ($statusLabels as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($prefill['status'] ?? 'todo') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label-field">Prioridade</label>
        <select name="priority" class="input-field w-full">
          <?php foreach ($priorityLabels as $v => $l): ?>
          <option value="<?= $v ?>" <?= $v === 'medium' ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Cliente</label>
        <select name="client_id" class="input-field w-full">
          <option value="">— Sem cliente —</option>
          <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($prefill['client_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label-field">Responsável</label>
        <select name="assigned_to" class="input-field w-full">
          <option value="">— Sem responsável —</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($prefill['assigned_to'] ?? '') === $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="label-field">Data de entrega</label>
      <input type="date" name="due_date" class="input-field w-48">
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/tarefas" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Criar tarefa</button>
    </div>
  </form>
</div>

<?php view_end(); ?>
