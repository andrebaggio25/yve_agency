<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
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
<body class="min-h-full flex flex-col pb-14 sm:pb-0">

  <!-- Top nav -->
  <header class="flex-shrink-0 flex items-center justify-between px-4 sm:px-8 py-4"
          style="background:#111118; border-bottom:1px solid rgba(255,255,255,0.07);">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm text-white flex-shrink-0"
           style="background:#7c3aed;">
        <?= mb_strtoupper(mb_substr($client['name'] ?? 'C', 0, 1)) ?>
      </div>
      <div>
        <p class="text-sm font-semibold text-white leading-tight"><?= e($client['name'] ?? '') ?></p>
        <p class="text-xs text-gray-500 leading-tight">Portal do cliente</p>
      </div>
    </div>
    <?php $cpPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); ?>
    <nav class="hidden sm:flex items-center gap-1 text-sm">
      <?php
      $navItems = [
        "/portal/{$token}"           => 'Início',
        "/portal/{$token}/planos"    => 'Planos de Conteúdo',
        "/portal/{$token}/faturas"   => 'Faturas',
        "/portal/{$token}/contratos" => 'Contratos',
      ];
      foreach ($navItems as $href => $label):
        $isActive = ($href === "/portal/{$token}")
          ? ($cpPath === $href)
          : str_starts_with($cpPath, $href);
        $cls = $isActive
          ? 'px-3 py-2 rounded-lg text-violet-300 bg-violet-500/10 font-medium text-sm'
          : 'px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.06] transition-colors text-sm';
      ?>
      <a href="<?= $href ?>" class="<?= $cls ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </header>

  <!-- Flash -->
  <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
  <?php if ($flashSuccess || $flashError): ?>
  <div class="px-4 sm:px-8 pt-4">
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
  </div>
  <?php endif; ?>

  <!-- Main -->
  <main class="flex-1 px-4 sm:px-8 py-8 max-w-5xl mx-auto w-full">
    <?= view_slot('content') ?>
  </main>

  <!-- Mobile bottom nav -->
  <nav class="sm:hidden fixed bottom-0 left-0 right-0 flex"
       style="background:#111118; border-top:1px solid rgba(255,255,255,0.07);">
    <?php foreach ($navItems as $href => $label):
      $isActive = ($href === "/portal/{$token}")
        ? ($cpPath === $href)
        : str_starts_with($cpPath, $href);
      $cls = $isActive ? 'text-violet-400 font-medium' : 'text-gray-500';
    ?>
    <a href="<?= $href ?>" class="flex-1 flex flex-col items-center py-3 text-xs <?= $cls ?>">
      <?= explode(' ', $label)[0] ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <?= view_slot('scripts') ?>
</body>
</html>
