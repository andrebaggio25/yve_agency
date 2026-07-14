<?php view_layout('app'); view_start('title'); ?>Tráfego Pago<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Tráfego Pago</h1>
    <p class="text-sm text-gray-400 mt-0.5">Visão geral de todas as contas de anúncios</p>
  </div>
  <div class="flex gap-3">
    <a href="/trafego/contas" class="btn-secondary text-sm px-4 py-2">Contas conectadas</a>
    <a href="/trafego/contas/nova" class="btn-primary text-sm px-4 py-2">+ Conectar conta</a>
  </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card p-12 text-center">
  <svg class="w-12 h-12 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
  </svg>
  <p class="text-gray-400 mb-4">Nenhuma conta de anúncios conectada ainda.</p>
  <a href="/trafego/contas/nova" class="btn-primary px-6 py-2.5 text-sm">Conectar conta Meta Ads</a>
</div>
<?php return; endif; ?>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <div class="flex items-center gap-2">
    <label class="text-sm text-gray-400">De</label>
    <input type="date" name="since" value="<?= e($since) ?>"
           class="input-field text-sm py-1.5 px-3 w-40">
  </div>
  <div class="flex items-center gap-2">
    <label class="text-sm text-gray-400">Até</label>
    <input type="date" name="until" value="<?= e($until) ?>"
           class="input-field text-sm py-1.5 px-3 w-40">
  </div>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
  <?php
  $kpis = [
    ['label' => 'Investimento',   'value' => 'R$ ' . number_format($totals['spend'], 2, ',', '.'),        'color' => 'text-brand-300'],
    ['label' => 'Impressões',     'value' => number_format($totals['impressions'], 0, ',', '.'),           'color' => 'text-blue-300'],
    ['label' => 'Cliques',        'value' => number_format($totals['clicks'], 0, ',', '.'),                'color' => 'text-cyan-300'],
    ['label' => 'Conversões',     'value' => number_format($totals['conversions'], 0, ',', '.'),           'color' => 'text-green-300'],
    ['label' => 'CPC médio',      'value' => 'R$ ' . number_format($totals['cpc'], 2, ',', '.'),           'color' => 'text-yellow-300'],
    ['label' => 'CPM médio',      'value' => 'R$ ' . number_format($totals['cpm'], 2, ',', '.'),           'color' => 'text-orange-300'],
    ['label' => 'ROAS médio',     'value' => number_format($totals['roas'], 2, ',', '.') . 'x',           'color' => 'text-pink-300'],
  ];
  foreach ($kpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500 mb-1"><?= $k['label'] ?></p>
    <p class="text-lg font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Gráfico de gasto diário -->
<?php if (!empty($dailyChart)): ?>
<div class="card p-5 mb-6" x-data="trafficChart(<?= json_encode($dailyChart) ?>)">
  <h2 class="text-sm font-medium text-gray-300 mb-4">Investimento diário (primeira conta)</h2>
  <canvas id="trafficSpendChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- Tabela de campanhas -->
<div class="card overflow-hidden">
  <div class="p-4 border-b border-white/[0.06] flex items-center justify-between">
    <h2 class="text-sm font-medium text-gray-300">Campanhas</h2>
    <span class="text-xs text-gray-500"><?= count($campaigns) ?> campanha<?= count($campaigns) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/[0.04]">
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Campanha</th>
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conta / Cliente</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Investido</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Impressões</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliques</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">CPC</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">CPM</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conv.</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">ROAS</th>
          <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.03]">
        <?php foreach ($campaigns as $c): ?>
        <?php
          $statusColors = [
            'active'   => 'bg-green-500/15 text-green-400',
            'paused'   => 'bg-yellow-500/15 text-yellow-400',
            'archived' => 'bg-gray-500/15 text-gray-400',
            'deleted'  => 'bg-red-500/15 text-red-400',
          ];
          $sc = $statusColors[$c['status']] ?? 'bg-gray-500/15 text-gray-400';
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-4 py-3">
            <a href="/trafego/campanhas/<?= $c['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
               class="font-medium text-white hover:text-brand-300 transition-colors">
              <?= e($c['name']) ?>
            </a>
          </td>
          <td class="px-4 py-3 text-gray-400">
            <?= e($c['account_name']) ?>
            <?php if ($c['client_name']): ?>
              <span class="text-gray-600 mx-1">·</span>
              <span class="text-gray-500 text-xs"><?= e($c['client_name']) ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right font-medium text-white">
            R$ <?= number_format((float)$c['spend'], 2, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300">
            <?= number_format((int)$c['impressions'], 0, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300">
            <?= number_format((int)$c['clicks'], 0, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300">
            R$ <?= number_format((float)$c['cpc'], 2, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300">
            R$ <?= number_format((float)$c['cpm'], 2, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300">
            <?= number_format((int)$c['conversions'], 0, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right <?= (float)$c['roas'] >= 1 ? 'text-green-400' : 'text-red-400' ?>">
            <?= number_format((float)$c['roas'], 2, ',', '.') ?>x
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>">
              <?= ucfirst($c['status']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/trafego/campanhas/<?= $c['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
               class="text-xs text-brand-400 hover:text-brand-300">Ver &rarr;</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($campaigns)): ?>
        <tr><td colspan="11" class="px-4 py-10 text-center text-gray-500">
          Nenhuma campanha no período. <a href="/trafego/contas" class="text-brand-400 hover:underline">Sincronize uma conta</a>.
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function trafficChart(rows) {
  return {
    init() {
      const labels = rows.map(r => r.date);
      const spend  = rows.map(r => parseFloat(r.spend) || 0);
      new Chart(document.getElementById('trafficSpendChart'), {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Investimento (R$)',
            data: spend,
            backgroundColor: 'rgba(198,161,91,0.5)',
            borderColor: 'rgba(198,161,91,1)',
            borderWidth: 1,
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 } } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 }, callback: v => 'R$ ' + v.toLocaleString('pt-BR') } }
          }
        }
      });
    }
  }
}
</script>

<?php view_end(); ?>
