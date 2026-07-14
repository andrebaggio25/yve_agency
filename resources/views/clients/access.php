<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8">
    <a href="/clientes/<?= e($clientId) ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= t('clients.title') ?>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= t('clients.manage_access') ?></h1>
    <p class="mt-1 text-sm text-gray-400"><?= t('clients.access_hint') ?></p>
  </div>

  <?php if (has_flash('success')): ?>
  <div class="mb-6 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
    <?= e(flash('success')) ?>
  </div>
  <?php endif; ?>

  <?php if (has_flash('error')): ?>
  <div class="mb-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e(flash('error')) ?>
  </div>
  <?php endif; ?>

  <div class="rounded-2xl border border-white/5 bg-white/[0.03] divide-y divide-white/5">
    <div class="p-5">
      <h2 class="text-sm font-semibold text-white"><?= t('clients.current_users') ?></h2>
    </div>

    <?php if (empty($accesses)): ?>
    <div class="px-5 py-8 text-center text-sm text-gray-400"><?= t('clients.no_users_linked') ?></div>
    <?php else: ?>
    <?php foreach ($accesses as $access): ?>
    <div class="flex items-center justify-between px-5 py-3">
      <div>
        <p class="text-sm font-medium text-white"><?= e($access['user_name'] ?? $access['name'] ?? '—') ?></p>
        <p class="text-xs text-gray-400"><?= e($access['user_email'] ?? $access['email'] ?? '') ?></p>
      </div>
      <form action="/clientes/<?= e($clientId) ?>/acesso/<?= e($access['user_id'] ?? $access['id']) ?>" method="POST">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">
          <?= t('common.remove') ?>
        </button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!empty($users)): ?>
  <div class="mt-6 rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-4"><?= t('clients.link_user') ?></h2>
    <form action="/clientes/<?= e($clientId) ?>/acesso" method="POST" class="flex gap-3">
      <?= csrf_field() ?>
      <select name="user_id"
              class="flex-1 rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
        <?php foreach ($users as $u): ?>
        <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit"
              class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
        <?= t('common.add') ?>
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
