<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8">
    <a href="/usuarios/perfis" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= t('roles.title') ?>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= t('roles.new') ?></h1>
  </div>

  <?php if (has_flash('error')): ?>
  <div class="mb-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e(flash('error')) ?>
  </div>
  <?php endif; ?>

  <form action="/usuarios/perfis" method="POST" class="space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
          <?= t('roles.name') ?> <span class="text-red-400">*</span>
        </label>
        <input type="text" name="name" value="<?= old('name') ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('roles.description') ?></label>
        <input type="text" name="description" value="<?= old('description') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>
    </div>

    <?php if (!empty($allPermissions)): ?>
    <?php
      // Group by module (first segment before the dot)
      $permGroups = [];
      foreach ($allPermissions as $slug => $label) {
          $module = explode('.', $slug)[0];
          $permGroups[$module][] = ['slug' => $slug, 'label' => $label];
      }
    ?>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4"><?= t('roles.permissions') ?></h2>
      <div class="space-y-4">
        <?php foreach ($permGroups as $group => $perms): ?>
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 mb-2"><?= e(ucfirst($group)) ?></p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <?php foreach ($perms as $perm): ?>
            <label class="flex items-center gap-3 rounded-xl border border-white/5 px-3 py-2.5 cursor-pointer hover:border-violet-500/20 transition-colors">
              <input type="checkbox" name="permissions[]" value="<?= e($perm['slug']) ?>"
                     class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500 focus:ring-offset-0">
              <span class="text-sm text-gray-300"><?= e($perm['label']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-end gap-3">
      <a href="/usuarios/perfis" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm text-gray-400 hover:text-white transition-colors">
        <?= t('common.cancel') ?>
      </a>
      <button type="submit"
              class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all hover:scale-105 active:scale-95">
        <?= t('common.save') ?>
      </button>
    </div>
  </form>
</div>

<?php view_end(); ?>
