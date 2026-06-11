<?php view_layout('app'); view_start('title'); ?>Assinatura<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['trialing' => 'Trial', 'active' => 'Ativa', 'past_due' => 'Atrasada', 'cancelled' => 'Cancelada', 'suspended' => 'Suspensa', 'none' => 'Sem plano'];
$statusColors = ['trialing' => 'text-blue-300 bg-blue-500/10', 'active' => 'text-green-300 bg-green-500/10', 'past_due' => 'text-red-300 bg-red-500/10', 'cancelled' => 'text-gray-500 bg-gray-700/20', 'none' => 'text-gray-400 bg-gray-500/10'];
$tierColors   = ['free' => 'text-gray-300', 'starter' => 'text-blue-300', 'pro' => 'text-violet-300', 'enterprise' => 'text-yellow-300'];
$tc           = $tierColors[$subscription['plan_slug'] ?? ''] ?? 'text-gray-300';

$resourceLabels = ['clients' => 'Clientes', 'users' => 'Usuários', 'meta_accounts' => 'Contas Meta Ads', 'organic_accounts' => 'Contas Orgânicas'];
?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Minha assinatura</h1>
  <p class="text-sm text-gray-400 mt-0.5">Plano atual e uso dos recursos</p>
</div>

<!-- Plano atual -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
  <div class="card p-6 col-span-1">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs font-semibold uppercase tracking-wider <?= $tc ?>">
        <?= e($subscription['plan_name'] ?? 'Sem plano') ?>
      </span>
      <?php if ($subscription): ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$subscription['status']] ?? '' ?>">
        <?= $statusLabels[$subscription['status']] ?? $subscription['status'] ?>
      </span>
      <?php endif; ?>
    </div>
    <p class="text-3xl font-bold text-white mb-1">
      <?= ($subscription['price_monthly'] ?? 0) > 0
        ? 'R$ ' . number_format($subscription['price_monthly'], 0, ',', '.')
        : 'Grátis' ?>
      <?php if (($subscription['price_monthly'] ?? 0) > 0): ?>
      <span class="text-base font-normal text-gray-500">/mês</span>
      <?php endif; ?>
    </p>
    <?php if ($subscription['description'] ?? null): ?>
    <p class="text-sm text-gray-500"><?= e($subscription['description']) ?></p>
    <?php endif; ?>

    <?php if ($subscription['current_period_end'] ?? null): ?>
    <p class="text-xs text-gray-600 mt-4">
      Próxima renovação: <?= date('d/m/Y', strtotime($subscription['current_period_end'])) ?>
    </p>
    <?php endif; ?>
    <?php if ($subscription['trial_ends_at'] ?? null): ?>
    <p class="text-xs text-blue-400 mt-1">
      Trial até: <?= date('d/m/Y', strtotime($subscription['trial_ends_at'])) ?>
    </p>
    <?php endif; ?>
  </div>

  <!-- Uso dos recursos -->
  <div class="card p-6 col-span-2">
    <h2 class="text-sm font-semibold text-gray-300 mb-5">Uso dos recursos</h2>
    <div class="space-y-4">
      <?php foreach ($usage as $resource => $data):
        $label  = $resourceLabels[$resource] ?? $resource;
        $pct    = $data['pct'];
        $barColor = $data['over'] ? 'bg-red-500' : ($pct >= 80 ? 'bg-yellow-500' : 'bg-violet-500');
      ?>
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm text-gray-400"><?= $label ?></span>
          <span class="text-sm font-medium <?= $data['over'] ? 'text-red-400' : 'text-gray-300' ?>">
            <?= $data['current'] ?>
            <?php if ($data['limit'] !== null): ?>
            <span class="text-gray-600">/ <?= $data['limit'] ?></span>
            <?php else: ?>
            <span class="text-gray-600">/ ∞</span>
            <?php endif; ?>
          </span>
        </div>
        <?php if ($data['limit'] !== null): ?>
        <div class="h-2 rounded-full bg-white/[0.06] overflow-hidden">
          <div class="h-full rounded-full <?= $barColor ?> transition-all"
               style="width: <?= $pct ?>%"></div>
        </div>
        <?php else: ?>
        <div class="h-2 rounded-full bg-violet-500/20"></div>
        <?php endif; ?>
        <?php if ($data['over']): ?>
        <p class="text-xs text-red-400 mt-1">Limite atingido — faça upgrade para continuar adicionando.</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Comparativo de planos -->
