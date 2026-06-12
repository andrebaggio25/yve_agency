<?php view_layout('portal'); view_start('title'); ?><?= e($plan['title']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['draft' => 'Rascunho', 'pending_approval' => 'Aguardando aprovação', 'approved' => 'Aprovado', 'in_revision' => 'Em revisão', 'published' => 'Publicado'];
$statusColors = [
  'draft'            => 'text-gray-400 bg-gray-500/10',
  'pending_approval' => 'text-yellow-300 bg-yellow-500/10',
  'approved'         => 'text-green-300 bg-green-500/10',
  'in_revision'      => 'text-blue-300 bg-blue-500/10',
  'published'        => 'text-violet-300 bg-violet-500/10',
];
$itemStatusColors = ['pending' => 'text-gray-400 bg-gray-500/10', 'approved' => 'text-green-300 bg-green-500/10', 'revision' => 'text-yellow-300 bg-yellow-500/10', 'rejected' => 'text-red-300 bg-red-500/10', 'draft' => 'text-gray-400 bg-gray-500/10'];
$itemStatusLabels = ['pending' => 'Pendente', 'approved' => 'Aprovado', 'revision' => 'Revisão', 'rejected' => 'Rejeitado', 'draft' => 'Rascunho'];
$platformColors   = ['instagram' => '#E1306C', 'tiktok' => '#010101', 'youtube' => '#FF0000', 'linkedin' => '#0A66C2', 'facebook' => '#1877F2', 'pinterest' => '#E60023'];
$videoTypes       = ['Reels / Vídeo', 'reels', 'Story'];
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="/portal/<?= $token ?>/planos" class="hover:text-gray-300">Planos</a>
  <span>/</span>
  <span class="text-gray-300"><?= e($plan['title']) ?></span>
</nav>

<!-- Header do plano -->
<div class="card p-5 mb-6">
  <div class="flex items-start justify-between gap-4 mb-4">
    <div>
      <h1 class="text-xl font-semibold text-white"><?= e($plan['title']) ?></h1>
      <?php if ($plan['period_label'] ?? null): ?>
      <p class="text-sm text-gray-400 mt-0.5"><?= e($plan['period_label']) ?></p>
      <?php endif; ?>
    </div>
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0 <?= $statusColors[$plan['status']] ?? '' ?>">
      <?= $statusLabels[$plan['status']] ?? $plan['status'] ?>
    </span>
  </div>

  <?php if ($plan['description'] ?? null): ?>
  <p class="text-sm text-gray-400 mb-4 leading-relaxed"><?= e($plan['description']) ?></p>
  <?php endif; ?>

  <!-- Ações de aprovação -->
  <?php if ($plan['status'] === 'pending_approval'): ?>
  <div class="flex flex-wrap gap-3 pt-4 border-t border-white/[0.06]" x-data="{showRevision: false}">
    <form method="POST" action="/portal/<?= $token ?>/planos/<?= $plan['id'] ?>/aprovar">
      <input type="hidden" name="_token" value="<?= csrf_token() ?>">
      <button type="submit" class="btn-primary text-sm px-5 py-2.5 gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Aprovar plano
      </button>
    </form>
    <button @click="showRevision = !showRevision" class="btn-secondary text-sm px-4 py-2.5">
      Solicitar revisão
    </button>

    <div x-show="showRevision" x-transition class="w-full mt-2">
      <form method="POST" action="/portal/<?= $token ?>/planos/<?= $plan['id'] ?>/revisao">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <textarea name="comment" rows="3" placeholder="Descreva o que precisa ser ajustado..."
                  class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 resize-none"
                  style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);"></textarea>
        <div class="flex justify-end mt-2">
          <button type="submit" class="btn-primary text-sm px-4 py-2">Enviar revisão</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Itens do plano -->
<?php if (!empty($items)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">Posts (<?= count($items) ?>)</h2>
<div class="space-y-4">
  <?php foreach ($items as $item):
    $parsedDrive = $item['drive_parsed'] ?? null;
    $isVideo     = in_array($item['content_type'] ?? '', $videoTypes);
    $pColor      = $platformColors[$item['platform'] ?? ''] ?? null;
  ?>
  <div class="card p-4">

    <!-- Post header: platform + format + date + status -->
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex items-center gap-2 flex-wrap">
        <?php if ($pColor): ?>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold text-white" style="background:<?= $pColor ?>">
          <?= ucfirst(e($item['platform'])) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($item['content_type'])): ?>
        <span class="text-xs text-violet-300 bg-violet-500/10 px-2 py-0.5 rounded-full font-medium">
          <?= e($item['content_type']) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($item['publish_date'])): ?>
        <span class="text-xs text-gray-500">
          <?= date('d/m/Y', strtotime($item['publish_date'])) ?>
          <?= !empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '' ?>
        </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($item['status'])): ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 <?= $itemStatusColors[$item['status']] ?? 'text-gray-400 bg-gray-500/10' ?>">
        <?= $itemStatusLabels[$item['status']] ?? $item['status'] ?>
      </span>
      <?php endif; ?>
    </div>

    <!-- Cover image (always shown if available) -->
    <?php if (!empty($item['cover_url'])): ?>
    <div class="mb-3 rounded-xl overflow-hidden">
      <img src="<?= e($item['cover_url']) ?>" alt="Capa"
           class="w-full object-cover max-h-80"
           onerror="this.parentElement.style.display='none'">
    </div>
    <?php endif; ?>

    <!-- Caption -->
    <?php if (!empty($item['caption'])): ?>
    <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-line mb-3"><?= e($item['caption']) ?></p>
    <?php endif; ?>

    <!-- Drive embed (video player for Reels/Vídeo) or link for others -->
    <?php if ($parsedDrive && $parsedDrive['valid']): ?>
    <?php if ($isVideo || $parsedDrive['file_type'] === 'video'): ?>
    <div class="rounded-xl overflow-hidden border border-white/10 bg-black/30">
      <div class="flex items-center gap-2 px-3 py-2 border-b border-white/5">
        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-xs text-gray-400">Vídeo</span>
        <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
           class="ml-auto text-xs text-violet-400 hover:text-violet-300 transition-colors">
          Abrir Drive →
        </a>
      </div>
      <div class="aspect-video">
        <iframe src="<?= e($parsedDrive['embed_url']) ?>"
                class="w-full h-full border-0" loading="lazy" allowfullscreen></iframe>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 text-xs text-violet-400 hover:text-violet-300 transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
      Ver arquivo no Drive →
    </a>
    <?php endif; ?>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card p-8 text-center text-gray-500 text-sm">Nenhum post adicionado ainda.</div>
<?php endif; ?>

<?php view_end(); ?>
