<?php view_layout('guest') ?>
<?php view_start('title') ?><?= t('auth.forgot_password') ?><?php view_end() ?>

<?php view_start('content') ?>
<h2 class="text-base font-semibold text-white mb-1"><?= t('auth.forgot_password') ?></h2>
<p class="text-sm text-gray-400 mb-6"><?= t('auth.forgot_intro') ?></p>

<form method="POST" action="/esqueci-senha" class="space-y-4">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">
            <?= t('auth.email') ?>
        </label>
        <input id="email" name="email" type="email" autocomplete="email" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-500
                      focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
    </div>

    <button type="submit"
            class="mt-2 w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-gray-950
                   shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all hover:scale-[1.02] active:scale-[0.98]">
        <?= t('auth.send_reset_link') ?>
    </button>
</form>

<a href="/login" class="mt-4 block text-center text-sm text-brand-400 hover:text-brand-300 transition-colors">
    <?= t('auth.back_to_login') ?>
</a>
<?php view_end() ?>
