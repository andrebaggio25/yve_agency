<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Erro interno</title>
  <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
  <style>body { background: #0a0a12; }</style>
</head>
<body class="flex min-h-screen items-center justify-center p-4">
  <div class="text-center max-w-lg w-full">
    <p class="text-7xl font-bold text-red-500 mb-4">500</p>
    <h1 class="text-2xl font-semibold text-white mb-2">Erro interno do servidor</h1>
    <p class="text-gray-400 mb-8">Algo deu errado. Tente novamente em alguns instantes.</p>

    <?php if (!empty($message)): ?>
    <div class="text-left rounded-xl border border-red-500/20 bg-red-500/10 p-4 mb-4">
      <p class="text-sm font-mono text-red-300 break-all"><?= htmlspecialchars($message) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($trace)): ?>
    <pre class="text-left text-xs text-gray-500 bg-white/[0.03] border border-white/[0.06] rounded-xl p-4 overflow-x-auto mb-6"><?= htmlspecialchars($trace) ?></pre>
    <?php endif; ?>

    <a href="/dashboard" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500 transition-colors">
      ← Voltar ao dashboard
    </a>
  </div>
</body>
</html>
