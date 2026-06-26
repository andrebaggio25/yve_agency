<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(view_slot('title', $client['name'] ?? 'Portal')) ?> — Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
  <style>
    body { background: #09090f; color: #e2e8f0; font-family: ui-sans-serif, system-ui, sans-serif; }
    .card { background: #16161f; border: 1px solid rgba(255,255,255,0.07); border-radius: 1rem; }
    .btn-primary { background: #7c3aed; color: #fff; border-radius: .625rem; font-weight: 500; display: inline-flex; align-items: center; transition: opacity .15s; }
    .btn-primary:hover { opacity: .85; }
    .btn-secondary { background: rgba(255,255,255,.06); color: #d1d5db; border: 1px solid rgba(255,255,255,.07); border-radius: .625rem; font-weight: 500; display: inline-flex; align-items: center; transition: background .15s; }
    .btn-secondary:hover { background: rgba(255,255,255,.1); }
  </style>
</head>
<body class="min-h-full flex flex-col pb-16 sm:pb-0">

<?php
$agency = \App\Support\PortalAuth::agency();
$agencyLogoUrl = $agency['logo_url'] ?? null;
$clientLogoUrl = $client['logo_url'] ?? null;
?>

  <!-- Top nav -->
  <header class="flex-shrink-0 flex items-center justify-between px-4 sm:px-8 py-3"
          style="background:#111118; border-bottom:1px solid rgba(255,255,255,0.07);">

    <!-- Left: logos -->
    <div class="flex items-center gap-3 min-w-0">
      <!-- Client logo or avatar -->
      <?php if ($clientLogoUrl): ?>
      <img src="<?= e($clientLogoUrl) ?>" alt="<?= e($client['name'] ?? '') ?>"
           class="h-9 w-auto max-w-[120px] object-contain rounded-lg flex-shrink-0">
      <?php else: ?>
      <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm text-white flex-shrink-0"
           style="background:#7c3aed;">
        <?= mb_strtoupper(mb_substr($client['name'] ?? 'C', 0, 1)) ?>
      </div>
      <?php endif; ?>

      <div class="min-w-0">
        <p class="text-sm font-semibold text-white leading-tight truncate"><?= e($client['name'] ?? '') ?></p>
        <?php if ($agencyLogoUrl): ?>
        <img src="<?= e($agencyLogoUrl) ?>" alt="Agência"
             class="h-3.5 w-auto max-w-[80px] object-contain mt-0.5 opacity-60">
        <?php else: ?>
        <p class="text-[11px] text-gray-500 leading-tight"><?= e($agency['name'] ?? t('portal.subtitle')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: desktop nav -->
    <?php $cpPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); ?>
    <?php
    $navItems = [
      "/portal/{$token}"           => ['label' => t('portal.nav.home'),      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
      "/portal/{$token}/planos"    => ['label' => t('portal.nav.plans'),     'icon' => 'M4 6h16M4 10h16M4 14h10'],
      "/portal/{$token}/arquivos"  => ['label' => t('portal.nav.upload'),    'icon' => 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12'],
      "/portal/{$token}/faturas"   => ['label' => t('portal.nav.invoices'),  'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2'],
      "/portal/{$token}/contratos" => ['label' => t('portal.nav.contracts'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ];
    ?>
    <nav class="hidden sm:flex items-center gap-1 text-sm">
      <?php foreach ($navItems as $href => $nav):
        $isActive = ($href === "/portal/{$token}")
          ? ($cpPath === $href)
          : str_starts_with($cpPath, $href);
        $cls = $isActive
          ? 'px-3 py-2 rounded-lg text-violet-300 bg-violet-500/10 font-medium text-sm'
          : 'px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.06] transition-colors text-sm';
      ?>
      <a href="<?= $href ?>" class="<?= $cls ?>"><?= $nav['label'] ?></a>
      <?php endforeach; ?>
    </nav>
  </header>

  <!-- Flash -->
  <?php $flashSuccess = flash('success'); $flashError = flash('error'); $flashErrors = flash('errors'); ?>
  <?php if ($flashSuccess || $flashError || (is_array($flashErrors) && $flashErrors)): ?>
  <div class="px-4 sm:px-8 pt-4 space-y-2">
    <?php if ($flashSuccess): ?>
    <div class="rounded-xl px-4 py-3 text-sm text-green-300 bg-green-500/10 border border-green-500/20">
      <?= e($flashSuccess) ?>
    </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="rounded-xl px-4 py-3 text-sm text-red-300 bg-red-500/10 border border-red-500/20">
      <?= e($flashError) ?>
    </div>
    <?php endif; ?>
    <?php if (is_array($flashErrors) && $flashErrors): ?>
    <div class="rounded-xl px-4 py-3 text-sm text-red-300 bg-red-500/10 border border-red-500/20">
      <ul class="list-disc list-inside space-y-1">
        <?php foreach ($flashErrors as $msg): ?><li><?= e((string) $msg) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Main -->
  <main class="flex-1 px-4 sm:px-8 py-6 max-w-5xl mx-auto w-full">
    <?= view_slot('content') ?>
  </main>

  <!-- Mobile bottom nav -->
  <nav class="sm:hidden fixed bottom-0 left-0 right-0 flex safe-bottom"
       style="background:#111118; border-top:1px solid rgba(255,255,255,0.07); padding-bottom:env(safe-area-inset-bottom)">
    <?php foreach ($navItems as $href => $nav):
      $isActive = ($href === "/portal/{$token}")
        ? ($cpPath === $href)
        : str_starts_with($cpPath, $href);
      $cls = $isActive ? 'text-violet-400' : 'text-gray-600';
    ?>
    <a href="<?= $href ?>" class="flex-1 flex flex-col items-center py-3 gap-1 <?= $cls ?> transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="<?= $isActive ? '2' : '1.5' ?>" d="<?= $nav['icon'] ?>"/>
      </svg>
      <span class="text-[10px] font-medium"><?= $nav['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <?= view_slot('scripts') ?>
  <?= view_partial('form_loading') ?>
</body>
</html>
