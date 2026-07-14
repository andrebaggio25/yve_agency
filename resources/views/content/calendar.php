<?php view_layout('app'); view_start('title'); ?>Calendário<?php view_end(); ?>
<?php view_start('content'); ?>
<?php
/**
 * PROD-04 — calendário de conteúdo.
 *
 * Classes completas nos mapas (nunca concatenadas): o purge do Tailwind só
 * enxerga o que está literal no arquivo.
 */
$statusMeta = [
    'draft'    => ['label' => 'Rascunho',  'dot' => 'bg-gray-500',    'cls' => 'border-gray-500/30 bg-gray-500/[0.07]'],
    'sent'     => ['label' => 'Enviado',   'dot' => 'bg-blue-400',    'cls' => 'border-blue-500/30 bg-blue-500/[0.07]'],
    'revision' => ['label' => 'Revisão',   'dot' => 'bg-amber-400',   'cls' => 'border-amber-500/30 bg-amber-500/[0.07]'],
    'approved' => ['label' => 'Aprovado',  'dot' => 'bg-emerald-400', 'cls' => 'border-emerald-500/30 bg-emerald-500/[0.07]'],
    'rejected' => ['label' => 'Rejeitado', 'dot' => 'bg-rose-400',    'cls' => 'border-rose-500/30 bg-rose-500/[0.07]'],
];
$fallback = ['label' => '—', 'dot' => 'bg-gray-500', 'cls' => 'border-white/10 bg-white/[0.03]'];

// Grade: começa no domingo da semana do dia 1 e vai até fechar a última semana.
$firstTs   = strtotime($firstDay);
$lastTs    = strtotime($lastDay);
$gridStart = strtotime('-' . (int) date('w', $firstTs) . ' days', $firstTs);
$gridEnd   = strtotime('+' . (6 - (int) date('w', $lastTs)) . ' days', $lastTs);
$today     = date('Y-m-d');
$monthNum  = (int) date('n', $firstTs);

$qs = static function (array $extra) use ($filters, $month): string {
    return '?' . http_build_query(array_merge(['month' => $month], array_filter($filters), $extra));
};
?>

<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
  <div>
    <h1 class="text-xl font-bold text-white"><?= e($monthLabel) ?></h1>
    <p class="text-sm text-gray-500 mt-0.5">
      <?= (int) $total ?> <?= (int) $total === 1 ? 'publicação planejada' : 'publicações planejadas' ?> neste mês
    </p>
  </div>

  <div class="flex items-center gap-2 flex-wrap">
    <a href="/conteudo" class="btn-secondary text-sm px-4 py-2">Ver em lista</a>
    <a href="/conteudo/criar" class="btn-primary text-sm px-4 py-2">Novo plano</a>
  </div>
</div>

<!-- Navegação + filtro -->
<div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
  <div class="flex items-center gap-1">
    <a href="/conteudo/calendario<?= $qs(['month' => $prevMonth]) ?>"
       class="btn-secondary text-sm px-3 py-2" aria-label="Mês anterior">←</a>
    <a href="/conteudo/calendario<?= $qs(['month' => date('Y-m')]) ?>"
       class="btn-secondary text-sm px-3 py-2">Hoje</a>
    <a href="/conteudo/calendario<?= $qs(['month' => $nextMonth]) ?>"
       class="btn-secondary text-sm px-3 py-2" aria-label="Próximo mês">→</a>
  </div>

  <form method="GET" class="flex items-end gap-2">
    <input type="hidden" name="month" value="<?= e($month) ?>">
    <div>
      <label class="sr-only" for="client_id">Cliente</label>
      <select id="client_id" name="client_id" class="input-field w-52 py-2" onchange="this.form.submit()">
        <option value="">Todos os clientes</option>
        <?php foreach ($clientList as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<!-- Legenda -->
<div class="flex items-center gap-4 mb-3 flex-wrap text-xs text-gray-500">
  <?php foreach ($statusMeta as $meta): ?>
    <span class="inline-flex items-center gap-1.5">
      <span class="w-2 h-2 rounded-full <?= $meta['dot'] ?>"></span><?= e($meta['label']) ?>
    </span>
  <?php endforeach; ?>
</div>

<!-- Grade do mês. Rola horizontalmente no celular em vez de espremer o dia. -->
<div class="overflow-x-auto">
  <div class="min-w-[46rem]">
    <div class="grid grid-cols-7 gap-px mb-px">
      <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $dow): ?>
        <div class="px-2 py-2 text-center text-xs uppercase tracking-wide text-gray-500"><?= e($dow) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-7 gap-1.5">
      <?php for ($ts = $gridStart; $ts <= $gridEnd; $ts = strtotime('+1 day', $ts)):
        $date      = date('Y-m-d', $ts);
        $isMonth   = (int) date('n', $ts) === $monthNum;
        $isToday   = $date === $today;
        $dayItems  = $byDay[$date] ?? [];
      ?>
      <div class="min-h-[7rem] rounded-xl border p-2 transition-colors
                  <?= $isMonth ? 'border-white/[0.06] bg-white/[0.02]' : 'border-transparent bg-transparent' ?>
                  <?= $isToday ? 'ring-1 ring-brand-500/50' : '' ?>">

        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs font-medium <?= $isToday ? 'text-brand-300' : ($isMonth ? 'text-gray-400' : 'text-gray-700') ?>">
            <?= (int) date('j', $ts) ?>
          </span>
          <?php if (count($dayItems) > 3): ?>
            <span class="text-[10px] text-gray-600"><?= count($dayItems) ?></span>
          <?php endif; ?>
        </div>

        <?php foreach (array_slice($dayItems, 0, 3) as $item):
          $m = $statusMeta[$item['status']] ?? $fallback;
        ?>
          <a href="/conteudo/<?= (int) $item['content_plan_id'] ?>#item-<?= (int) $item['id'] ?>"
             class="block mb-1 rounded-lg border px-1.5 py-1 <?= $m['cls'] ?> hover:brightness-125 transition-all"
             title="<?= e(($item['client_name'] ?? '') . ' — ' . ($item['title'] ?: 'Sem título') . ' (' . $m['label'] . ')') ?>">
            <span class="flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 <?= $m['dot'] ?>"></span>
              <span class="text-[11px] text-gray-300 truncate">
                <?= e($item['title'] ?: ($item['content_type'] ?: 'Publicação')) ?>
              </span>
            </span>
            <span class="block text-[10px] text-gray-600 truncate pl-2.5"><?= e($item['client_name'] ?? '') ?></span>
          </a>
        <?php endforeach; ?>

        <?php if (count($dayItems) > 3): ?>
          <p class="text-[10px] text-gray-600 pl-1">+ <?= count($dayItems) - 3 ?> mais</p>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php if ((int) $total === 0): ?>
  <!-- Estado vazio: nunca um mês em branco sem explicação -->
  <div class="card p-8 mt-4 text-center">
    <p class="text-sm text-gray-400">Nenhuma publicação planejada para este mês</p>
    <p class="text-xs text-gray-600 mt-1">
      Crie um plano de conteúdo — os itens com data de publicação aparecem aqui automaticamente.
    </p>
  </div>
<?php endif; ?>

<?php view_end(); ?>
