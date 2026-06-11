<?php view_layout('app'); view_start('title'); ?>Campanha: <?= e($campaign['name']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="/trafego" class="hover:text-gray-300">Tráfego Pago</a>
  <span>/</span>
  <span class="text-gray-300"><?= e($campaign['name']) ?></span>
</nav>

<div class="flex items-start justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white"><?= e($campaign['name']) ?></h1>
    <p class="text-sm text-gray-400 mt-1">
      <?= e($campaign['account_name']) ?>
      <?php if ($campaign['objective']): ?>
        <span class="mx-1 text-gray-600">·</span>
        <span class="text-gray-500">Objetivo: <?= e($campaign['objective']) ?></span>
      <?php endif; ?>
    </p>
  </div>
  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
    <?= $campaign['status'] === 'active' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400' ?>">
    <?= ucfirst($campaign['status']) ?>
  </span>
</div>

<!-- Filtro período -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <input type="date" name="since" value="<?= e($since) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <span class="text-gray-500 text-sm">até</span>
  <input type="date" name="until" value="<?= e($until) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
  <?php
  $kpis = [
    ['label' => 'Investimento',  'value' => 'R$ ' . number_format($totals['spend'], 2, ',', '.'),  'color' => 'text-violet-300'],
    ['label' => 'Impressões',    'value' => number_format($totals['impressions'], 0, ',', '.'),     'color' => 'text-blue-300'],
    ['label' => 'Cliques',       'value' => number_format($totals['clicks'], 0, ',', '.'),          'color' => 'text-cyan-300'],
    ['label' => 'Conversões',    'value' => number_format($totals['conversions'], 0, ',', '.'),     'color' => 'text-green-300'],
    ['label' => 'CPC médio',     'value' => 'R$ ' . number_format($totals['cpc'], 2, ',', '.'),    'color' => 'text-yellow-300'],
    ['label' => 'CPM médio',     'value' => 'R$ ' . number_format($totals['cpm'], 2, ',', '.'),    'color' => 'text-orange-300'],
  ];
  foreach ($kpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500 mb-1"><?= $k['label'] ?></p>
    <p class="text-lg font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Conjuntos de anúncio -->
<div class="card overflow-hidden mb-6">
  <div class="p-4 border-b border-white/[0.06]">
    <h2 class="text-sm font-semibold text-gray-300">Conjuntos de anúncio</h2>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/[0.04]">
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conjunto</th>
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Objetivo</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Orçamento/dia</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Investido</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Impressões</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliques</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">CPC</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conv.</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">ROAS</th>
          <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.03]">
        <?php foreach ($adSets as $s): ?>
        <?php
          $sc = $s['status'] === 'active' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400';
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-4 py-3">
            <a href="/trafego/conjuntos/<?= $s['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
               class="font-medium text-white hover:text-violet-300 transition-colors">
              <?= e($s['name']) ?>
            </a>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs"><?= e($s['optimization_goal'] ?? '—') ?></td>
          <td class="px-4 py-3 text-right text-gray-300">
            <?= $s['daily_budget'] ? 'R$ ' . number_format((float)$s['daily_budget'], 2, ',', '.') : '—' ?>
          </td>
          <td class="px-4 py-3 text-right font-medium text-white">
            R$ <?= number_format((float)$s['spend'], 2, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)$s['impressions'], 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)$s['clicks'], 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300">R$ <?= number_format((float)$s['cpc'], 2, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)$s['conversions'], 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right <?= (float)$s['roas'] >= 1 ? 'text-green-400' : 'text-red-400' ?>">
            <?= number_format((float)$s['roas'], 2, ',', '.') ?>x
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>">
              <?= ucfirst($s['status']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/trafego/conjuntos/<?= $s['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
               class="text-xs text-violet-400 hover:text-violet-300">Anúncios &rarr;</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($adSets)): ?>
        <tr><td colspan="11" class="px-4 py-10 text-center text-gray-500">Nenhum conjunto no período.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php view_end(); ?>
