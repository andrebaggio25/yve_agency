<?php view_layout('app'); view_start('title'); ?>Insight IA<?php view_end(); ?>
<?php view_start('content'); ?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
  <a href="/ia" class="hover:text-gray-300">IA Insights</a>
  <span>/</span>
  <span class="text-gray-300">Insight #<?= $insight['id'] ?></span>
</nav>

<div class="max-w-3xl">
  <!-- Header -->
  <div class="flex items-start justify-between mb-6 gap-4">
    <div>
      <?php
      $typeColors = [
        'performance_summary' => ['bg' => 'bg-blue-500/10',   'text' => 'text-blue-400',   'label' => 'Resumo de Performance'],
        'alert'               => ['bg' => 'bg-red-500/10',    'text' => 'text-red-400',    'label' => 'Alerta'],
        'recommendation'      => ['bg' => 'bg-green-500/10',  'text' => 'text-green-400',  'label' => 'Recomendação'],
        'report'              => ['bg' => 'bg-purple-500/10', 'text' => 'text-purple-400', 'label' => 'Relatório Executivo'],
      ];
      $tc = $typeColors[$insight['type']] ?? ['bg' => 'bg-gray-500/10', 'text' => 'text-gray-400', 'label' => $insight['type']];
      ?>
      <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $tc['bg'] ?> <?= $tc['text'] ?> mb-3">
        <?= $tc['label'] ?>
      </span>
      <div class="flex flex-wrap items-center gap-3 text-sm text-gray-400">
        <?php if ($insight['account_name']): ?>
        <span><?= e($insight['account_name']) ?></span>
        <?php endif; ?>
        <?php if ($insight['client_name']): ?>
        <span class="text-gray-400">·</span>
        <span><?= e($insight['client_name']) ?></span>
        <?php endif; ?>
        <?php if ($insight['period_start']): ?>
        <span class="text-gray-400">·</span>
        <span><?= date('d/m/Y', strtotime($insight['period_start'])) ?> – <?= date('d/m/Y', strtotime($insight['period_end'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="text-right flex-shrink-0">
      <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($insight['created_at'])) ?></p>
      <?php if ($insight['ai_provider']): ?>
      <p class="text-xs text-gray-400 mt-0.5">
        <?= $insight['ai_provider'] === 'openai' ? 'OpenAI' : 'Claude' ?> · <?= e($insight['model'] ?? '') ?>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Conteúdo markdown renderizado -->
  <div class="card p-6 prose prose-invert prose-sm max-w-none
    prose-headings:text-white prose-headings:font-semibold
    prose-p:text-gray-300 prose-p:leading-relaxed
    prose-li:text-gray-300
    prose-strong:text-white
    prose-code:bg-white/10 prose-code:text-brand-300 prose-code:px-1 prose-code:rounded
    prose-blockquote:border-brand-500 prose-blockquote:text-gray-400"
    id="insightContent">
  </div>

  <!-- Ações -->
  <div class="flex items-center gap-3 mt-6">
    <a href="/ia" class="btn-secondary text-sm px-4 py-2">← Voltar</a>
    <a href="/ia/recomendacoes?account_id=<?= $insight['ad_account_id'] ?>"
       class="btn-secondary text-sm px-4 py-2">⚡ Gerar ações</a>
    <form method="POST" action="/ia/<?= $insight['id'] ?>"
          onsubmit="return confirm('Remover este insight?')" class="ml-auto">
      <?= csrf_field() ?>
      <input type="hidden" name="_method" value="DELETE">
      <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors">Remover</button>
    </form>
  </div>
</div>

<?php view_start('scripts'); ?>
<script src="<?= asset('/js/vendor/marked.min.js') ?>"></script>
<script src="<?= asset('/js/vendor/purify.min.js') ?>"></script>
<script>
const raw = <?= json_encode($insight['content'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
// SEC-04: o Markdown vira HTML e é SANITIZADO com DOMPurify antes de ir ao DOM.
// Sem isso, um insight com HTML/script embutido executaria (XSS armazenado).
const el = document.getElementById('insightContent');
if (el) {
  el.innerHTML = (window.DOMPurify && window.marked)
    ? DOMPurify.sanitize(marked.parse(raw))
    : '';
}
</script>
<?php view_end(); ?>

<?php view_end(); ?>
