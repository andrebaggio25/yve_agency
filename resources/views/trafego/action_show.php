<?php view_layout('app'); view_start('title'); ?>Ação #<?= $action['id'] ?><?php view_end(); ?>
<?php view_start('content'); ?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="/trafego/acoes" class="hover:text-gray-300">Ações</a>
  <span>/</span>
  <span class="text-gray-300">Ação #<?= $action['id'] ?></span>
</nav>

<?php
$statusMap = [
  'pending'  => ['bg' => 'bg-yellow-500/15',  'text' => 'text-yellow-400',  'label' => 'Pendente'],
  'approved' => ['bg' => 'bg-blue-500/15',    'text' => 'text-blue-400',    'label' => 'Aprovada'],
  'rejected' => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'label' => 'Rejeitada'],
  'executed' => ['bg' => 'bg-green-500/15',   'text' => 'text-green-400',   'label' => 'Executada'],
  'failed'   => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'label' => 'Falhou'],
];
$actionLabels = [
  'pause'           => 'Pausar campanha/conjunto',
  'resume'          => 'Reativar campanha/conjunto',
  'increase_budget' => 'Aumentar orçamento',
  'decrease_budget' => 'Reduzir orçamento',
  'test_creative'   => 'Testar novo criativo',
  'archive'         => 'Arquivar',
];
$sc = $statusMap[$action['status']] ?? ['bg' => 'bg-gray-500/15', 'text' => 'text-gray-400', 'label' => $action['status']];
?>

<div class="max-w-2xl space-y-5">
  <!-- Cabeçalho -->
  <div class="card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
      <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
          <?= $sc['label'] ?>
        </span>
        <span class="text-xs text-gray-500 bg-white/[0.05] px-2 py-1 rounded-full">
          <?= $actionLabels[$action['action_type']] ?? $action['action_type'] ?>
        </span>
        <?php if ($action['ai_generated']): ?>
        <span class="text-xs text-brand-400 bg-brand-500/10 px-2 py-1 rounded-full">Gerado por IA</span>
        <?php endif; ?>
      </div>
      <span class="text-xs text-gray-500 flex-shrink-0">
        <?= date('d/m/Y H:i', strtotime($action['created_at'])) ?>
      </span>
    </div>

    <h2 class="text-base font-semibold text-white mb-1"><?= e($action['description']) ?></h2>
    <?php if ($action['justification']): ?>
    <p class="text-sm text-gray-400 leading-relaxed"><?= e($action['justification']) ?></p>
    <?php endif; ?>

    <!-- Alvo -->
    <div class="mt-4 pt-4 border-t border-white/[0.06] grid grid-cols-2 gap-3 text-sm">
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Conta</p>
        <p class="text-gray-300"><?= e($action['account_name']) ?></p>
      </div>
      <?php if ($action['campaign_name']): ?>
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Campanha</p>
        <p class="text-gray-300"><?= e($action['campaign_name']) ?></p>
      </div>
      <?php endif; ?>
      <?php if ($action['ad_set_name']): ?>
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Conjunto de anúncio</p>
        <p class="text-gray-300"><?= e($action['ad_set_name']) ?></p>
      </div>
      <?php endif; ?>
      <?php if ($action['ad_name']): ?>
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Anúncio</p>
        <p class="text-gray-300"><?= e($action['ad_name']) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Valores -->
    <?php if ($action['current_value'] || $action['proposed_value']): ?>
    <div class="mt-4 pt-4 border-t border-white/[0.06] flex items-center gap-6 text-sm">
      <?php if ($action['current_value']): ?>
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Valor atual</p>
        <p class="text-gray-300 font-medium"><?= e($action['current_value']) ?></p>
      </div>
      <?php endif; ?>
      <?php if ($action['proposed_value']): ?>
      <span class="text-gray-600 text-lg">→</span>
      <div>
        <p class="text-xs text-gray-500 mb-0.5">Valor proposto</p>
        <p class="text-green-400 font-semibold"><?= e($action['proposed_value']) ?></p>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Histórico de workflow -->
  <div class="card p-5">
    <h3 class="text-sm font-semibold text-gray-300 mb-4">Histórico</h3>
    <div class="space-y-3 text-sm">
      <div class="flex items-center gap-3">
        <div class="w-2 h-2 rounded-full bg-gray-500 flex-shrink-0 mt-0.5"></div>
        <div>
          <span class="text-gray-400">Criado</span>
          <?php if ($action['requested_by_name']): ?>
          <span class="text-gray-500"> por <?= e($action['requested_by_name']) ?></span>
          <?php endif; ?>
          <span class="text-gray-600 ml-2 text-xs"><?= date('d/m/Y H:i', strtotime($action['created_at'])) ?></span>
        </div>
      </div>
      <?php if ($action['approved_at']): ?>
      <div class="flex items-center gap-3">
        <div class="w-2 h-2 rounded-full <?= $action['status'] === 'rejected' ? 'bg-red-500' : 'bg-blue-500' ?> flex-shrink-0 mt-0.5"></div>
        <div>
          <span class="text-gray-400"><?= $action['status'] === 'rejected' ? 'Rejeitado' : 'Aprovado' ?></span>
          <?php if ($action['approved_by_name']): ?>
          <span class="text-gray-500"> por <?= e($action['approved_by_name'] ?? '') ?></span>
          <?php endif; ?>
          <span class="text-gray-600 ml-2 text-xs"><?= date('d/m/Y H:i', strtotime($action['approved_at'])) ?></span>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($action['executed_at']): ?>
      <div class="flex items-center gap-3">
        <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0 mt-0.5"></div>
        <div>
          <span class="text-gray-400">Executado via Meta Ads API</span>
          <span class="text-gray-600 ml-2 text-xs"><?= date('d/m/Y H:i', strtotime($action['executed_at'])) ?></span>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($action['error_message']): ?>
      <div class="mt-2 rounded-xl bg-red-500/10 border border-red-500/20 px-4 py-3 text-xs text-red-300">
        <span class="font-semibold">Erro:</span> <?= e($action['error_message']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Botões de workflow -->
  <div class="flex flex-wrap items-center gap-3">
    <a href="/trafego/acoes" class="btn-secondary text-sm px-4 py-2">← Voltar</a>

    <?php if ($action['status'] === 'pending'): ?>
    <form method="POST" action="/trafego/acoes/<?= $action['id'] ?>/aprovar">
      <?= csrf_field() ?>
      <button type="submit"
              class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-colors">
        ✓ Aprovar
      </button>
    </form>
    <form method="POST" action="/trafego/acoes/<?= $action['id'] ?>/rejeitar">
      <?= csrf_field() ?>
      <button type="submit"
              class="rounded-xl bg-red-600/20 border border-red-500/30 px-5 py-2 text-sm font-medium text-red-400 hover:bg-red-600/30 transition-colors">
        ✕ Rejeitar
      </button>
    </form>
    <?php endif; ?>

    <?php if ($action['status'] === 'approved'): ?>
    <form method="POST" action="/trafego/acoes/<?= $action['id'] ?>/executar"
          onsubmit="return confirm('Executar esta ação via Meta Ads API agora?')">
      <?= csrf_field() ?>
      <button type="submit"
              class="rounded-xl bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-500 transition-colors">
        ⚡ Executar agora
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php view_end(); ?>
