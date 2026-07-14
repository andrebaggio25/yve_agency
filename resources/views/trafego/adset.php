<?php view_layout('app'); view_start('title'); ?>Conjunto: <?= e($adSet['name']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
  <a href="/trafego" class="hover:text-gray-300">Tráfego Pago</a>
  <span>/</span>
  <a href="/trafego/campanhas/<?= $campaign['id'] ?>?since=<?= e($since) ?>&until=<?= e($until) ?>"
     class="hover:text-gray-300"><?= e($campaign['name']) ?></a>
  <span>/</span>
  <span class="text-gray-300"><?= e($adSet['name']) ?></span>
</nav>

<div class="flex items-start justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white"><?= e($adSet['name']) ?></h1>
    <p class="text-sm text-gray-400 mt-1">
      Conjunto de anúncio · <?= e($campaign['name']) ?>
      <?php if ($adSet['optimization_goal']): ?>
        <span class="mx-1 text-gray-400">·</span>
        <?= e($adSet['optimization_goal']) ?>
      <?php endif; ?>
    </p>
  </div>
  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
    <?= $adSet['status'] === 'active' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400' ?>">
    <?= ucfirst($adSet['status']) ?>
  </span>
</div>

<!-- Filtro período -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <input aria-label="Data inicial" type="date" name="since" value="<?= e($since) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <span class="text-gray-400 text-sm">até</span>
  <input aria-label="Data final" type="date" name="until" value="<?= e($until) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
  <?php
  $kpis = [
    ['label' => 'Investimento',  'value' => 'R$ ' . number_format($totals['spend'], 2, ',', '.'),   'color' => 'text-brand-300'],
    ['label' => 'Impressões',    'value' => number_format($totals['impressions'], 0, ',', '.'),      'color' => 'text-blue-300'],
    ['label' => 'Cliques',       'value' => number_format($totals['clicks'], 0, ',', '.'),           'color' => 'text-cyan-300'],
    ['label' => 'Conversões',    'value' => number_format($totals['conversions'], 0, ',', '.'),      'color' => 'text-green-300'],
    ['label' => 'CPC médio',     'value' => 'R$ ' . number_format($totals['cpc'], 2, ',', '.'),     'color' => 'text-yellow-300'],
    ['label' => 'CPM médio',     'value' => 'R$ ' . number_format($totals['cpm'], 2, ',', '.'),     'color' => 'text-orange-300'],
  ];
  foreach ($kpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-400 mb-1"><?= $k['label'] ?></p>
    <p class="text-lg font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Anúncios (cards com criativo) -->
<h2 class="text-sm font-semibold text-gray-300 mb-4">Anúncios (<?= count($ads) ?>)</h2>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
  <?php foreach ($ads as $ad): ?>
  <?php
    $statusColor = $ad['status'] === 'active' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-400';
    $roasColor   = (float)$ad['roas'] >= 1 ? 'text-green-400' : ((float)$ad['roas'] > 0 ? 'text-yellow-400' : 'text-gray-400');
  ?>
  <div class="card flex flex-col overflow-hidden">
    <!-- Creative preview -->
    <?php if ($ad['image_url'] || $ad['thumbnail_url']): ?>
    <div class="relative bg-black/30 flex-shrink-0" style="height:180px;">
      <img src="<?= e($ad['image_url'] ?? $ad['thumbnail_url']) ?>" alt=""
           class="w-full h-full object-cover opacity-90"
           onerror="this.parentElement.style.display='none'">
      <?php if ($ad['creative_type'] === 'video'): ?>
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-12 h-12 bg-black/60 rounded-full flex items-center justify-center">
          <svg class="w-6 h-6 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z"/>
          </svg>
        </div>
      </div>
      <?php endif; ?>
      <span class="absolute top-2 right-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
        <?= ucfirst($ad['status']) ?>
      </span>
    </div>
    <?php else: ?>
    <div class="bg-white/[0.03] flex-shrink-0 flex items-center justify-center" style="height:120px;">
      <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <span class="absolute top-2 right-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
        <?= ucfirst($ad['status']) ?>
      </span>
    </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="p-4 flex flex-col flex-1">
      <div class="mb-3">
        <p class="font-medium text-white text-sm leading-tight"><?= e($ad['name']) ?></p>
        <?php if ($ad['headline']): ?>
        <p class="text-gray-300 text-xs mt-1 font-medium"><?= e($ad['headline']) ?></p>
        <?php endif; ?>
        <?php if ($ad['call_to_action']): ?>
        <span class="mt-1.5 inline-block px-2 py-0.5 bg-blue-500/15 text-blue-400 text-xs rounded">
          <?= e(str_replace('_', ' ', $ad['call_to_action'])) ?>
        </span>
        <?php endif; ?>
      </div>

      <!-- Métricas do anúncio -->
      <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs mt-auto pt-3 border-t border-white/[0.06]">
        <div>
          <p class="text-gray-400">Investido</p>
          <p class="font-semibold text-brand-300">R$ <?= number_format((float)$ad['spend'], 2, ',', '.') ?></p>
        </div>
        <div>
          <p class="text-gray-400">Impressões</p>
          <p class="font-semibold text-blue-300"><?= number_format((int)$ad['impressions'], 0, ',', '.') ?></p>
        </div>
        <div>
          <p class="text-gray-400">Cliques</p>
          <p class="font-semibold text-cyan-300"><?= number_format((int)$ad['clicks'], 0, ',', '.') ?></p>
        </div>
        <div>
          <p class="text-gray-400">CPC</p>
          <p class="font-semibold text-yellow-300">R$ <?= number_format((float)$ad['cpc'], 2, ',', '.') ?></p>
        </div>
        <div>
          <p class="text-gray-400">Conversões</p>
          <p class="font-semibold text-green-300"><?= number_format((int)$ad['conversions'], 0, ',', '.') ?></p>
        </div>
        <div>
          <p class="text-gray-400">ROAS</p>
          <p class="font-semibold <?= $roasColor ?>">
            <?= (float)$ad['roas'] > 0 ? number_format((float)$ad['roas'], 2, ',', '.') . 'x' : '—' ?>
          </p>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($ads)): ?>
  <div class="col-span-full card p-10 text-center text-gray-400">
    Nenhum anúncio no período.
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
