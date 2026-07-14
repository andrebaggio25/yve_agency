<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8">
    <a href="/clientes" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= t('clients.title') ?>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= t('clients.new') ?></h1>
  </div>

  <?php if (has_flash('error')): ?>
  <div class="mb-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e(flash('error')) ?>
  </div>
  <?php endif; ?>

  <form action="/clientes" method="POST" class="space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500"><?= t('common.basic_info') ?></h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
          <?= t('clients.name') ?> <span class="text-red-400">*</span>
        </label>
        <input type="text" name="name" value="<?= old('name') ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.segment') ?></label>
        <input type="text" name="segment" value="<?= old('segment') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.status') ?></label>
          <select name="status"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
            <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>><?= t('status.active') ?></option>
            <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>><?= t('status.inactive') ?></option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.currency') ?></label>
          <select name="currency_code"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
            <option value="BRL" <?= old('currency_code', 'BRL') === 'BRL' ? 'selected' : '' ?>>BRL — Real</option>
            <option value="USD" <?= old('currency_code') === 'USD' ? 'selected' : '' ?>>USD — Dollar</option>
            <option value="EUR" <?= old('currency_code') === 'EUR' ? 'selected' : '' ?>>EUR — Euro</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.approval_language') ?></label>
        <select name="language"
                class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
          <?php foreach (\App\Core\Lang::supportedLocales() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= old('language', 'pt') === $code ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-500"><?= t('clients.approval_language_hint') ?></p>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3">
      <a href="/clientes" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm text-gray-400 hover:text-white transition-colors">
        <?= t('common.cancel') ?>
      </a>
      <button type="submit"
              class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all hover:scale-105 active:scale-95">
        <?= t('common.save') ?>
      </button>
    </div>
  </form>
</div>

<?php view_end(); ?>
