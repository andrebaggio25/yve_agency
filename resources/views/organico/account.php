<?php view_layout('app'); view_start('title'); ?>@<?= e($account['username'] ?? $account['name']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
  <a href="/organico" class="hover:text-gray-300">Orgânico</a>
  <span>/</span>
  <span class="text-gray-300"><?= e($account['name']) ?></span>
</nav>

<!-- Header da conta -->
<div class="card p-5 mb-6 flex items-start gap-5">
  <?php if ($account['profile_picture_url']): ?>
  <img src="<?= e($account['profile_picture_url']) ?>" alt=""
       class="w-16 h-16 rounded-full object-cover flex-shrink-0 bg-white/5"
       onerror="this.style.display='none'">
  <?php else: ?>
  <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center text-xl text-gray-400 flex-shrink-0">
    <?= mb_strtoupper(mb_substr($account['name'], 0, 1)) ?>
  </div>
  <?php endif; ?>
  <div class="flex-1 min-w-0">
    <div class="flex items-center gap-2 flex-wrap">
      <h1 class="text-lg font-semibold text-white"><?= e($account['name']) ?></h1>
      <?php
        $pc = ['instagram' => 'text-pink-400 bg-pink-500/10', 'facebook' => 'text-blue-400 bg-blue-500/10'];
        $c  = $pc[$account['platform']] ?? 'text-gray-400 bg-gray-500/10';
      ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $c ?>">
        <?= ucfirst($account['platform']) ?>
      </span>
    </div>
    <?php if ($account['username']): ?>
    <p class="text-sm text-gray-400">@<?= e($account['username']) ?></p>
    <?php endif; ?>
    <?php if ($account['biography']): ?>
    <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?= e($account['biography']) ?></p>
    <?php endif; ?>
    <div class="flex items-center gap-5 mt-3 text-sm">
      <div><span class="font-semibold text-white"><?= number_format($account['followers_count'], 0, ',', '.') ?></span> <span class="text-gray-400">seguidores</span></div>
      <div><span class="font-semibold text-white"><?= number_format($account['following_count'], 0, ',', '.') ?></span> <span class="text-gray-400">seguindo</span></div>
      <div><span class="font-semibold text-white"><?= number_format($account['media_count'], 0, ',', '.') ?></span> <span class="text-gray-400">posts</span></div>
    </div>
  </div>
  <div class="text-right flex-shrink-0 text-xs text-gray-400">
    <?php if ($account['last_synced_at']): ?>
    <p>Último sync: <?= date('d/m/Y H:i', strtotime($account['last_synced_at'])) ?></p>
    <?php endif; ?>
    <form method="POST" action="/organico/contas/<?= $account['id'] ?>/sync" class="mt-2">
      <?= csrf_field() ?>
      <button type="submit" class="text-brand-400 hover:text-brand-300 transition-colors">Sincronizar</button>
    </form>
  </div>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
  <input aria-label="Data inicial" type="date" name="since" value="<?= e($since) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <span class="text-gray-400 text-sm">até</span>
  <input aria-label="Data final" type="date" name="until" value="<?= e($until) ?>" class="input-field text-sm py-1.5 px-3 w-40">
  <select aria-label="Ordenar por" name="sort" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="date"        <?= $sortBy === 'date'        ? 'selected' : '' ?>>Mais recentes</option>
    <option value="reach"       <?= $sortBy === 'reach'       ? 'selected' : '' ?>>Maior alcance</option>
    <option value="likes"       <?= $sortBy === 'likes'       ? 'selected' : '' ?>>Mais curtidas</option>
    <option value="impressions" <?= $sortBy === 'impressions' ? 'selected' : '' ?>>Mais impressões</option>
    <option value="engagement"  <?= $sortBy === 'engagement'  ? 'selected' : '' ?>>Maior engajamento</option>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<!-- KPIs do período -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
  <?php
  $kpis = [
    ['label' => 'Posts',       'value' => number_format((int)($summary['total_posts'] ?? 0), 0, ',', '.'),                    'color' => 'text-white'],
    ['label' => 'Alcance',     'value' => number_format((int)($summary['total_reach'] ?? 0), 0, ',', '.'),                    'color' => 'text-blue-300'],
    ['label' => 'Impressões',  'value' => number_format((int)($summary['total_impressions'] ?? 0), 0, ',', '.'),              'color' => 'text-cyan-300'],
    ['label' => 'Curtidas',    'value' => number_format((int)($summary['total_likes'] ?? 0), 0, ',', '.'),                    'color' => 'text-red-300'],
    ['label' => 'Comentários', 'value' => number_format((int)($summary['total_comments'] ?? 0), 0, ',', '.'),                 'color' => 'text-yellow-300'],
    ['label' => 'Salvos',      'value' => number_format((int)($summary['total_saves'] ?? 0), 0, ',', '.'),                    'color' => 'text-green-300'],
    ['label' => 'Video views', 'value' => number_format((int)($summary['total_video_views'] ?? 0), 0, ',', '.'),              'color' => 'text-purple-300'],
    ['label' => 'Eng. médio',  'value' => number_format((float)($summary['avg_engagement_rate'] ?? 0), 2, ',', '.') . '%',    'color' => 'text-pink-300'],
  ];
  foreach ($kpis as $k): ?>
  <div class="card p-3.5">
    <p class="text-xs text-gray-400 mb-1"><?= $k['label'] ?></p>
    <p class="text-base font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Distribuição por tipo -->
<?php
$typeCounts = [
  'Imagens'   => (int)($summary['image_count']    ?? 0),
  'Vídeos'    => (int)($summary['video_count']     ?? 0),
  'Reels'     => (int)($summary['reel_count']      ?? 0),
  'Carrosséis'=> (int)($summary['carousel_count']  ?? 0),
  'Stories'   => (int)($summary['story_count']     ?? 0),
];
$totalTyped = array_sum($typeCounts);
?>
<?php if ($totalTyped > 0): ?>
<div class="card p-5 mb-6">
  <h2 class="text-sm font-medium text-gray-300 mb-4">Distribuição por tipo de conteúdo</h2>
  <div class="flex flex-wrap items-center gap-4">
    <?php foreach ($typeCounts as $label => $count): ?>
    <?php if ($count > 0): ?>
    <div class="flex items-center gap-2">
      <div class="text-lg font-bold text-white"><?= $count ?></div>
      <div>
        <p class="text-xs text-gray-400"><?= $label ?></p>
        <p class="text-xs text-gray-400"><?= $totalTyped > 0 ? round($count / $totalTyped * 100, 0) : 0 ?>%</p>
      </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Gráfico de crescimento de seguidores -->
<?php if (!empty($dailyChart)): ?>
<div class="card p-5 mb-6" x-data="organicChart(<?= e(json_encode($dailyChart)) ?>)">
  <h2 class="text-sm font-medium text-gray-300 mb-4">Alcance diário</h2>
  <canvas id="organicReachChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- Top posts -->
<?php if (!empty($topPosts)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-4">Top posts do período</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
  <?php foreach ($topPosts as $post): ?>
  <?php
    $mediaColors = ['REEL' => 'text-purple-400', 'VIDEO' => 'text-blue-400', 'STORY' => 'text-yellow-400'];
    $mc = $mediaColors[$post['media_type']] ?? 'text-gray-400';
  ?>
  <div class="card overflow-hidden flex flex-col group">
    <!-- Thumbnail -->
    <div class="relative bg-black/30 flex-shrink-0" style="height:200px;">
      <?php if ($post['media_url'] || $post['thumbnail_url']): ?>
      <img src="<?= e($post['thumbnail_url'] ?? $post['media_url']) ?>"
           alt="" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"
           onerror="this.style.display='none'">
      <?php if (in_array($post['media_type'], ['VIDEO','REEL'])): ?>
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-10 h-10 bg-black/60 rounded-full flex items-center justify-center">
          <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z"/>
          </svg>
        </div>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="w-full h-full flex items-center justify-center">
        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <?php endif; ?>
      <!-- Badge tipo -->
      <span class="absolute top-2 left-2 text-xs font-medium px-2 py-0.5 rounded-full bg-black/60 <?= $mc ?>">
        <?= ucfirst(strtolower($post['media_type'] ?? 'POST')) ?>
      </span>
      <?php if ($post['permalink']): ?>
      <a href="<?= e($post['permalink']) ?>" target="_blank"
         class="absolute top-2 right-2 w-7 h-7 bg-black/60 rounded-full flex items-center justify-center hover:bg-black/80 transition-colors">
        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>

    <div class="p-3 flex flex-col flex-1">
      <?php if ($post['caption']): ?>
      <p class="text-xs text-gray-400 line-clamp-2 leading-relaxed mb-3">
        <?= e(mb_substr($post['caption'], 0, 120)) ?>
      </p>
      <?php endif; ?>
      <p class="text-xs text-gray-400 mb-3">
        <?= $post['posted_at'] ? date('d/m/Y', strtotime($post['posted_at'])) : '—' ?>
      </p>

      <!-- Métricas -->
      <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs mt-auto">
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Alcance</span>
          <span class="font-medium text-blue-300"><?= number_format((int)$post['reach'], 0, ',', '.') ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Impr.</span>
          <span class="font-medium text-cyan-300"><?= number_format((int)$post['impressions'], 0, ',', '.') ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Curtidas</span>
          <span class="font-medium text-red-300"><?= number_format((int)$post['likes'], 0, ',', '.') ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Coment.</span>
          <span class="font-medium text-yellow-300"><?= number_format((int)$post['comments'], 0, ',', '.') ?></span>
        </div>
        <?php if ($post['saves'] > 0): ?>
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Salvos</span>
          <span class="font-medium text-green-300"><?= number_format((int)$post['saves'], 0, ',', '.') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($post['video_views'] > 0): ?>
        <div class="flex items-center justify-between">
          <span class="text-gray-400">Views</span>
          <span class="font-medium text-purple-300"><?= number_format((int)$post['video_views'], 0, ',', '.') ?></span>
        </div>
        <?php endif; ?>
        <div class="col-span-2 pt-1 border-t border-white/[0.06] flex items-center justify-between">
          <span class="text-gray-400">Engajamento</span>
          <span class="font-semibold <?= (float)$post['engagement_rate'] >= 3 ? 'text-green-400' : 'text-gray-300' ?>">
            <?= number_format((float)$post['engagement_rate'], 2, ',', '.') ?>%
          </span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_start('scripts'); ?>
<script>
function organicChart(rows) {
  return {
    init() {
      const labels = rows.map(r => r.date);
      const reach  = rows.map(r => parseInt(r.reach) || 0);
      new Chart(document.getElementById('organicReachChart'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Alcance',
            data: reach,
            borderColor: 'rgba(236,72,153,0.8)',
            backgroundColor: 'rgba(236,72,153,0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 2,
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 } } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 } } }
          }
        }
      });
    }
  }
}
</script>
<?php view_end(); ?>

<?php view_end(); ?>
