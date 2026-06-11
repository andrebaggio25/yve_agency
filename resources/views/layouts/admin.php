<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(view_slot('title', 'Admin')) ?> — YVE Platform Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: { gray: { 925: '#0f1117', 950: '#09090f' } },
            fontFamily: { sans: ['"Inter"', 'system-ui', 'sans-serif'] }
          }
        }
      }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
      * { -webkit-font-smoothing: antialiased; }
      html,body { background: #09090f; color: #e5e7eb; }
      ::-webkit-scrollbar { width: 4px; }
      ::-webkit-scrollbar-thumb { background: rgba(239,68,68,0.3); border-radius: 4px; }
      .nav-active { box-shadow: 0 0 0 1px rgba(239,68,68,0.4), inset 0 0 0 1px rgba(239,68,68,0.2); }
      @keyframes fadeInUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
      .page-enter { animation: fadeInUp 0.25s ease-out; }

      /* Utility classes shared with tenant layout */
      .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 1rem; }
      .input-field { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.10); border-radius: 0.75rem; color: #fff; padding: 0.5rem 0.875rem; font-size: 0.875rem; transition: border-color .15s; outline: none; }
      .input-field:focus { border-color: rgba(239,68,68,0.5); box-shadow: 0 0 0 2px rgba(239,68,68,0.15); }
      .input-field option { background: #1a1a2e; }
      .label-field { display: block; font-size: 0.75rem; font-weight: 500; color: #9ca3af; margin-bottom: 0.375rem; }
      .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 0.75rem; transition: all .15s; border: none; cursor: pointer; }
      .btn-primary:hover { background: linear-gradient(135deg,#ef4444,#dc2626); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,.25); }
      .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; background: rgba(255,255,255,0.05); color: #d1d5db; font-size: 0.875rem; font-weight: 500; padding: 0.5rem 1rem; border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); transition: all .15s; cursor: pointer; text-decoration: none; }
      .btn-secondary:hover { background: rgba(255,255,255,0.08); color: #fff; }
      .badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    </style>
    <?= view_slot('head') ?>
</head>
<body class="h-full font-sans" x-data="{open:false}">

<div class="flex h-full min-h-screen">

  <!-- Sidebar -->
  <aside class="fixed inset-y-0 left-0 z-50 w-64 flex-shrink-0 flex flex-col border-r border-white/[0.06] bg-[#0d0d14] lg:static lg:block">

    <!-- Logo — distinguível do painel tenant -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/[0.06]">
      <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-red-600 to-red-800 shadow-lg shadow-red-500/20 flex-shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
      </div>
      <div>
        <p class="text-sm font-bold text-white">Platform Admin</p>
        <p class="text-xs text-red-400 font-medium">YVE Agency</p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
      <?php
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $navItems = [
          ['href' => '/admin', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
          ['href' => '/admin/tenants', 'label' => 'Tenants', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
          ['href' => '/admin/usuarios', 'label' => 'Usuários', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
          ['href' => '/admin/planos',        'label' => 'Planos',          'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
          ['href' => '/admin/assinaturas',   'label' => 'Assinaturas',     'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
          ['href' => '/admin/configuracoes', 'label' => 'Configurações',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ];
        foreach ($navItems as $item):
          $isActive = ($currentPath === $item['href']) || ($item['href'] !== '/admin' && str_starts_with($currentPath, $item['href']));
      ?>
      <a href="<?= e($item['href']) ?>"
         class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all <?= $isActive ? 'text-white bg-red-500/10 nav-active' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
        </svg>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Footer: user info + logout -->
    <div class="p-3 border-t border-white/[0.06] space-y-1">
      <div class="px-3 py-2">
        <p class="text-xs font-medium text-white truncate"><?= e(\App\Support\Auth::user()['name'] ?? 'Admin') ?></p>
        <p class="text-xs text-gray-500 truncate"><?= e(\App\Support\Auth::user()['email'] ?? '') ?></p>
      </div>
      <form method="POST" action="/logout">
        <?= csrf_field() ?>
        <button type="submit" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-400 hover:text-white hover:bg-white/5 transition-all">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Sair
        </button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex flex-1 flex-col min-w-0 overflow-hidden">

    <!-- Top bar -->
    <header class="flex-shrink-0 flex items-center gap-4 px-6 py-3.5 border-b border-white/[0.06] bg-[#0d0d14]/80 backdrop-blur-sm sticky top-0 z-30">
      <div class="flex-1">
        <span class="text-sm text-gray-400"><?= view_slot('breadcrumb', '') ?></span>
      </div>
      <div class="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-1.5">
        <div class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></div>
        <span class="text-xs font-semibold text-red-400">Platform Admin</span>
      </div>
    </header>

    <!-- Flash messages -->
    <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
    <?php if ($flashSuccess || $flashError): ?>
    <div class="px-6 pt-4 space-y-2">
      <?php if ($flashSuccess): ?>
      <div x-data="{show:true}" x-show="show" x-transition class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?= e($flashSuccess) ?>
        <button @click="show=false" class="ml-auto text-emerald-400">✕</button>
      </div>
      <?php endif; ?>
      <?php if ($flashError): ?>
      <div x-data="{show:true}" x-show="show" x-transition class="flex items-center gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= e($flashError) ?>
        <button @click="show=false" class="ml-auto text-rose-400">✕</button>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Content -->
    <main class="flex-1 overflow-y-auto">
      <div class="px-6 py-6 page-enter">
        <?= view_slot('content') ?>
      </div>
    </main>
  </div>
</div>

<?= view_slot('scripts') ?>
</body>
</html>
