<?php view_layout('app'); view_start('content'); ?>
<?php
$statusColors = [
  'sent'     => ['bg' => 'bg-blue-500/15',   'text' => 'text-blue-300',   'ring' => 'ring-blue-500/30',   'dot' => 'bg-blue-400',    'label' => t('approval.waiting')],
  'revision' => ['bg' => 'bg-amber-500/15',  'text' => 'text-amber-300',  'ring' => 'ring-amber-500/30',  'dot' => 'bg-amber-400',   'label' => t('approval.in_revision')],
  'approved' => ['bg' => 'bg-emerald-500/15','text' => 'text-emerald-300','ring' => 'ring-emerald-500/30','dot' => 'bg-emerald-400', 'label' => t('status.approved')],
  'rejected' => ['bg' => 'bg-rose-500/15',   'text' => 'text-rose-300',   'ring' => 'ring-rose-500/30',   'dot' => 'bg-rose-400',    'label' => t('status.rejected')],
];
?>

<div class="min-h-screen">

  <!-- Header -->
  <div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-accent mb-1"><?= t('approval.title') ?></p>
    <h1 class="text-2xl font-bold text-white"><?= t('approval.page_title') ?></h1>
    <p class="mt-1 text-sm text-gray-400"><?= e(tc('approval.plans_count', count($plans))) ?></p>
  </div>

  <?php if (empty($plans)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <?php /* Perfil sem alcance de cliente veria "Tudo em dia!" para sempre
              (BUG-04/SEC-11): a tela diz o que pedir ao administrador. */ ?>
    <?php if (!empty($noClientReach)): ?>
    <div class="mb-4 rounded-2xl bg-accent-soft p-6">
      <svg class="w-12 h-12 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
      </svg>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1"><?= t('approval.page_title') ?></h3>
    <p class="text-gray-400 text-sm max-w-xl"><?= t('approval.no_client_reach') ?></p>
    <?php else: ?>
    <div class="mb-4 rounded-2xl bg-emerald-500/10 p-6">
      <svg class="w-12 h-12 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1"><?= t('approval.nothing_pending') ?></h3>
    <p class="text-gray-400 text-sm"><?= t('approval.no_plans') ?></p>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php
    foreach ($plans as $plan):
      $sc    = $statusColors[$plan['status']] ?? $statusColors['sent'];
      $total = (int) $plan['total_items'];
      $aprov = (int) $plan['approved_items'];
      $pct   = $total > 0 ? round(($aprov / $total) * 100) : 0;
    ?>
    <a href="/aprovacoes/<?= (int) $plan['id'] ?>"
       class="card group flex items-start gap-4 p-5 transition-all hover:border-accent hover:bg-white/[0.06] hover:-translate-y-0.5">
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $sc['bg'] ?> <?= $sc['text'] ?> <?= $sc['ring'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?> <?= $plan['status'] === 'sent' ? 'animate-pulse' : '' ?>"></span>
            <?= e($sc['label']) ?>
          </span>
          <?php /* A tela interna mistura clientes: sem o nome, dois planos da
                    mesma semana ficam indistinguíveis. */ ?>
          <span class="text-xs text-gray-400"><?= e($plan['client_name'] ?? '') ?></span>
        </div>
        <h3 class="font-semibold text-white group-hover:text-accent transition-colors"><?= e($plan['title']) ?></h3>
        <p class="text-sm text-gray-400 mt-0.5">
          <?= date('d/m', strtotime($plan['week_start'])) ?> – <?= date('d/m/Y', strtotime($plan['week_end'])) ?>
        </p>
        <?php if ($total > 0): ?>
        <div class="mt-3">
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span><?= e(t('approval.items_approved', ['approved' => $aprov, 'total' => $total])) ?></span>
            <span><?= $pct ?>%</span>
          </div>
          <div class="h-1.5 rounded-full bg-white/5">
            <div class="h-full rounded-full <?= $plan['status'] === 'approved' ? 'bg-emerald-400' : 'bg-accent' ?>"
                 style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5 transition-transform group-hover:translate-x-1 group-hover:text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php view_end(); ?>
