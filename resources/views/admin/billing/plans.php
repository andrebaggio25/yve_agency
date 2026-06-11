<?php view_layout('admin'); view_start('title'); ?>Planos<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Planos de Assinatura</h1>
    <p class="text-sm text-gray-400 mt-0.5"><?= count($plans) ?> planos configurados</p>
  </div>
  <a href="/admin/planos/novo" class="btn-primary text-sm px-4 py-2">+ Novo plano</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
  <?php foreach ($plans as $plan):
    $subs = $subsByPlan[$plan['id']] ?? 0;
    $features = is_string($plan['features']) ? (json_decode($plan['features'], true) ?? []) : ($plan['features'] ?? []);
    $tierColors = ['free' => 'text-gray-300', 'starter' => 'text-blue-300', 'pro' => 'text-violet-300', 'enterprise' => 'text-yellow-300'];
    $tc = $tierColors[$plan['slug']] ?? 'text-gray-300';
  ?>
  <div class="card p-5 flex flex-col <?= !$plan['is_active'] ? 'opacity-50' : '' ?>">
    <div class="flex items-start justify-between mb-3">
      <div>
        <span class="text-xs font-semibold uppercase tracking-wider <?= $tc ?>"><?= e($plan['name']) ?></span>
        <?php if (!$plan['is_active']): ?>
        <span class="ml-2 text-xs text-gray-600">(inativo)</span>
        <?php endif; ?>
      </div>
      <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full"><?= $subs ?> agência<?= $subs !== 1 ? 's' : '' ?></span>
    </div>

    <div class="mb-4">
      <p class="text-2xl font-bold text-white">
        <?= $plan['price_monthly'] > 0 ? 'R$ ' . number_format($plan['price_monthly'], 0, ',', '.') : 'Grátis' ?>
        <?php if ($plan['price_monthly'] > 0): ?>
        <span class="text-sm font-normal text-gray-500">/mês</span>
        <?php endif; ?>
      </p>
      <?php if ($plan['price_yearly'] > 0): ?>
      <p class="text-xs text-gray-500">R$ <?= number_format($plan['price_yearly'], 0, ',', '.') ?>/ano</p>
      <?php endif; ?>
    </div>

    <?php if ($plan['description']): ?>
    <p class="text-xs text-gray-500 mb-4"><?= e($plan['description']) ?></p>
    <?php endif; ?>

    <!-- Limites -->
    <div class="space-y-1.5 mb-4 flex-1">
      <?php
      $limits = [
        'Clientes'    => $plan['max_clients'],
        'Usuários'    => $plan['max_users'],
        'Contas Meta' => $plan['max_meta_accounts'],
        'Orgânico'    => $plan['max_organic_accounts'],
      ];
      foreach ($limits as $label => $val): ?>
      <div class="flex items-center justify-between text-xs">
        <span class="text-gray-500"><?= $label ?></span>
        <span class="text-gray-300 font-medium"><?= $val ?? '∞' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <a href="/admin/planos/<?= $plan['id'] ?>/editar"
       class="btn-secondary text-xs px-3 py-2 justify-center w-full mt-2">Editar</a>
  </div>
  <?php endforeach; ?>
</div>

<?php view_end(); ?>
