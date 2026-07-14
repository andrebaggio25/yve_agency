<?php view_layout('app'); view_start('content'); ?>

<div>
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1"><?= t('nav.settings') ?></p>
      <h1 class="text-2xl font-bold text-white"><?= t('roles.title') ?></h1>
      <p class="mt-1 text-sm text-gray-400"><?= count($roles) ?> perfil<?= count($roles) !== 1 ? 'is' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('roles.create')): ?>
    <a href="/usuarios/perfis/novo"
       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-105 active:scale-95">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      <?= t('roles.new') ?>
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($roles)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-brand-500/10 p-6">
      <svg class="w-12 h-12 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
    </div>
    <p class="text-gray-400"><?= t('roles.no_roles') ?></p>
    <?php if (\App\Support\Auth::can('roles.create')): ?>
    <a href="/usuarios/perfis/novo" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">
      <?= t('roles.new') ?> →
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($roles as $role): ?>
    <div class="group rounded-2xl border border-white/5 bg-white/[0.03] p-5 hover:border-brand-500/20 transition-all">
      <div class="flex items-start justify-between mb-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10">
          <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <?php if (!\App\Support\Auth::can('roles.edit')): ?>
        <span class="text-xs text-gray-400"><?= t('roles.system') ?></span>
        <?php endif; ?>
      </div>
      <h3 class="font-semibold text-white mb-1"><?= e($role['name']) ?></h3>
      <?php if (!empty($role['description'])): ?>
      <p class="text-sm text-gray-400 mb-3"><?= e($role['description']) ?></p>
      <?php endif; ?>
      <p class="text-xs text-gray-400"><?= count($role['permissions'] ?? []) ?> <?= t('roles.permissions') ?></p>
      <?php if (\App\Support\Auth::can('roles.edit')): ?>
      <div class="mt-4 flex gap-2">
        <a href="/usuarios/perfis/<?= e($role['id']) ?>" class="flex-1 text-center rounded-lg border border-white/10 px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-colors">
          <?= t('common.view') ?>
        </a>
        <a href="/usuarios/perfis/<?= e($role['id']) ?>/editar" class="flex-1 text-center rounded-lg bg-brand-500/10 px-3 py-1.5 text-xs text-brand-300 hover:bg-brand-500/20 transition-colors">
          <?= t('common.edit') ?>
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
