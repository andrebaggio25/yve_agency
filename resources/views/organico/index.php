<?php view_layout('app'); view_start('title'); ?>Métricas Orgânicas<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Métricas Orgânicas</h1>
    <p class="text-sm text-gray-400 mt-0.5">Desempenho de páginas e perfis conectados</p>
  </div>
  <div class="flex gap-3">
    <a href="/organico/contas" class="btn-secondary text-sm px-4 py-2">Contas conectadas</a>
    <a href="/organico/conectar" class="btn-primary text-sm px-4 py-2">+ Conectar conta</a>
  </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card p-12 text-center">
  <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-pink-500/10 flex items-center justify-center">
    <svg class="w-7 h-7 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
  </div>
  <p class="text-gray-400 mb-4">Nenhuma conta orgânica conectada.</p>
  <a href="/organico/conectar" class="btn-primary px-6 py-2.5 text-sm">Conectar Instagram ou Facebook</a>
</div>
<?php return; endif; ?>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <input aria-label="Data inicial" type="date" name="since" value="<?= e($since) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <span class="text-gray-400 text-sm">até</span>
  <input aria-label="Data final" type="date" name="until" value="<?= e($until) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- KPIs globais -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
  <?php
  $kpis = [
    ['label' => 'Total seguidores', 'value' => number_format($totals['followers'], 0, ',', '.'), 'color' => 'text-pink-300'],
    ['label' => 'Posts no período', 'value' => number_format($totals['posts'], 0, ',', '.'),     'color' => 'text-brand-300'],
    ['label' => 'Alcance total',    'value' => number_format($totals['reach'], 0, ',', '.'),     'color' => 'text-blue-300'],
    ['label' => 'Impressões',       'value' => number_format($totals['impressions'], 0, ',', '.'), 'color' => 'text-cyan-300'],
    ['label' => 'Curtidas',         'value' => number_format($totals['likes'], 0, ',', '.'),     'color' => 'text-red-300'],
    ['label' => 'Eng. médio',       'value' => number_format($totals['avg_er'], 2, ',', '.') . '%', 'color' => 'text-green-300'],
  ];
  foreach ($kpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-400 mb-1"><?= $k['label'] ?></p>
    <p class="text-lg font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabela de contas com métricas -->
<div class="card overflow-hidden">
  <div class="p-4 border-b border-white/[0.06] flex items-center justify-between">
    <h2 class="text-sm font-medium text-gray-300">Contas conectadas</h2>
    <span class="text-xs text-gray-400"><?= count($accounts) ?> conta<?= count($accounts) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/[0.04]">
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Conta</th>
          <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Cliente</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Seguidores</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Posts</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Alcance</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Impressões</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Curtidas</th>
          <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Eng. médio</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.03]">
        <?php
        $overviewById = array_column($overview, null, 'id');
        foreach ($accounts as $a):
          $ov = $overviewById[$a['id']] ?? [];
          $platform = $a['platform'];
          $platformColors = ['instagram' => 'text-pink-400', 'facebook' => 'text-blue-400', 'linkedin' => 'text-sky-400'];
          $pc = $platformColors[$platform] ?? 'text-gray-400';
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <?php if ($a['profile_picture_url']): ?>
              <img src="<?= e($a['profile_picture_url']) ?>" alt=""
                   class="w-8 h-8 rounded-full object-cover bg-white/5"
                   onerror="this.style.display='none'">
              <?php else: ?>
              <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-xs text-gray-400">
                <?= mb_strtoupper(mb_substr($a['name'], 0, 1)) ?>
              </div>
              <?php endif; ?>
              <div>
                <p class="font-medium text-white"><?= e($a['name']) ?></p>
                <p class="text-xs <?= $pc ?>">
                  <?= ucfirst($platform) ?>
                  <?= $a['username'] ? ' · @' . e($a['username']) : '' ?>
                </p>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-gray-400"><?= e($a['client_name'] ?? '—') ?></td>
          <td class="px-4 py-3 text-right font-medium text-white">
            <?= number_format((int)$a['followers_count'], 0, ',', '.') ?>
          </td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)($ov['total_posts'] ?? 0), 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)($ov['total_reach'] ?? 0), 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)($ov['total_impressions'] ?? 0), 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><?= number_format((int)($ov['total_likes'] ?? 0), 0, ',', '.') ?></td>
          <td class="px-4 py-3 text-right <?= (float)($ov['avg_engagement_rate'] ?? 0) >= 3 ? 'text-green-400' : 'text-gray-300' ?>">
            <?= number_format((float)($ov['avg_engagement_rate'] ?? 0), 2, ',', '.') ?>%
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/organico/contas/<?= $a['id'] ?>"
               class="text-xs text-brand-400 hover:text-brand-300">Ver &rarr;</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php view_end(); ?>
