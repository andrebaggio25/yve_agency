<?php view_layout('app'); view_start('title'); ?>Ações em Campanhas<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Ações em Campanhas</h1>
    <p class="text-sm text-gray-400 mt-0.5">
      Solicitações de otimização geradas por IA ou pela equipe
      <?php if ($pending > 0): ?>
      <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-500/15 text-yellow-400">
        <?= $pending ?> pendente<?= $pending > 1 ? 's' : '' ?>
      </span>
      <?php endif; ?>
    </p>
  </div>
  <div class="flex gap-3">
    <a href="/ia/recomendacoes" class="btn-secondary text-sm px-4 py-2">⚡ Recomendações IA</a>
    <a href="/trafego/acoes/nova" class="btn-primary text-sm px-4 py-2">+ Nova ação</a>
  </div>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
  <select name="status" class="input-field text-sm py-1.5 px-3 w-44">
    <option value="">Todos os status</option>
    <?php foreach (['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Rejeitada', 'executed' => 'Executada', 'failed' => 'Falhou'] as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="account_id" class="input-field text-sm py-1.5 px-3 w-56">
    <option value="">Todas as contas</option>
    <?php foreach ($accounts as $a): ?>
    <option value="<?= $a['id'] ?>" <?= $filters['ad_account_id'] == $a['id'] ? 'selected' : '' ?>>
      <?= e($a['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-1.5">Filtrar</button>
</form>

<?php if (empty($actions)): ?>
<div class="card p-12 text-center text-gray-500">
  Nenhuma ação encontrada com os filtros selecionados.
</div>
<?php else: ?>
<div class="space-y-3">
  <?php
  $statusMap = [
    'pending'  => ['bg' => 'bg-yellow-500/15',  'text' => 'text-yellow-400',  'label' => 'Pendente'],
    'approved' => ['bg' => 'bg-blue-500/15',    'text' => 'text-blue-400',    'label' => 'Aprovada'],
    'rejected' => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'label' => 'Rejeitada'],
    'executed' => ['bg' => 'bg-green-500/15',   'text' => 'text-green-400',   'label' => 'Executada'],
    'failed'   => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'label' => 'Falhou'],
  ];
  $actionLabels = [
    'pause'           => 'Pausar',
    'resume'          => 'Ativar',
    'increase_budget' => 'Aumentar orçamento',
    'decrease_budget' => 'Reduzir orçamento',
    'test_creative'   => 'Testar criativo',
    'archive'         => 'Arquivar',
  ];
  ?>
  <?php foreach ($actions as $a): ?>
  <?php $sc = $statusMap[$a['status']] ?? ['bg' => 'bg-gray-500/15', 'text' => 'text-gray-400', 'label' => $a['status']]; ?>
  <div class="card p-4 hover:border-white/10 transition-colors">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-2">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sc['bg'] ?> <?= $sc['text'] ?>">
            <?= $sc['label'] ?>
          </span>
          <span class="text-xs text-gray-500 bg-white/[0.05] px-2 py-0.5 rounded-full">
            <?= $actionLabels[$a['action_type']] ?? $a['action_type'] ?>
          </span>
          <?php if ($a['ai_generated']): ?>
          <span class="text-xs text-brand-400 bg-brand-500/10 px-2 py-0.5 rounded-full">IA</span>
          <?php endif; ?>
          <span class="text-xs text-gray-600 ml-auto"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
        </div>
        <p class="text-sm font-medium text-white"><?= e($a['description']) ?></p>
        <p class="text-xs text-gray-500 mt-1">
          <?= e($a['account_name']) ?>
          <?php if ($a['campaign_name']): ?>
          <span class="mx-1">·</span><?= e($a['campaign_name']) ?>
          <?php endif; ?>
          <?php if ($a['ad_set_name']): ?>
          <span class="mx-1">·</span><?= e($a['ad_set_name']) ?>
          <?php endif; ?>
        </p>
        <?php if ($a['current_value'] || $a['proposed_value']): ?>
        <div class="flex items-center gap-2 mt-2 text-xs">
          <?php if ($a['current_value']): ?>
          <span class="text-gray-500">Atual: <span class="text-gray-300"><?= e($a['current_value']) ?></span></span>
          <?php endif; ?>
          <?php if ($a['proposed_value']): ?>
          <span class="text-gray-600">→</span>
          <span class="text-gray-500">Proposto: <span class="text-green-400"><?= e($a['proposed_value']) ?></span></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <a href="/trafego/acoes/<?= $a['id'] ?>" class="btn-secondary text-xs px-3 py-1.5 flex-shrink-0">
        Ver detalhes
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_end(); ?>
