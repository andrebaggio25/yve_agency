<?php view_layout('app'); view_start('content'); ?>

<?php $clients = $paginated['items']; ?>

<div class="min-h-screen">
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-1"><?= t('nav.clients') ?></p>
      <h1 class="text-2xl font-bold text-white"><?= t('clients.title') ?></h1>
      <p class="mt-1 text-sm text-gray-400"><?= $paginated['total'] ?> cliente<?= $paginated['total'] !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('clients.create')): ?>
    <a href="/clientes/novo"
       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 transition-all hover:bg-violet-500 hover:scale-105 active:scale-95">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      <?= t('clients.new') ?>
    </a>
    <?php endif; ?>
  </div>

  <!-- Search -->
  <form method="GET" class="mb-6">
    <div class="relative w-full max-w-sm">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
      </svg>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar por nome ou e-mail..."
             class="w-full rounded-xl border border-white/10 bg-white/[0.03] pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none">
    </div>
  </form>

  <?php if (empty($clients)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-violet-500/10 p-6">
      <svg class="w-12 h-12 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>
    <?php if ($q): ?>
    <p class="text-gray-400">Nenhum cliente encontrado para "<?= e($q) ?>".</p>
    <a href="/clientes" class="mt-4 text-sm text-violet-400 hover:text-violet-300">Limpar busca</a>
    <?php else: ?>
    <p class="text-gray-400"><?= t('clients.no_clients') ?></p>
    <?php if (\App\Support\Auth::can('clients.create')): ?>
    <a href="/clientes/novo" class="mt-4 text-sm text-violet-400 hover:text-violet-300 transition-colors">
      <?= t('clients.new') ?> →
    </a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($clients as $client): ?>
    <a href="/clientes/<?= e($client['id']) ?>"
       class="group flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-5 transition-all hover:border-violet-500/30 hover:bg-white/[0.06] hover:-translate-y-0.5">
      <div class="flex items-start justify-between mb-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-sm font-bold text-violet-300">
          <?= strtoupper(substr($client['name'], 0, 2)) ?>
        </div>
        <?php
          $statusCls = $client['status'] === 'active'
            ? 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'
            : 'bg-gray-500/15 text-gray-400 ring-gray-500/30';
          $statusLabel = $client['status'] === 'active' ? t('status.active') : t('status.inactive');
        ?>
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 <?= $statusCls ?>">
          <?= $statusLabel ?>
        </span>
      </div>
      <h3 class="font-semibold text-white group-hover:text-violet-200 transition-colors truncate">
        <?= e($client['name']) ?>
      </h3>
      <?php if (!empty($client['segment'])): ?>
      <p class="text-sm text-gray-400 mt-0.5 truncate"><?= e($client['segment']) ?></p>
      <?php endif; ?>
      <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
        <?= e(strtoupper($client['language'] ?? 'pt')) ?>
        <span class="text-gray-700">·</span>
        <?= e($client['currency_code'] ?? 'BRL') ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($paginated['pages'] > 1): ?>
  <?php $base = '/clientes?' . ($q ? 'q=' . urlencode($q) . '&' : ''); ?>
  <div class="mt-8 flex items-center justify-center gap-1">
    <?php if ($paginated['page'] > 1): ?>
    <a href="<?= $base ?>page=<?= $paginated['page'] - 1 ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:border-violet-500/50 hover:text-white transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <?php endif; ?>
    <?php for ($p = 1; $p <= $paginated['pages']; $p++): ?>
    <a href="<?= $base ?>page=<?= $p ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg text-sm transition-colors
              <?= $p === $paginated['page'] ? 'bg-violet-600 text-white font-semibold' : 'border border-white/10 text-gray-400 hover:border-violet-500/50 hover:text-white' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
    <?php if ($paginated['page'] < $paginated['pages']): ?>
    <a href="<?= $base ?>page=<?= $paginated['page'] + 1 ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:border-violet-500/50 hover:text-white transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php view_end(); ?>
