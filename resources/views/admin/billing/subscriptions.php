<?php view_layout('admin'); view_start('title'); ?>Assinaturas<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['trialing' => 'Trial', 'active' => 'Ativa', 'past_due' => 'Atrasada', 'cancelled' => 'Cancelada', 'suspended' => 'Suspensa', 'none' => 'Sem plano'];
$statusColors = [
  'trialing'  => 'text-blue-300 bg-blue-500/10',
  'active'    => 'text-green-300 bg-green-500/10',
  'past_due'  => 'text-red-300 bg-red-500/10',
  'cancelled' => 'text-gray-500 bg-gray-700/20',
  'suspended' => 'text-yellow-300 bg-yellow-500/10',
];
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Assinaturas</h1>
    <p class="text-sm text-gray-400 mt-0.5"><?= count($subscriptions) ?> assinatura<?= count($subscriptions) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap gap-3 mb-6">
  <select name="status" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="">Todos os status</option>
    <?php foreach ($statusLabels as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="plan_id" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="">Todos os planos</option>
    <?php foreach ($plans as $p): ?>
    <option value="<?= $p['id'] ?>" <?= $filters['plan_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- Atribuir plano manualmente -->
<div class="card p-5 mb-6 border border-violet-500/20">
  <h2 class="text-sm font-semibold text-violet-300 mb-4">Atribuir plano a uma agência</h2>
  <form method="POST" action="/admin/assinaturas/atribuir" class="flex flex-wrap items-end gap-3">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div>
      <label class="label-field text-xs">ID da Agência</label>
      <input type="number" name="agency_id" required min="1" class="input-field w-32 text-sm py-1.5" placeholder="1">
    </div>
    <div>
      <label class="label-field text-xs">Plano</label>
      <select name="plan_id" required class="input-field text-sm py-1.5">
        <?php foreach ($plans as $p): ?>
        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="label-field text-xs">Ciclo</label>
      <select name="billing_cycle" class="input-field text-sm py-1.5">
        <option value="monthly">Mensal</option>
        <option value="yearly">Anual</option>
      </select>
    </div>
    <div>
      <label class="label-field text-xs">Status</label>
      <select name="status" class="input-field text-sm py-1.5">
        <option value="active">Ativa</option>
        <option value="trialing">Trial</option>
        <option value="suspended">Suspensa</option>
      </select>
    </div>
    <button type="submit" class="btn-primary text-sm px-4 py-2">Aplicar</button>
  </form>
</div>

<?php if (empty($subscriptions)): ?>
<div class="card p-12 text-center text-gray-500">Nenhuma assinatura encontrada.</div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Agência</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Plano</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Ciclo</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Período</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($subscriptions as $s): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3 font-medium text-white"><?= e($s['agency_name']) ?> <span class="text-xs text-gray-600">#<?= $s['agency_id'] ?></span></td>
        <td class="px-5 py-3 text-gray-300"><?= e($s['plan_name']) ?></td>
        <td class="px-5 py-3 text-gray-400 capitalize"><?= $s['billing_cycle'] === 'monthly' ? 'Mensal' : 'Anual' ?></td>
        <td class="px-5 py-3 text-gray-400 text-xs">
          <?= $s['current_period_start'] ? date('d/m/Y', strtotime($s['current_period_start'])) : '—' ?>
          <?php if ($s['current_period_end']): ?>
          → <?= date('d/m/Y', strtotime($s['current_period_end'])) ?>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3 text-center">
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColors[$s['status']] ?? 'text-gray-400 bg-gray-500/10' ?>">
            <?= $statusLabels[$s['status']] ?? $s['status'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php view_end(); ?>
