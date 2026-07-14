<?php view_layout('app'); view_start('title'); ?>Relatório Executivo<?php view_end(); ?>
<?php view_start('head'); ?>
<script defer src="<?= asset('/js/vendor/chart.umd.min.js') ?>"></script>
<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$fmtMoney  = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtInt    = fn($v) => number_format((int)$v, 0, ',', '.');
$fmtFloat  = fn($v, $d = 2) => number_format((float)$v, $d, ',', '.');
$statusLabels = ['todo' => 'A Fazer', 'in_progress' => 'Em Andamento', 'review' => 'Revisão', 'done' => 'Concluída'];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Relatório Executivo</h1>
    <p class="text-sm text-gray-400 mt-0.5">Visão consolidada de todos os clientes</p>
  </div>
  <!-- Period filter -->
  <form method="GET" class="flex items-center gap-2">
    <input type="date" name="since" value="<?= e($since) ?>" class="input-field text-sm py-1.5 px-3 w-40">
    <span class="text-gray-500 text-sm">até</span>
    <input type="date" name="until" value="<?= e($until) ?>" class="input-field text-sm py-1.5 px-3 w-40">
    <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Aplicar</button>
  </form>
</div>

<!-- ── Row 1: Main KPIs ─────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
  <?php
  $kpis = [
    ['Clientes Ativos',    count(array_filter($clients, fn($c) => $c['status'] === 'active')), null, 'text-brand-300'],
    ['Faturado Total',     $fmtMoney($financialKpis['billed_total'] ?? 0), null, 'text-emerald-300'],
    ['Recebido',           $fmtMoney($financialKpis['received_total'] ?? 0), null, 'text-green-300'],
    ['Em Aberto',          $fmtMoney($financialKpis['pending_total'] ?? 0), null, 'text-yellow-300'],
    ['Investimento Ads',   $fmtMoney($adsKpis['total_spend'] ?? 0), 'Período selecionado', 'text-blue-300'],
    ['Planos Aguardando',  (int)($contentKpis['awaiting'] ?? 0), 'Aprovação pendente', 'text-orange-300'],
  ];
  foreach ($kpis as [$label, $value, $sub, $color]):
  ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500 mb-1"><?= $label ?></p>
    <p class="text-xl font-bold <?= $color ?>"><?= $value ?></p>
    <?php if ($sub): ?><p class="text-xs text-gray-600 mt-0.5"><?= $sub ?></p><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Row 2: Revenue chart + Tasks + Organic ─────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

  <!-- Revenue trend -->
  <div class="card p-5 col-span-2">
    <h2 class="text-sm font-semibold text-gray-300 mb-4">Receita — últimos 12 meses</h2>
    <div style="height:220px" x-data x-init="
      new Chart($el.querySelector('canvas'), {
        type: 'bar',
        data: {
          labels: <?= json_encode(array_column($revenueTrend, 'month')) ?>,
          datasets: [
            {
              label: 'Recebido',
              data: <?= json_encode(array_map(fn($r) => (float)$r['received'], $revenueTrend)) ?>,
              backgroundColor: 'rgba(198,161,91,0.7)',
              borderRadius: 4,
            },
            {
              label: 'Faturado',
              data: <?= json_encode(array_map(fn($r) => (float)$r['billed'], $revenueTrend)) ?>,
              backgroundColor: 'rgba(255,255,255,0.07)',
              borderRadius: 4,
            }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { labels: { color: '#9ca3af', font: { size: 11 } } } },
          scales: {
            x: { ticks: { color: '#6b7280', font: { size: 10 } }, grid: { display: false } },
            y: { ticks: { color: '#6b7280', font: { size: 10 },
                          callback: v => 'R$ ' + Intl.NumberFormat('pt-BR',{notation:'compact'}).format(v) },
                 grid: { color: 'rgba(255,255,255,0.04)' } }
          }
        }
      })
    "><canvas></canvas></div>
  </div>

  <!-- Tasks + Content summary -->
  <div class="space-y-4">
    <!-- Tasks by status -->
    <div class="card p-5">
      <h2 class="text-sm font-semibold text-gray-300 mb-3">Tarefas</h2>
      <div class="space-y-2">
        <?php
        $taskColors = ['todo' => 'bg-gray-500', 'in_progress' => 'bg-blue-500', 'review' => 'bg-yellow-500', 'done' => 'bg-green-500'];
        $totalTasks = max(1, array_sum($taskKpis));
        foreach (['todo','in_progress','review','done'] as $s):
          $n = (int)($taskKpis[$s] ?? 0);
          $pct = round($n / $totalTasks * 100);
        ?>
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="text-gray-400"><?= $statusLabels[$s] ?></span>
            <span class="text-gray-300 font-medium"><?= $n ?></span>
          </div>
          <div class="h-1.5 rounded-full bg-white/[0.06]">
            <div class="h-full rounded-full <?= $taskColors[$s] ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Organic summary -->
    <div class="card p-5">
      <h2 class="text-sm font-semibold text-gray-300 mb-3">Orgânico <span class="text-gray-600 font-normal text-xs ml-1">período</span></h2>
      <dl class="space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500">Alcance</dt><dd class="text-gray-300 font-medium"><?= $fmtInt($organicKpis['total_reach'] ?? 0) ?></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">Impressões</dt><dd class="text-gray-300 font-medium"><?= $fmtInt($organicKpis['total_impressions'] ?? 0) ?></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">Engajamentos</dt><dd class="text-gray-300 font-medium"><?= $fmtInt($organicKpis['total_engagement'] ?? 0) ?></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">Publicações</dt><dd class="text-gray-300 font-medium"><?= $fmtInt($organicKpis['total_posts'] ?? 0) ?></dd></div>
      </dl>
    </div>
  </div>
