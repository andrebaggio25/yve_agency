<?php view_layout('portal'); view_start('title'); ?><?= t('portal.invoices.title') ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = [
  'draft'     => t('portal.vstatus.draft'),
  'sent'      => t('portal.vstatus.sent'),
  'paid'      => t('portal.vstatus.paid'),
  'overdue'   => t('portal.vstatus.overdue'),
  'cancelled' => t('portal.vstatus.cancelled'),
  'partial'   => t('portal.vstatus.partial'),
];
$statusColors = [
  'draft'     => 'text-gray-400 bg-gray-500/10',
  'sent'      => 'text-blue-300 bg-blue-500/10',
  'paid'      => 'text-green-300 bg-green-500/10',
  'overdue'   => 'text-red-300 bg-red-500/10',
  'cancelled' => 'text-gray-400 bg-gray-700/30',
  'partial'   => 'text-yellow-300 bg-yellow-500/10',
];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white"><?= t('portal.invoices.title') ?></h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= t(count($invoices) === 1 ? 'portal.invoices.count' : 'portal.invoices.count_plural', ['n' => count($invoices)]) ?></p>
</div>

<?php if (empty($invoices)): ?>
<div class="card p-12 text-center text-gray-400"><?= t('portal.invoices.empty') ?></div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide"><?= t('portal.invoices.col_invoice') ?></th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide hidden sm:table-cell"><?= t('portal.invoices.col_issue') ?></th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide hidden sm:table-cell"><?= t('portal.invoices.col_due') ?></th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide"><?= t('portal.invoices.col_amount') ?></th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide"><?= t('portal.invoices.col_status') ?></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.04]">
      <?php foreach ($invoices as $inv): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-4">
          <p class="font-medium text-white"><?= e($inv['invoice_number'] ?? "#$inv[id]") ?></p>
          <?php if ($inv['description'] ?? null): ?>
          <p class="text-xs text-gray-400 mt-0.5 line-clamp-1"><?= e($inv['description']) ?></p>
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
