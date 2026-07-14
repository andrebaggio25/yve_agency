<?php view_layout('guest') ?>
<?php view_start('title') ?>Entrar<?php view_end() ?>

<?php view_start('content') ?>
<h2 class="text-base font-semibold text-white mb-1"><?= t('auth.welcome') ?></h2>
<p class="text-sm text-gray-400 mb-6"><?= t('auth.sign_in_to') ?></p>

<?php if ($error = flash('error')): ?>
<div class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form method="POST" action="/login" class="space-y-4">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">
            <?= t('auth.email') ?>
        </label>
        <input id="email" name="email" type="email" autocomplete="email" required
               value="<?= old('email') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-500
                      focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-300 mb-1.5">
            <?= t('auth.password') ?>
        </label>
        <input id="password" name="password" type="password" autocomplete="current-password" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-500
                      focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors">
    </div>

    <div class="flex items-center justify-between pt-1">
        <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer select-none">
            <input type="checkbox" name="remember"
                   class="rounded border-white/20 bg-white/5 text-brand-500 focus:ring-brand-500 focus:ring-offset-0">
            Lembrar-me
        </label>
        <a href="/esqueci-senha" class="text-sm text-brand-400 hover:text-brand-300 transition-colors">
            <?= t('auth.forgot_password') ?>
        </a>
    </div>

    <button type="submit"
            class="mt-2 w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-gray-950
                   shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all hover:scale-[1.02] active:scale-[0.98]">
        <?= t('auth.login') ?>
    </button>
</form>
<?php view_end() ?>
