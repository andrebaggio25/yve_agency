<?php view_layout('portal'); view_start('title'); ?><?= t('portal.contracts.title') ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = [
  'draft'     => t('portal.cstatus.draft'),
  'active'    => t('portal.cstatus.active'),
  'expired'   => t('portal.cstatus.expired'),
  'cancelled' => t('portal.cstatus.cancelled'),
];
$statusColors = [
  'draft'     => 'text-gray-400 bg-gray-500/10',
  'active'    => 'text-green-300 bg-green-500/10',
  'expired'   => 'text-yellow-300 bg-yellow-500/10',
  'cancelled' => 'text-gray-400 bg-gray-700/30',
];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white"><?= t('portal.contracts.title') ?></h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= t(count($contracts) === 1 ? 'portal.contracts.count' : 'portal.contracts.count_plural', ['n' => count($contracts)]) ?></p>
</div>

<?php if (empty($contracts)): ?>
<div class="card p-12 text-center text-gray-400"><?= t('portal.contracts.empty') ?></div>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($contracts as $ct): ?>
  <div class="card p-5 flex items-start justify-between gap-4">
    <div class="flex-1 min-w-0">
      <p class="font-medium text-white mb-1"><?= e($ct['title']) ?></p>
      <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
        <?php if ($ct['start_date'] ?? null): ?>
        <span><?= t('portal.contracts.start') ?>: <?= date('d/m/Y', strtotime($ct['start_date'])) ?></span>
        <?php endif; ?>
        <?php if ($ct['end_date'] ?? null): ?>
        <span><?= t('portal.contracts.end') ?>: <?= date('d/m/Y', strtotime($ct['end_date'])) ?></span>
        <?php endif; ?>
        <?php if ($ct['value'] ?? null): ?>
        <span class="font-semibold text-white">R$ <?= number_format((float)$ct['value'], 2, ',', '.') ?><?= t('portal.contracts.per_month') ?></span>
        <?php endif; ?>
      </div>
    </div>
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0 <?= $statusColors[$ct['status']] ?? '' ?>">
      <?= $statusLabels[$ct['status']] ?? $ct['status'] ?>
    </span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_end(); ?>
