<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('portal.rate_limited.title') ?></title>
  <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
  <style>body { background: #09090f; color: #e2e8f0; font-family: ui-sans-serif, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-sm">
    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-amber-500/10 flex items-center justify-center">
      <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h1 class="text-xl font-semibold text-white mb-2"><?= t('portal.rate_limited.title') ?></h1>
    <p class="text-gray-400 text-sm"><?= t('portal.rate_limited.text') ?></p>
    <?php if (!empty($retry_after)): ?>
    <p class="text-gray-500 text-xs mt-2"><?= t('portal.rate_limited.retry', ['seconds' => (int) $retry_after]) ?></p>
    <?php endif; ?>
  </div>
</body>
</html>
