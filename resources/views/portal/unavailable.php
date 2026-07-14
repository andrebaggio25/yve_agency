<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('portal.unavailable.title') ?></title>
  <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
  <style>body { background: #09090f; color: #e2e8f0; font-family: ui-sans-serif, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-sm">
    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-red-500/10 flex items-center justify-center">
      <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
    </div>
    <h1 class="text-xl font-semibold text-white mb-2"><?= t('portal.unavailable.title') ?></h1>
    <p class="text-gray-400 text-sm"><?= t('portal.unavailable.text') ?></p>
  </div>
</body>
</html>
