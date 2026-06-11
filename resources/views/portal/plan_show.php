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
      <button type="submit"
              class="btn-primary text-sm px-5 py-2.5 gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Aprovar plano
      </button>
    </form>
    <button @click="showRevision = !showRevision"
            class="btn-secondary text-sm px-4 py-2.5">
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
<h2 class="text-sm font-semibold text-gray-300 mb-3">Conteúdos do plano (<?= count($items) ?>)</h2>
<div class="space-y-3">
  <?php
  $itemStatusColors = ['pending' => 'text-gray-400 bg-gray-500/10', 'approved' => 'text-green-300 bg-green-500/10', 'revision' => 'text-yellow-300 bg-yellow-500/10', 'rejected' => 'text-red-300 bg-red-500/10'];
  $itemStatusLabels = ['pending' => 'Pendente', 'approved' => 'Aprovado', 'revision' => 'Revisão', 'rejected' => 'Rejeitado'];
  foreach ($items as $item): ?>
  <div class="card p-4">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <?php if ($item['content_type'] ?? null): ?>
          <span class="text-xs text-violet-400 bg-violet-500/10 px-2 py-0.5 rounded-full font-medium">
            <?= e($item['content_type']) ?>
          </span>
          <?php endif; ?>
          <?php if ($item['scheduled_date'] ?? null): ?>
          <span class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($item['scheduled_date'])) ?></span>
          <?php endif; ?>
        </div>
        <p class="font-medium text-white text-sm"><?= e($item['title'] ?? $item['caption'] ?? 'Sem título') ?></p>
        <?php if ($item['caption'] ?? null): ?>
        <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= e($item['caption']) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($item['status'] ?? null): ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 <?= $itemStatusColors[$item['status']] ?? 'text-gray-400' ?>">
        <?= $itemStatusLabels[$item['status']] ?? $item['status'] ?>
      </span>
      <?php endif; ?>
    </div>
    <?php if (!empty($item['media_url'] ?? '') && $item['media_url']): ?>
    <img src="<?= e($item['media_url']) ?>" alt=""
         class="mt-3 rounded-lg w-full max-h-48 object-cover opacity-80"
         onerror="this.style.display='none'">
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card p-8 text-center text-gray-500 text-sm">Nenhum item adicionado ainda.</div>
<?php endif; ?>

<?php view_end(); ?>
