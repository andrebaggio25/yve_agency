<?php view_layout('app'); view_start('title'); ?>IA Insights<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">IA Insights</h1>
    <p class="text-sm text-gray-400 mt-0.5">Análises e recomendações geradas por inteligência artificial</p>
  </div>
  <div class="flex gap-3">
    <a href="/ia/recomendacoes" class="btn-secondary text-sm px-4 py-2">
      <span class="mr-1">⚡</span> Recomendações de ação
    </a>
    <a href="/ia/gerar" class="btn-primary text-sm px-4 py-2">+ Gerar insight</a>
  </div>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
  <select name="account_id" class="input-field text-sm py-1.5 px-3 w-56">
    <option value="">Todas as contas</option>
    <?php foreach ($accounts as $a): ?>
    <option value="<?= $a['id'] ?>" <?= $filters['ad_account_id'] == $a['id'] ? 'selected' : '' ?>>
      <?= e($a['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <select name="type" class="input-field text-sm py-1.5 px-3 w-56">
    <option value="">Todos os tipos</option>
    <?php foreach (['performance_summary' => 'Resumo de performance', 'alert' => 'Alertas', 'recommendation' => 'Recomendações', 'report' => 'Relatório'] as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filters['type'] === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<?php if (empty($insights)): ?>
<div class="card p-12 text-center">
  <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-brand-500/10 flex items-center justify-center">
    <svg class="w-7 h-7 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
    </svg>
  </div>
  <p class="text-gray-400 mb-2">Nenhum insight gerado ainda.</p>
  <p class="text-sm text-gray-500 mb-5">Conecte uma conta de anúncios e gere seu primeiro insight.</p>
  <a href="/ia/gerar" class="btn-primary px-6 py-2.5 text-sm">Gerar primeiro insight</a>
</div>
<?php else: ?>
<div class="space-y-3">
  <?php
  $typeColors = [
    'performance_summary' => ['bg' => 'bg-blue-500/10',   'text' => 'text-blue-400',   'label' => 'Performance'],
    'alert'               => ['bg' => 'bg-red-500/10',    'text' => 'text-red-400',    'label' => 'Alerta'],
    'recommendation'      => ['bg' => 'bg-green-500/10',  'text' => 'text-green-400',  'label' => 'Recomendação'],
    'report'              => ['bg' => 'bg-purple-500/10', 'text' => 'text-purple-400', 'label' => 'Relatório'],
  ];
  ?>
  <?php foreach ($insights as $i): ?>
  <?php $tc = $typeColors[$i['type']] ?? ['bg' => 'bg-gray-500/10', 'text' => 'text-gray-400', 'label' => $i['type']]; ?>
  <div class="card p-5 hover:border-white/10 transition-colors">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-2">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $tc['bg'] ?> <?= $tc['text'] ?>">
            <?= $tc['label'] ?>
          </span>
          <?php if ($i['account_name']): ?>
          <span class="text-xs text-gray-500"><?= e($i['account_name']) ?></span>
          <?php endif; ?>
          <?php if ($i['period_start']): ?>
          <span class="text-xs text-gray-600">
            <?= date('d/m/Y', strtotime($i['period_start'])) ?> – <?= date('d/m/Y', strtotime($i['period_end'])) ?>
          </span>
          <?php endif; ?>
          <span class="text-xs text-gray-600 ml-auto"><?= date('d/m/Y H:i', strtotime($i['created_at'])) ?></span>
        </div>
        <p class="text-sm text-gray-300 line-clamp-2 leading-relaxed">
          <?= e(mb_substr(strip_tags($i['content']), 0, 200)) ?>…
        </p>
        <div class="mt-2 flex items-center gap-3">
          <?php if ($i['ai_provider']): ?>
          <span class="text-xs text-gray-600">
            <?= $i['ai_provider'] === 'openai' ? 'OpenAI' : 'Claude' ?> · <?= e($i['model'] ?? '') ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <a href="/ia/<?= $i['id'] ?>" class="btn-secondary text-xs px-3 py-1.5">Ver</a>
        <form method="POST" action="/ia/<?= $i['id'] ?>"
              onsubmit="return confirm('Remover este insight?')">
          <?= csrf_field() ?>
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors px-2 py-1.5">✕</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_end(); ?>
