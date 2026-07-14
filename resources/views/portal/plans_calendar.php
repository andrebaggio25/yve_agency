<?php view_layout('portal'); view_start('title'); ?><?= t('portal.calendar.title') ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
/**
 * Calendário mensal de CONSULTA do cliente: mostra as publicações do mês
 * (nunca de planos em rascunho — filtro no repositório) e cada criativo
 * abre a planificação semanal dele, ancorado no item.
 */
$itemStatusColors = [
    'draft'    => 'border-gray-500/30 bg-gray-500/[0.07]',
    'approved' => 'border-green-500/30 bg-green-500/[0.07]',
    'revision' => 'border-yellow-500/30 bg-yellow-500/[0.07]',
    'rejected' => 'border-red-500/30 bg-red-500/[0.07]',
];
$itemStatusDots = [
    'draft'    => 'bg-gray-400',
    'approved' => 'bg-green-400',
    'revision' => 'bg-yellow-400',
    'rejected' => 'bg-red-400',
];

// Grade seg→dom, o mesmo eixo da planificação semanal.
$firstTs   = strtotime($firstDay);
$lastTs    = strtotime($lastDay);
$gridStart = strtotime('-' . ((int) date('N', $firstTs) - 1) . ' days', $firstTs);
$gridEnd   = strtotime('+' . (7 - (int) date('N', $lastTs)) . ' days', $lastTs);
$today     = date('Y-m-d');
$monthNum  = (int) date('n', $firstTs);
$monthLabel = t('portal.month.' . $monthNum) . ' ' . date('Y', $firstTs);
?>

<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
  <div>
    <h1 class="text-xl font-semibold text-white"><?= e($monthLabel) ?></h1>
    <p class="text-sm text-gray-400 mt-0.5">
      <?= t((int) $total === 1 ? 'portal.calendar.count' : 'portal.calendar.count_plural', ['n' => (int) $total]) ?>
      · <?= t('portal.calendar.hint') ?>
    </p>
  </div>
  <a href="/portal/<?= $token ?>/planos" class="btn-secondary text-sm px-4 py-2"><?= t('portal.calendar.back') ?></a>
</div>

<!-- Navegação por mês -->
<div class="flex items-center gap-1 mb-4">
  <a href="/portal/<?= $token ?>/planos/calendario?month=<?= e($prevMonth) ?>"
     class="btn-secondary text-sm px-3 py-2" aria-label="<?= e(t('portal.calendar.prev')) ?>">←</a>
  <a href="/portal/<?= $token ?>/planos/calendario?month=<?= date('Y-m') ?>"
     class="btn-secondary text-sm px-3 py-2"><?= t('portal.calendar.today') ?></a>
  <a href="/portal/<?= $token ?>/planos/calendario?month=<?= e($nextMonth) ?>"
     class="btn-secondary text-sm px-3 py-2" aria-label="<?= e(t('portal.calendar.next')) ?>">→</a>
</div>

<!-- Grade do mês (rola horizontalmente no celular) -->
<div class="overflow-x-auto">
  <div class="min-w-[46rem]">
    <div class="grid grid-cols-7 gap-px mb-px">
      <?php for ($d = 1; $d <= 7; $d++): ?>
        <div class="px-2 py-2 text-center text-xs uppercase tracking-wide text-gray-400"><?= e(t('portal.dow.' . $d)) ?></div>
      <?php endfor; ?>
    </div>

    <div class="grid grid-cols-7 gap-1.5">
      <?php for ($ts = $gridStart; $ts <= $gridEnd; $ts = strtotime('+1 day', $ts)):
        $date     = date('Y-m-d', $ts);
        $isMonth  = (int) date('n', $ts) === $monthNum;
        $isToday  = $date === $today;
        $dayItems = $byDay[$date] ?? [];
      ?>
      <div class="min-h-[6.5rem] rounded-xl border p-2
                  <?= $isMonth ? 'border-white/[0.06] bg-white/[0.02]' : 'border-transparent bg-transparent' ?>
                  <?= $isToday ? 'ring-1 ring-brand-500/50' : '' ?>">
        <div class="mb-1.5">
          <span class="text-xs font-medium <?= $isToday ? 'text-brand-300' : ($isMonth ? 'text-gray-400' : 'text-gray-500') ?>">
            <?= (int) date('j', $ts) ?>
          </span>
        </div>

        <?php foreach (array_slice($dayItems, 0, 3) as $item):
          $cls = $itemStatusColors[$item['status']] ?? $itemStatusColors['draft'];
          $dot = $itemStatusDots[$item['status']] ?? $itemStatusDots['draft'];
        ?>
          <a href="/portal/<?= $token ?>/planos/<?= (int) $item['content_plan_id'] ?>#item-<?= (int) $item['id'] ?>"
             class="block mb-1 rounded-lg border px-1.5 py-1 <?= $cls ?> hover:brightness-125 transition-all"
             title="<?= e(($item['title'] ?: ($item['content_type'] ?? '')) . (!empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '')) ?>">
            <span class="flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 <?= $dot ?>"></span>
              <span class="text-[11px] text-gray-300 truncate">
                <?= e($item['title'] ?: ($item['content_type'] ?: 'Post')) ?>
              </span>
            </span>
            <?php if (!empty($item['publish_time'])): ?>
            <span class="block text-[10px] text-gray-400 pl-2.5"><?= substr($item['publish_time'], 0, 5) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>

        <?php if (count($dayItems) > 3): ?>
          <p class="text-[10px] text-gray-400 pl-1">+ <?= count($dayItems) - 3 ?></p>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php if ((int) $total === 0): ?>
<div class="card-solid p-8 mt-4 text-center">
  <p class="text-sm text-gray-400"><?= t('portal.calendar.empty') ?></p>
</div>
<?php endif; ?>

<?php view_end(); ?>
