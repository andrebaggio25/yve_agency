<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <a href="/usuarios" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <?= t('users.title') ?>
      </a>
      <div class="flex items-center gap-4">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/10 text-xl font-bold text-brand-300">
          <?= strtoupper(substr($user['name'], 0, 2)) ?>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white"><?= e($user['name']) ?></h1>
          <p class="text-sm text-gray-400"><?= e($user['email']) ?></p>
        </div>
      </div>
    </div>
    <?php if (\App\Support\Auth::can('users.edit')): ?>
    <a href="/usuarios/<?= e($user['id']) ?>/editar"
       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      <?= t('common.edit') ?>
    </a>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <?php
      $statusCls = $user['status'] === 'active'
        ? 'text-emerald-300'
        : 'text-gray-400';
    ?>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('users.status') ?></p>
      <p class="font-semibold <?= $statusCls ?>">
        <?= $user['status'] === 'active' ? t('status.active') : t('status.inactive') ?>
      </p>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('users.role') ?></p>
      <p class="font-semibold text-white"><?= e($user['role_name'] ?? '—') ?></p>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('users.language') ?></p>
      <p class="font-semibold text-white uppercase"><?= e($user['language'] ?? 'pt') ?></p>
    </div>
  </div>

  <?php if (!empty($clientAccess)): ?>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4"><?= t('users.client_access') ?></h2>
    <div class="space-y-2">
      <?php foreach ($clientAccess as $c): ?>
      <div class="flex items-center gap-3 rounded-xl border border-white/5 px-4 py-2.5">
        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500/10 text-xs font-bold text-brand-300">
          <?= strtoupper(substr($c['name'], 0, 2)) ?>
        </div>
        <span class="text-sm text-gray-300"><?= e($c['name']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
