<?php view_layout('admin'); view_start('title'); ?>Assinaturas<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Assinaturas<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['trialing' => 'Trial', 'active' => 'Ativa', 'past_due' => 'Atrasada', 'cancelled' => 'Cancelada', 'suspended' => 'Suspensa'];
$statusColors = [
  'trialing'  => 'text-blue-300 bg-blue-500/10',
  'active'    => 'text-emerald-300 bg-emerald-500/10',
  'past_due'  => 'text-red-300 bg-red-500/10',
  'cancelled' => 'text-gray-400 bg-gray-700/20',
  'suspended' => 'text-yellow-300 bg-yellow-500/10',
  'none'      => 'text-gray-400 bg-gray-700/10',
];

$total    = count($tenants);
$withPlan = count(array_filter($tenants, fn($t) => $t['sub_id']));
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Assinaturas</h1>
    <p class="text-sm text-gray-400 mt-0.5"><?= $withPlan ?>/<?= $total ?> tenants com plano ativo</p>
  </div>
  <a href="/admin/tenants/criar" class="btn-primary text-sm px-4 py-2">+ Novo tenant</a>
</div>

<?php if (empty($tenants)): ?>
<div class="card p-12 text-center text-gray-400">Nenhum tenant cadastrado.</div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Tenant</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Plano</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Ciclo</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Período atual</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Status</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($tenants as $t): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3.5">
          <a href="/admin/tenants/<?= $t['agency_id'] ?>/editar" class="font-medium text-white hover:text-red-400 transition-colors">
            <?= e($t['agency_name']) ?>
          </a>
          <span class="ml-1.5 text-xs text-gray-400">#<?= $t['agency_id'] ?></span>
        </td>
        <td class="px-5 py-3.5 text-gray-300">
          <?= $t['plan_name'] ? e($t['plan_name']) : '<span class="text-gray-400 italic">Sem plano</span>' ?>
        </td>
        <td class="px-5 py-3.5 text-gray-400 text-xs">
          <?php if ($t['billing_cycle']): ?>
            <?= $t['billing_cycle'] === 'monthly' ? 'Mensal' : 'Anual' ?>
          <?php else: ?>
            <span class="text-gray-400">—</span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3.5 text-gray-400 text-xs">
          <?php if ($t['current_period_start']): ?>
            <?= date('d/m/Y', strtotime($t['current_period_start'])) ?>
            <?php if ($t['current_period_end']): ?>
            → <?= date('d/m/Y', strtotime($t['current_period_end'])) ?>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-gray-400">—</span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3.5 text-center">
          <?php if ($t['status']): ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColors[$t['status']] ?? 'text-gray-400 bg-gray-500/10' ?>">
            <?= $statusLabels[$t['status']] ?? $t['status'] ?>
          </span>
          <?php else: ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColors['none'] ?>">
            Sem plano
          </span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3.5 text-right">
          <a href="/admin/assinaturas/<?= $t['agency_id'] ?>/editar"
             class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition-all">
            <?= $t['sub_id'] ? 'Editar' : 'Atribuir plano' ?>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php view_end(); ?>
