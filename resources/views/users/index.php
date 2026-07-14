<?php view_layout('app'); view_start('content'); ?>

<div>
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1"><?= t('nav.users') ?></p>
      <h1 class="text-2xl font-bold text-white"><?= t('users.title') ?></h1>
      <p class="mt-1 text-sm text-gray-400"><?= count($users) ?> usuário<?= count($users) !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('users.create')): ?>
    <a href="/usuarios/novo"
       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-105 active:scale-95">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      <?= t('users.new') ?>
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($users)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-brand-500/10 p-6">
      <svg class="w-12 h-12 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
    </div>
    <p class="text-gray-400"><?= t('users.no_users') ?></p>
    <?php if (\App\Support\Auth::can('users.create')): ?>
    <a href="/usuarios/novo" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">
      <?= t('users.new') ?> →
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5">
          <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"><?= t('users.name') ?></th>
          <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"><?= t('users.role') ?></th>
          <th class="hidden md:table-cell px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"><?= t('users.status') ?></th>
          <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"><?= t('users.language') ?></th>
          <th class="px-5 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($users as $user): ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-5 py-4">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10 text-xs font-bold text-brand-300 shrink-0">
                <?= strtoupper(substr($user['name'], 0, 2)) ?>
              </div>
              <div>
                <p class="font-medium text-white"><?= e($user['name']) ?></p>
                <p class="text-xs text-gray-500"><?= e($user['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="hidden sm:table-cell px-5 py-4 text-gray-300"><?= e($user['role_name'] ?? '—') ?></td>
          <td class="hidden md:table-cell px-5 py-4">
            <?php
              $cls = $user['status'] === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-gray-500/15 text-gray-400';
            ?>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $cls ?>">
              <?= $user['status'] === 'active' ? t('status.active') : t('status.inactive') ?>
            </span>
          </td>
          <td class="px-5 py-4 text-xs text-gray-500 uppercase"><?= e($user['language'] ?? 'pt') ?></td>
          <td class="px-5 py-4 text-right">
            <?php if (\App\Support\Auth::can('users.edit')): ?>
            <a href="/usuarios/<?= e($user['id']) ?>/editar" class="text-xs text-gray-400 hover:text-brand-300 transition-colors">
              <?= t('common.edit') ?>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
