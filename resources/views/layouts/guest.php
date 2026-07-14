<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(view_slot('title', 'Entrar')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>
    <!-- Marca YVE Beauty (ícone oficial: monograma dourado sobre preto) -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/brand/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('/assets/brand/favicon-16.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('/assets/brand/apple-touch-icon.png') ?>">
    <meta name="theme-color" content="#09090f">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
</head>
<body class="h-full bg-[#09090f] text-white noise">

<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">

    <!-- Marca: a logo oficial substitui o ícone genérico de raio.
         `alt` traz o nome (leitor de tela lê a marca, não "imagem"). -->
    <div class="mb-8 text-center">
        <img src="<?= asset('/assets/brand/logo-horizontal.png') ?>"
             alt="<?= e(env('APP_NAME', 'YVE Beauty')) ?>"
             class="mx-auto h-12 w-auto object-contain" width="240" height="50">
    </div>

    <!-- Card -->
    <div class="w-full max-w-sm">
        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.04] backdrop-blur-sm p-8 glow">
            <?= view_partial('flash') ?>
            <?= view_slot('content') ?>
        </div>
        <p class="mt-6 text-center text-xs text-gray-400">
            &copy; <?= date('Y') ?> <?= e(env('APP_NAME', 'YVE Agency')) ?>. Todos os direitos reservados.
        </p>
    </div>

</div>
</body>
</html>
