<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Não encontrado</title>
  <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
  <style>body { background: #0a0a12; }</style>
</head>
<body class="flex min-h-screen items-center justify-center p-4">
  <div class="text-center">
    <p class="text-7xl font-bold text-brand-500 mb-4">404</p>
    <h1 class="text-2xl font-semibold text-white mb-2">Página não encontrada</h1>
    <p class="text-gray-400 mb-8">O recurso que você procura não existe ou foi movido.</p>
    <a href="/dashboard" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500 transition-colors">
      ← Voltar ao dashboard
    </a>
  </div>
</body>
</html>
