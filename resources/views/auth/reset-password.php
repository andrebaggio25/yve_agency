<?php view_layout('guest') ?>
<?php view_start('title') ?><?= t('auth.reset_password') ?><?php view_end() ?>

<?php view_start('content') ?>
<h2 class="text-base font-semibold text-white mb-1"><?= t('auth.reset_password') ?></h2>

<?php if (!$valid): ?>
<p class="text-sm text-gray-400 mb-6"><?= t('auth.reset_link_invalid') ?></p>
<a href="/esqueci-senha" class="block text-center text-sm text-brand-400 hover:text-brand-300 transition-colors">
    <?= t('auth.send_reset_link') ?>
</a>
<?php else: ?>
<p class="text-sm text-gray-400 mb-6"><?= t('auth.reset_intro') ?></p>

<form method="POST" action="/redefinir-senha" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div>
        <label for="password" class="block text-sm font-medium text-gray-300 mb-1.5">
            <?= t('auth.new_password') ?>
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-500
                      focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-1.5">
            <?= t('auth.confirm_password') ?>
        </label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-500
                      focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
    </div>

    <button type="submit"
            class="mt-2 w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-gray-950
                   shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all hover:scale-[1.02] active:scale-[0.98]">
        <?= t('auth.reset_password') ?>
    </button>
</form>
<?php endif; ?>
<?php view_end() ?>
