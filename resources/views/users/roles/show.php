<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <a href="/usuarios/perfis" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <?= t('roles.title') ?>
      </a>
      <h1 class="text-2xl font-bold text-white"><?= e($role['name']) ?></h1>
      <?php if (!empty($role['description'])): ?>
      <p class="mt-1 text-sm text-gray-400"><?= e($role['description']) ?></p>
      <?php endif; ?>
    </div>
    <?php if (\App\Support\Auth::can('roles.edit')): ?>
    <a href="/usuarios/perfis/<?= e($role['id']) ?>/editar"
       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      <?= t('common.edit') ?>
    </a>
    <?php endif; ?>
  </div>

  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4"><?= t('roles.permissions') ?></h2>
    <?php $rolePerms = $role['permissions'] ?? []; ?>
    <?php if (empty($rolePerms)): ?>
    <p class="text-sm text-gray-500"><?= t('roles.no_permissions') ?></p>
    <?php else: ?>
    <div class="space-y-4">
      <?php
        $grouped = [];
        foreach ($rolePerms as $p) {
            $key   = $p['slug'] ?? $p['name'];
            $parts = explode('.', $key);
            $group = $parts[0] ?? 'other';
            $grouped[$group][] = $p;
        }
      ?>
      <?php foreach ($grouped as $group => $perms): ?>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 mb-2"><?= e(ucfirst($group)) ?></p>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($perms as $perm): ?>
          <span class="inline-flex items-center rounded-lg bg-violet-500/10 px-3 py-1 text-xs font-medium text-violet-300">
            <?= e($perm['name']) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php view_end(); ?>
