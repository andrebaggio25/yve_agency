<?php view_layout('portal'); view_start('title'); ?>Contratos<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['draft' => 'Rascunho', 'active' => 'Ativo', 'expired' => 'Expirado', 'cancelled' => 'Cancelado'];
$statusColors = [
  'draft'     => 'text-gray-400 bg-gray-500/10',
  'active'    => 'text-green-300 bg-green-500/10',
  'expired'   => 'text-yellow-300 bg-yellow-500/10',
  'cancelled' => 'text-gray-500 bg-gray-700/30',
];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Contratos</h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= count($contracts) ?> contrato<?= count($contracts) !== 1 ? 's' : '' ?></p>
</div>

<?php if (empty($contracts)): ?>
<div class="card p-12 text-center text-gray-500">Nenhum contrato disponível.</div>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($contracts as $ct): ?>
  <div class="card p-5 flex items-start justify-between gap-4">
    <div class="flex-1 min-w-0">
      <p class="font-medium text-white mb-1"><?= e($ct['title']) ?></p>
      <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
        <?php if ($ct['start_date'] ?? null): ?>
        <span>Início: <?= date('d/m/Y', strtotime($ct['start_date'])) ?></span>
        <?php endif; ?>
        <?php if ($ct['end_date'] ?? null): ?>
        <span>Fim: <?= date('d/m/Y', strtotime($ct['end_date'])) ?></span>
        <?php endif; ?>
        <?php if ($ct['value'] ?? null): ?>
        <span class="font-semibold text-white">R$ <?= number_format((float)$ct['value'], 2, ',', '.') ?>/mês</span>
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
