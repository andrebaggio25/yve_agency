<?php view_layout('portal'); view_start('title'); ?><?= t('portal.plans.title') ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = [
  'draft'            => t('portal.pstatus.draft'),
  'pending_approval' => t('portal.pstatus.pending_approval'),
  'approved'         => t('portal.pstatus.approved'),
  'in_revision'      => t('portal.pstatus.in_revision'),
  'published'        => t('portal.pstatus.published'),
];
$statusColors = [
  'draft'            => 'text-gray-400 bg-gray-500/10',
  'pending_approval' => 'text-yellow-300 bg-yellow-500/10',
  'approved'         => 'text-green-300 bg-green-500/10',
  'in_revision'      => 'text-blue-300 bg-blue-500/10',
  'published'        => 'text-violet-300 bg-violet-500/10',
];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white"><?= t('portal.plans.title') ?></h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= t(count($plans) === 1 ? 'portal.plans.count' : 'portal.plans.count_plural', ['n' => count($plans)]) ?></p>
</div>

<?php if (empty($plans)): ?>
<div class="card p-12 text-center text-gray-500"><?= t('portal.plans.empty') ?></div>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($plans as $p): ?>
  <a href="/portal/<?= $token ?>/planos/<?= $p['id'] ?>"
     class="card p-5 flex items-center justify-between hover:bg-white/[0.03] transition-colors block">
    <div>
      <p class="font-medium text-white mb-1"><?= e($p['title']) ?></p>
      <div class="flex items-center gap-3 text-xs text-gray-500">
        <?php if ($p['period_label'] ?? null): ?>
        <span><?= e($p['period_label']) ?></span>
        <?php endif; ?>
        <?php if ($p['items_count'] ?? null): ?>
        <span><?= t((int) $p['items_count'] === 1 ? 'portal.plans.items' : 'portal.plans.items_plural', ['n' => $p['items_count']]) ?></span>
        <?php endif; ?>
        <span><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
      </div>
    </div>
    <div class="flex items-center gap-3 flex-shrink-0 ml-4">
      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $statusColors[$p['status']] ?? 'text-gray-400' ?>">
        <?= $statusLabels[$p['status']] ?? $p['status'] ?>
      </span>
      <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_end(); ?>
