<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><title>429 — Muitas requisições</title>
<link rel="stylesheet" href="<?= asset('/css/app.css') ?>"></head>
<body class="flex min-h-screen items-center justify-center bg-gray-100">
<div class="text-center">
    <p class="text-6xl font-bold text-orange-500">429</p>
    <h1 class="mt-4 text-2xl font-semibold text-gray-900">Muitas requisições</h1>
    <p class="mt-2 text-gray-500">Você excedeu o limite de tentativas. Tente novamente em instantes.</p>
    <?php if (!empty($retry_after)): ?>
    <p class="mt-1 text-sm text-gray-400">Aguarde <?= (int)$retry_after ?> segundo(s).</p>
    <?php endif; ?>
    <a href="/login" class="mt-6 inline-block text-indigo-600 hover:underline">Voltar ao login</a>
</div>
</body></html>
