<?php view_layout('app'); view_start('content'); ?>

<div class="min-h-screen">

  <!-- Header -->
  <div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Aprovações</p>
    <h1 class="text-2xl font-bold text-white">Planos para Revisar</h1>
    <p class="mt-1 text-sm text-gray-400">
      <?= count($plans) ?> plano<?= count($plans) !== 1 ? 's' : '' ?> disponível<?= count($plans) !== 1 ? 'is' : '' ?>
    </p>
  </div>

  <?php if (empty($plans)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-emerald-500/10 p-6">
      <svg class="w-12 h-12 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1">Tudo em dia!</h3>
    <p class="text-gray-400 text-sm">Nenhum plano aguardando revisão no momento.</p>
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php
    $statusColors = [
      'sent'     => ['bg' => 'bg-blue-500/15',   'text' => 'text-blue-300',   'ring' => 'ring-blue-500/30',   'dot' => 'bg-blue-400',    'label' => 'Aguardando Aprovação'],
      'revision' => ['bg' => 'bg-amber-500/15',  'text' => 'text-amber-300',  'ring' => 'ring-amber-500/30',  'dot' => 'bg-amber-400',   'label' => 'Em Revisão'],
      'approved' => ['bg' => 'bg-emerald-500/15','text' => 'text-emerald-300','ring' => 'ring-emerald-500/30','dot' => 'bg-emerald-400', 'label' => 'Aprovado'],
    ];
    foreach ($plans as $plan):
      $sc    = $statusColors[$plan['status']] ?? $statusColors['sent'];
      $total = (int) $plan['total_items'];
      $aprov = (int) $plan['approved_items'];
      $pct   = $total > 0 ? round(($aprov / $total) * 100) : 0;
    ?>
    <a href="/aprovacoes/<?= e($plan['id']) ?>"
       class="group flex items-start gap-4 rounded-2xl border border-white/5 bg-white/[0.03] p-5 transition-all hover:border-brand-500/30 hover:bg-white/[0.06] hover:-translate-y-0.5">
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $sc['bg'] ?> <?= $sc['text'] ?> <?= $sc['ring'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?> <?= $plan['status'] === 'sent' ? 'animate-pulse' : '' ?>"></span>
            <?= $sc['label'] ?>
          </span>
        </div>
        <h3 class="font-semibold text-white group-hover:text-brand-200 transition-colors"><?= e($plan['title']) ?></h3>
        <p class="text-sm text-gray-400 mt-0.5">
          <?= date('d/m', strtotime($plan['week_start'])) ?> – <?= date('d/m/Y', strtotime($plan['week_end'])) ?>
        </p>
        <?php if ($total > 0): ?>
        <div class="mt-3">
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span><?= $aprov ?>/<?= $total ?> aprovados</span>
            <span><?= $pct ?>%</span>
          </div>
          <div class="h-1.5 rounded-full bg-white/5">
            <div class="h-full rounded-full <?= $plan['status'] === 'approved' ? 'bg-emerald-400' : 'bg-gradient-to-r from-brand-600 to-brand-400' ?>"
                 style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5 transition-transform group-hover:translate-x-1 group-hover:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php view_end(); ?>
