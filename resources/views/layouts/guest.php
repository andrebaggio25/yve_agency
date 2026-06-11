<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(view_slot('title', 'Entrar')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        html { background: #09090f; }
        .noise { background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E"); }
        .glow { box-shadow: 0 0 80px -20px rgba(139,92,246,0.3); }
    </style>
</head>
<body class="h-full bg-[#09090f] text-white noise">

<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">

    <!-- Logo / Brand -->
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-violet-600 shadow-lg shadow-violet-500/30 mb-4">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight"><?= e(env('APP_NAME', 'YVE Agency')) ?></h1>
    </div>

    <!-- Card -->
    <div class="w-full max-w-sm">
        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.04] backdrop-blur-sm p-8 glow">
            <?= view_partial('flash') ?>
            <?= view_slot('content') ?>
        </div>
        <p class="mt-6 text-center text-xs text-gray-600">
            &copy; <?= date('Y') ?> <?= e(env('APP_NAME', 'YVE Agency')) ?>. Todos os direitos reservados.
        </p>
    </div>

</div>
</body>
</html>
