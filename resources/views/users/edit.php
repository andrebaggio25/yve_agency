<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8">
    <a href="/usuarios" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= t('users.title') ?>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= t('users.edit') ?></h1>
  </div>

  <?php if (has_flash('error')): ?>
  <div class="mb-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e(flash('error')) ?>
  </div>
  <?php endif; ?>

  <form action="/usuarios/<?= e($user['id']) ?>" method="POST" class="space-y-6">
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500"><?= t('common.basic_info') ?></h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
          <?= t('users.name') ?> <span class="text-red-400">*</span>
        </label>
        <input type="text" name="name" value="<?= old('name', $user['name']) ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
          <?= t('users.email') ?> <span class="text-red-400">*</span>
        </label>
        <input type="email" name="email" value="<?= old('email', $user['email']) ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('users.new_password') ?></label>
        <input type="password" name="password" minlength="8"
               placeholder="<?= t('users.leave_blank_to_keep') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('users.role') ?></label>
          <select name="role_id"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
            <option value=""><?= t('common.select') ?>...</option>
            <?php foreach ($roles as $role): ?>
            <option value="<?= e($role['id']) ?>" <?= old('role_id', $user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
              <?= e($role['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('users.status') ?></label>
          <select name="status"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
            <option value="active" <?= old('status', $user['status']) === 'active' ? 'selected' : '' ?>><?= t('status.active') ?></option>
            <option value="inactive" <?= old('status', $user['status']) === 'inactive' ? 'selected' : '' ?>><?= t('status.inactive') ?></option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('users.language') ?></label>
        <select name="language"
                class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
          <?php foreach (\App\Core\Lang::supportedLocales() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= old('language', $user['language'] ?? 'pt') === $code ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-500"><?= t('users.language_hint') ?></p>
      </div>
    </div>

    <div class="flex items-center justify-between">
      <?php if (\App\Support\Auth::can('users.delete') && \App\Support\Auth::id() !== (int)$user['id']): ?>
      <form action="/usuarios/<?= e($user['id']) ?>" method="POST" class="inline"
            onsubmit="return confirm('<?= t('users.confirm_delete') ?>')">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors">
          <?= t('common.delete') ?>
        </button>
      </form>
      <?php else: ?>
      <div></div>
      <?php endif; ?>
      <div class="flex items-center gap-3">
        <a href="/usuarios" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm text-gray-400 hover:text-white transition-colors">
          <?= t('common.cancel') ?>
        </a>
        <button type="submit"
                class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all hover:scale-105 active:scale-95">
          <?= t('common.save') ?>
        </button>
      </div>
    </div>
  </form>
</div>

<?php view_end(); ?>
