<?php view_layout('portal'); view_start('title'); ?>Início<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Olá, <?= e(explode(' ', $client['name'])[0]) ?>!</h1>
  <p class="text-sm text-gray-400 mt-0.5">Aqui está um resumo do seu trabalho com a agência.</p>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
  <?php
  $kpis = [
    ['label' => 'Planos pendentes',  'value' => $stats['plans_pending'],  'color' => 'text-yellow-300', 'href' => "/portal/{$token}/planos"],
    ['label' => 'Planos aprovados',  'value' => $stats['plans_approved'], 'color' => 'text-green-300',  'href' => "/portal/{$token}/planos"],
    ['label' => 'Faturas em aberto', 'value' => $stats['invoices_open'],  'color' => 'text-red-300',    'href' => "/portal/{$token}/faturas"],
    ['label' => 'Faturas pagas',     'value' => $stats['invoices_paid'],  'color' => 'text-blue-300',   'href' => "/portal/{$token}/faturas"],
  ];
  foreach ($kpis as $k): ?>
  <a href="<?= $k['href'] ?>" class="card p-5 hover:bg-white/[0.03] transition-colors block">
    <p class="text-xs text-gray-500 mb-1"><?= $k['label'] ?></p>
    <p class="text-2xl font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </a>
  <?php endforeach; ?>
</div>

<!-- Planos pendentes de aprovação -->
<?php $pending = array_filter($plans, fn($p) => $p['status'] === 'pending_approval'); ?>
<?php if (!empty($pending)): ?>
<div class="card p-5 mb-6 border border-yellow-500/20 bg-yellow-500/5">
  <h2 class="text-sm font-semibold text-yellow-300 mb-4 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    Aguardando sua aprovação
  </h2>
  <div class="space-y-2">
    <?php foreach ($pending as $p): ?>
    <div class="flex items-center justify-between">
      <p class="text-sm text-white"><?= e($p['title']) ?></p>
      <a href="/portal/<?= $token ?>/planos/<?= $p['id'] ?>"
         class="btn-primary text-xs px-3 py-1.5">Revisar</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Planos recentes -->
<?php if (!empty($plans)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">Planos de conteúdo recentes</h2>
<div class="card overflow-hidden mb-6">
  <table class="w-full text-sm">
    <tbody class="divide-y divide-white/[0.04]">
      <?php
      $statusLabels = ['draft' => 'Rascunho', 'pending_approval' => 'Aguardando', 'approved' => 'Aprovado', 'in_revision' => 'Em revisão', 'published' => 'Publicado'];
      $statusColors = ['draft' => 'text-gray-400 bg-gray-500/10', 'pending_approval' => 'text-yellow-300 bg-yellow-500/10', 'approved' => 'text-green-300 bg-green-500/10', 'in_revision' => 'text-blue-300 bg-blue-500/10', 'published' => 'text-violet-300 bg-violet-500/10'];
      foreach (array_slice($plans, 0, 5) as $p): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3">
          <p class="font-medium text-white"><?= e($p['title']) ?></p>
          <?php if ($p['period_label'] ?? null): ?>
          <p class="text-xs text-gray-500"><?= e($p['period_label']) ?></p>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3">
          <span class="badge text-xs font-medium px-2 py-0.5 rounded-full <?= $statusColors[$p['status']] ?? 'text-gray-400' ?>">
            <?= $statusLabels[$p['status']] ?? $p['status'] ?>
          </span>
        </td>
        <td class="px-5 py-3 text-right">
          <a href="/portal/<?= $token ?>/planos/<?= $p['id'] ?>"
             class="text-xs text-violet-400 hover:text-violet-300">Ver &rarr;</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Faturas em aberto -->
<?php $openInvoices = array_filter($invoices, fn($i) => in_array($i['status'], ['sent', 'overdue'])); ?>
<?php if (!empty($openInvoices)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">Faturas em aberto</h2>
<div class="card overflow-hidden mb-6">
  <table class="w-full text-sm">
    <tbody class="divide-y divide-white/[0.04]">
      <?php foreach ($openInvoices as $inv): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3">
          <p class="font-medium text-white"><?= e($inv['invoice_number'] ?? "#$inv[id]") ?></p>
          <p class="text-xs text-gray-500">Vencimento: <?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></p>
        </td>
        <td class="px-5 py-3 text-right font-semibold <?= $inv['status'] === 'overdue' ? 'text-red-400' : 'text-white' ?>">
          R$ <?= number_format((float)$inv['total'], 2, ',', '.') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Tráfego Pago (últimos 30 dias) -->
<?php if (!empty($adsSummary)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">
  Tráfego Pago
  <span class="ml-2 text-xs font-normal text-gray-500"><?= date('d/m', strtotime($since)) ?> – <?= date('d/m', strtotime($until)) ?></span>
</h2>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
  <?php
  $adsKpis = [
    ['label' => 'Impressões',  'value' => number_format((float)($adsSummary['impressions'] ?? 0), 0, ',', '.'), 'color' => 'text-violet-300'],
    ['label' => 'Cliques',     'value' => number_format((float)($adsSummary['clicks']      ?? 0), 0, ',', '.'), 'color' => 'text-blue-300'],
    ['label' => 'Investido',   'value' => 'R$ ' . number_format((float)($adsSummary['spend'] ?? 0), 2, ',', '.'), 'color' => 'text-amber-300'],
    ['label' => 'Resultados',  'value' => number_format((float)($adsSummary['conversions'] ?? $adsSummary['results'] ?? 0), 0, ',', '.'), 'color' => 'text-green-300'],
  ];
  foreach ($adsKpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500 mb-1"><?= $k['label'] ?></p>
    <p class="text-xl font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Orgânico (últimos 30 dias) -->
<?php if (!empty($organicSummary)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3">
  Redes Sociais Orgânicas
  <span class="ml-2 text-xs font-normal text-gray-500"><?= date('d/m', strtotime($since)) ?> – <?= date('d/m', strtotime($until)) ?></span>
</h2>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
  <?php
  $orgKpis = [
    ['label' => 'Alcance',      'value' => number_format((float)($organicSummary['reach']       ?? 0), 0, ',', '.'), 'color' => 'text-emerald-300'],
    ['label' => 'Impressões',   'value' => number_format((float)($organicSummary['impressions'] ?? 0), 0, ',', '.'), 'color' => 'text-violet-300'],
    ['label' => 'Engajamento',  'value' => number_format((float)($organicSummary['engagement']  ?? 0), 0, ',', '.'), 'color' => 'text-blue-300'],
    ['label' => 'Seguidores',   'value' => '+' . number_format((float)($organicSummary['followers_gained'] ?? 0), 0, ',', '.'), 'color' => 'text-pink-300'],
  ];
  foreach ($orgKpis as $k): ?>
  <div class="card p-4">
    <p class="text-xs text-gray-500 mb-1"><?= $k['label'] ?></p>
    <p class="text-xl font-bold <?= $k['color'] ?>"><?= $k['value'] ?></p>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php view_end(); ?>
