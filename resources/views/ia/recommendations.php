<?php view_layout('app'); view_start('title'); ?>Recomendações IA<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Recomendações de ação</h1>
    <p class="text-sm text-gray-400 mt-0.5">A IA analisa as campanhas e sugere otimizações</p>
  </div>
  <a href="/ia" class="btn-secondary text-sm px-4 py-2">← Insights</a>
</div>

<!-- Formulário de seleção de conta -->
<form method="GET" class="card p-5 mb-6 flex flex-wrap items-end gap-4">
  <div class="flex-1 min-w-48">
    <label class="label-field">Conta de anúncios</label>
    <select aria-label="Conta" name="account_id" class="input-field w-full">
      <option value="">Selecione…</option>
      <?php foreach ($accounts as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $accountId == $a['id'] ? 'selected' : '' ?>>
        <?= e($a['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="label-field">De</label>
    <input aria-label="Data inicial" type="date" name="since" value="<?= e($since) ?>" class="input-field w-40">
  </div>
  <div>
    <label class="label-field">Até</label>
    <input aria-label="Data final" type="date" name="until" value="<?= e($until) ?>" class="input-field w-40">
  </div>
  <button type="submit" class="btn-primary text-sm px-5 py-2.5">Analisar</button>
</form>

<?php if (!$accountId): ?>
<div class="card p-10 text-center text-gray-400">
  Selecione uma conta para gerar recomendações.
</div>
<?php elseif (empty($suggestions)): ?>
<div class="card p-10 text-center text-gray-400">
  Nenhuma recomendação gerada — pode não haver métricas suficientes no período ou a IA não identificou otimizações.
</div>
<?php else: ?>

<form method="POST" action="/ia/recomendacoes/salvar">
  <?= csrf_field() ?>
  <input type="hidden" name="account_id" value="<?= $accountId ?>">

  <div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-400"><?= count($suggestions) ?> recomendação(ões) gerada(s)</p>
    <button type="submit" class="btn-primary text-sm px-5 py-2">
      Criar ações selecionadas
    </button>
  </div>

  <div class="space-y-3">
    <?php
    $actionIcons = [
      'pause'           => ['icon' => '⏸', 'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10', 'label' => 'Pausar'],
      'resume'          => ['icon' => '▶', 'color' => 'text-green-400',  'bg' => 'bg-green-500/10',  'label' => 'Ativar'],
      'increase_budget' => ['icon' => '↑', 'color' => 'text-blue-400',   'bg' => 'bg-blue-500/10',   'label' => 'Aumentar orçamento'],
      'decrease_budget' => ['icon' => '↓', 'color' => 'text-orange-400', 'bg' => 'bg-orange-500/10', 'label' => 'Reduzir orçamento'],
      'test_creative'   => ['icon' => '🎨', 'color' => 'text-purple-400', 'bg' => 'bg-purple-500/10', 'label' => 'Testar criativo'],
      'archive'         => ['icon' => '📦', 'color' => 'text-gray-400',   'bg' => 'bg-gray-500/10',   'label' => 'Arquivar'],
    ];
    ?>
    <?php foreach ($suggestions as $idx => $s): ?>
    <?php $ai = $actionIcons[$s['action_type']] ?? ['icon' => '•', 'color' => 'text-gray-400', 'bg' => 'bg-gray-500/10', 'label' => $s['action_type']]; ?>
    <label class="card p-5 cursor-pointer hover:border-brand-500/30 transition-colors block" x-data="{ checked: true }">
      <div class="flex items-start gap-4">
        <input type="checkbox" name="suggestions[]"
               value="<?= e(json_encode($s)) ?>"
               checked
               class="mt-0.5 w-4 h-4 accent-brand-500 flex-shrink-0">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $ai['bg'] ?> <?= $ai['color'] ?>">
              <?= $ai['label'] ?>
            </span>
            <?php if ($s['campaign_name']): ?>
            <span class="text-xs text-gray-400"><?= e($s['campaign_name']) ?></span>
            <?php endif; ?>
          </div>
          <p class="text-sm font-medium text-white mb-1"><?= e($s['description']) ?></p>
          <p class="text-xs text-gray-400 leading-relaxed"><?= e($s['justification']) ?></p>
          <?php if ($s['current_value'] || $s['proposed_value']): ?>
          <div class="mt-3 flex items-center gap-3 text-xs">
            <?php if ($s['current_value']): ?>
            <span class="text-gray-400">Atual: <span class="text-gray-300"><?= e($s['current_value']) ?></span></span>
            <?php endif; ?>
            <?php if ($s['proposed_value']): ?>
            <span class="text-gray-400">→</span>
            <span class="text-gray-400">Proposto: <span class="text-green-400 font-medium"><?= e($s['proposed_value']) ?></span></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </label>
    <?php endforeach; ?>
  </div>

  <div class="flex justify-end mt-5">
    <button type="submit" class="btn-primary text-sm px-6 py-2.5">
      Criar ações selecionadas →
    </button>
  </div>
</form>
<?php endif; ?>

<?php view_end(); ?>
