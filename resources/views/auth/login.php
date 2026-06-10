<?php view_layout('guest') ?>

<?php view_start('title') ?>Entrar<?php view_end() ?>

<?php view_start('content') ?>
<h2 class="text-lg font-semibold text-gray-900 mb-6 text-center">Acesse sua conta</h2>

<form method="POST" action="/login" class="space-y-5">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="email" required
               value="<?= old('email') ?>"
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm
                      focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm
                      focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600">
            Lembrar-me
        </label>
        <a href="/esqueci-senha" class="text-sm text-indigo-600 hover:text-indigo-800">Esqueceu a senha?</a>
    </div>

    <button type="submit"
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                   hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        Entrar
    </button>
</form>
<?php view_end() ?>