<h2 class="text-sm font-semibold text-gray-300 mb-4">Planos disponíveis</h2>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
  <?php
  $allFeatureLabels = [
    'content_plans' => 'Planos de conteúdo',
    'approvals'     => 'Aprovações',
    'financial'     => 'Financeiro',
    'tasks'         => 'Tarefas',
    'portal'        => 'Portal do cliente',
    'ads'           => 'Tráfego Pago (Meta Ads)',
    'organic'       => 'Métricas orgânicas',
    'ai_insights'   => 'IA Insights',
    'all'           => 'Todos os recursos',
  ];
  foreach ($plans as $p):
    if (!$p['is_active']) continue;
    $isCurrent = ($p['slug'] === ($subscription['plan_slug'] ?? ''));
    $features  = is_string($p['features']) ? (json_decode($p['features'], true) ?? []) : ($p['features'] ?? []);
    $tc2 = $tierColors[$p['slug']] ?? 'text-gray-300';
  ?>
  <div class="card p-5 flex flex-col <?= $isCurrent ? 'ring-1 ring-violet-500/50' : '' ?>">
    <div class="flex items-center justify-between mb-3">
      <span class="text-xs font-bold uppercase tracking-wider <?= $tc2 ?>"><?= e($p['name']) ?></span>
      <?php if ($isCurrent): ?>
      <span class="text-xs text-violet-300 bg-violet-500/10 px-2 py-0.5 rounded-full font-medium">Atual</span>
      <?php endif; ?>
    </div>

    <p class="text-2xl font-bold text-white mb-0.5">
      <?= $p['price_monthly'] > 0 ? 'R$ ' . number_format($p['price_monthly'], 0, ',', '.') : 'Grátis' ?>
      <?php if ($p['price_monthly'] > 0): ?><span class="text-sm font-normal text-gray-500">/mês</span><?php endif; ?>
    </p>
    <?php if ($p['price_yearly'] > 0): ?>
    <p class="text-xs text-gray-500 mb-3">R$ <?= number_format($p['price_yearly'], 0, ',', '.') ?>/ano</p>
    <?php else: ?><div class="mb-3"></div><?php endif; ?>

    <!-- Limites -->
    <div class="space-y-1 mb-4 text-xs">
      <?php
      $limits = ['Clientes' => $p['max_clients'], 'Usuários' => $p['max_users'], 'Meta Ads' => $p['max_meta_accounts'], 'Orgânico' => $p['max_organic_accounts']];
      foreach ($limits as $lbl => $val): ?>
      <div class="flex justify-between">
        <span class="text-gray-600"><?= $lbl ?></span>
        <span class="text-gray-400"><?= $val ?? '∞' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Features -->
    <ul class="space-y-1.5 text-xs flex-1 mb-4">
      <?php foreach ($features as $f):
        $isAll = $f === 'all'; ?>
      <li class="flex items-center gap-2 text-gray-400">
        <svg class="w-3.5 h-3.5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <?= $isAll ? 'Todos os recursos' : ($allFeatureLabels[$f] ?? $f) ?>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!$isCurrent): ?>
    <div class="mt-auto">
      <p class="text-xs text-gray-500 text-center">
        Entre em contato com o suporte para fazer upgrade.
      </p>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Histórico de eventos -->
<?php if (!empty($events)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-4">Histórico</h2>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Data</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Evento</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Plano</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Valor</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($events as $ev): ?>
      <tr class="hover:bg-white/[0.02]">
        <td class="px-5 py-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($ev['created_at'])) ?></td>
        <td class="px-5 py-3 text-gray-300"><?= e($ev['description']) ?></td>
        <td class="px-5 py-3 text-gray-500 text-xs"><?= e($ev['plan_name'] ?? '—') ?></td>
        <td class="px-5 py-3 text-right font-medium text-gray-300">
          <?= $ev['amount'] > 0 ? 'R$ ' . number_format($ev['amount'], 2, ',', '.') : '—' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php view_end(); ?>
