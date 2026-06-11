<?php view_layout('portal'); view_start('title'); ?>Faturas<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['draft' => 'Rascunho', 'sent' => 'Em aberto', 'paid' => 'Paga', 'overdue' => 'Vencida', 'cancelled' => 'Cancelada', 'partial' => 'Parcial'];
$statusColors = [
  'draft'     => 'text-gray-400 bg-gray-500/10',
  'sent'      => 'text-blue-300 bg-blue-500/10',
  'paid'      => 'text-green-300 bg-green-500/10',
  'overdue'   => 'text-red-300 bg-red-500/10',
  'cancelled' => 'text-gray-500 bg-gray-700/30',
  'partial'   => 'text-yellow-300 bg-yellow-500/10',
];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Faturas</h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= count($invoices) ?> fatura<?= count($invoices) !== 1 ? 's' : '' ?></p>
</div>

<?php if (empty($invoices)): ?>
<div class="card p-12 text-center text-gray-500">Nenhuma fatura disponível.</div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Fatura</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide hidden sm:table-cell">Emissão</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide hidden sm:table-cell">Vencimento</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Valor</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.04]">
      <?php foreach ($invoices as $inv): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-4">
          <p class="font-medium text-white"><?= e($inv['invoice_number'] ?? "#$inv[id]") ?></p>
          <?php if ($inv['description'] ?? null): ?>
          <p class="text-xs text-gray-500 mt-0.5 line-clamp-1"><?= e($inv['description']) ?></p>
          <?php endif; ?>
        </td>
        <td class="px-5 py-4 text-gray-400 hidden sm:table-cell">
          <?= $inv['issue_date'] ? date('d/m/Y', strtotime($inv['issue_date'])) : '—' ?>
        </td>
        <td class="px-5 py-4 <?= ($inv['status'] === 'overdue') ? 'text-red-400 font-medium' : 'text-gray-400' ?> hidden sm:table-cell">
          <?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?>
        </td>
        <td class="px-5 py-4 text-right font-semibold text-white">
          R$ <?= number_format((float)$inv['total'], 2, ',', '.') ?>
        </td>
        <td class="px-5 py-4 text-center">
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColors[$inv['status']] ?? '' ?>">
            <?= $statusLabels[$inv['status']] ?? $inv['status'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php view_end(); ?>