</div>

<!-- ── Row 3: Ads KPIs ────────────────────────────────────────────────── -->
<?php if (\App\Support\Auth::can('ads_metrics.view')): ?>
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
  <?php
  $adsCards = [
    ['Investimento', $fmtMoney($adsKpis['total_spend'] ?? 0)],
    ['Impressões',   $fmtInt($adsKpis['total_impressions'] ?? 0)],
    ['Cliques',      $fmtInt($adsKpis['total_clicks'] ?? 0)],
    ['Conversões',   $fmtInt($adsKpis['total_conversions'] ?? 0)],
    ['ROAS Médio',   $fmtFloat($adsKpis['avg_roas'] ?? 0) . 'x'],
  ];
  foreach ($adsCards as [$l, $v]):
  ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500"><?= $l ?></p>
    <p class="text-lg font-bold text-white mt-1"><?= $v ?></p>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Row 4: Per-client summary table ────────────────────────────────── -->
<h2 class="text-sm font-semibold text-gray-300 mb-3">Resumo por Cliente</h2>
<div class="card overflow-hidden mb-6">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Faturado</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Recebido</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Pendente</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Planos Aguard.</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Tarefas Abertas</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php if (empty($clientSummary)): ?>
      <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">Nenhum cliente ativo.</td></tr>
      <?php endif; ?>
      <?php foreach ($clientSummary as $cs): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3 font-medium text-white"><?= e($cs['name']) ?></td>
        <td class="px-5 py-3 text-right text-gray-300"><?= $fmtMoney($cs['invoiced']) ?></td>
        <td class="px-5 py-3 text-right text-emerald-400"><?= $fmtMoney($cs['paid']) ?></td>
        <td class="px-5 py-3 text-right <?= $cs['pending'] > 0 ? 'text-yellow-400' : 'text-gray-500' ?>">
          <?= $fmtMoney($cs['pending']) ?>
        </td>
        <td class="px-5 py-3 text-center">
          <?php if ($cs['plans_awaiting'] > 0): ?>
          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-orange-500/20 text-orange-300">
            <?= $cs['plans_awaiting'] ?>
          </span>
          <?php else: ?>
          <span class="text-gray-600">—</span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3 text-center">
          <?php if ($cs['open_tasks'] > 0): ?>
          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-blue-500/20 text-blue-300">
            <?= $cs['open_tasks'] ?>
          </span>
          <?php else: ?>
          <span class="text-gray-600">0</span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3 text-right">
          <a href="/relatorio-executivo/cliente/<?= $cs['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
             target="_blank"
             class="text-xs text-brand-400 hover:text-brand-300 font-medium">
            Relatório PDF →
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ── Row 5: Top campaigns ───────────────────────────────────────────── -->
<?php if (!empty($topCampaigns) && \App\Support\Auth::can('ads_metrics.view')): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">Top Campanhas no Período</h2>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Campanha</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Investimento</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Impressões</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliques</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">ROAS</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($topCampaigns as $camp): ?>
      <tr class="hover:bg-white/[0.02]">
        <td class="px-5 py-3 text-white font-medium max-w-xs truncate"><?= e($camp['name']) ?></td>
        <td class="px-5 py-3 text-gray-400 text-xs"><?= e($camp['client_name'] ?? '—') ?></td>
        <td class="px-5 py-3 text-right text-gray-300"><?= $fmtMoney($camp['spend']) ?></td>
        <td class="px-5 py-3 text-right text-gray-400"><?= $fmtInt($camp['impressions']) ?></td>
        <td class="px-5 py-3 text-right text-gray-400"><?= $fmtInt($camp['clicks']) ?></td>
        <td class="px-5 py-3 text-right font-medium <?= $camp['roas'] >= 2 ? 'text-green-400' : ($camp['roas'] >= 1 ? 'text-yellow-400' : 'text-red-400') ?>">
          <?= $fmtFloat($camp['roas']) ?>x
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php view_end(); ?>
