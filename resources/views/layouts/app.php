<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(view_slot('title', 'YVE Agency')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              gray: {
                925: '#0f1117',
                950: '#09090f',
              }
            },
            fontFamily: {
              sans: ['"Inter"', 'system-ui', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
      /* Dark base */
      * { -webkit-font-smoothing: antialiased; }
      html { background: #09090f; }
      body { background: #09090f; color: #e5e7eb; }

      /* Custom scrollbar */
      ::-webkit-scrollbar { width: 4px; height: 4px; }
      ::-webkit-scrollbar-track { background: transparent; }
      ::-webkit-scrollbar-thumb { background: rgba(139,92,246,0.3); border-radius: 4px; }
      ::-webkit-scrollbar-thumb:hover { background: rgba(139,92,246,0.5); }

      /* Sidebar active glow */
      .nav-active { box-shadow: 0 0 0 1px rgba(139,92,246,0.4), inset 0 0 0 1px rgba(139,92,246,0.2); }

      /* Soft gradient text */
      .gradient-text {
        background: linear-gradient(135deg, #a78bfa 0%, #818cf8 50%, #c084fc 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      /* Animate fade-in for page content */
      @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      .page-enter { animation: fadeInUp 0.25s ease-out; }

      /* Mobile nav overlay */
      #mobile-nav { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s; }
    </style>

    <?= view_slot('head') ?>
</head>
<body class="h-full font-sans" x-data="appShell()" @keydown.escape="mobileSidebarOpen = false">

<!-- ── Mobile sidebar overlay ──────────────────────────────────────────── -->
<div x-show="mobileSidebarOpen" x-transition.opacity
     class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
     @click="mobileSidebarOpen = false" style="display:none"></div>

<!-- ── Layout wrapper ──────────────────────────────────────────────────── -->
<div class="flex h-full min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
  <aside id="mobile-nav"
         :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
         class="fixed inset-y-0 left-0 z-50 w-64 flex-shrink-0 flex flex-col
                border-r border-white/[0.06] bg-[#0d0d14]
                lg:static lg:block transition-transform">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/[0.06]">
      <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-violet-800 shadow-lg shadow-violet-500/20 flex-shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold text-white truncate gradient-text">YVE Agency</p>
        <p class="text-xs text-gray-500 truncate"><?= e(\App\Support\Auth::user()['name'] ?? 'Usuário') ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
      <?= view_partial('nav') ?>
    </nav>

    <!-- Logout -->
    <div class="p-3 border-t border-white/[0.06]">
      <form method="POST" action="/logout">
        <?= csrf_field() ?>
        <button type="submit"
                class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-400 hover:text-white hover:bg-white/5 transition-all">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Sair
        </button>
      </form>
    </div>
  </aside>

  <!-- ── Main area ─────────────────────────────────────────────────────── -->
  <div class="flex flex-1 flex-col min-w-0 overflow-hidden">

    <!-- ── Top bar ────────────────────────────────────────────────────── -->
    <header class="flex-shrink-0 flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5
                   border-b border-white/[0.06] bg-[#0d0d14]/80 backdrop-blur-sm sticky top-0 z-30">
      <!-- Mobile hamburger -->
      <button @click="mobileSidebarOpen = !mobileSidebarOpen" class="lg:hidden -ml-1 flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <!-- Breadcrumb / page title -->
      <div class="flex-1 min-w-0">
        <span class="text-sm text-gray-400 truncate"><?= view_slot('breadcrumb', '') ?></span>
      </div>

      <!-- User pill -->
      <div class="flex-shrink-0 flex items-center gap-2">
        <div class="hidden sm:flex h-8 w-8 rounded-full bg-gradient-to-br from-violet-500 to-violet-700 items-center justify-center text-xs font-bold text-white shadow-lg shadow-violet-500/20">
          <?= strtoupper(substr(\App\Support\Auth::user()['name'] ?? 'U', 0, 1)) ?>
        </div>
        <span class="text-sm text-gray-300 hidden sm:block max-w-[120px] truncate"><?= e(\App\Support\Auth::user()['name'] ?? '') ?></span>
      </div>
    </header>

    <!-- ── Flash messages ─────────────────────────────────────────────── -->
    <?php
    $flashSuccess = flash('success');
    $flashError   = flash('error');
    $flashInfo    = flash('info');
    ?>
    <?php if ($flashSuccess || $flashError || $flashInfo): ?>
    <div class="px-4 sm:px-6 pt-4 space-y-2">
      <?php if ($flashSuccess): ?>
      <div x-data="{show:true}" x-show="show" x-transition
           class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?= e($flashSuccess) ?>
        <button @click="show=false" class="ml-auto text-emerald-400 hover:text-emerald-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <?php endif; ?>
      <?php if ($flashError): ?>
      <div x-data="{show:true}" x-show="show" x-transition
           class="flex items-center gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= e($flashError) ?>
        <button @click="show=false" class="ml-auto text-rose-400 hover:text-rose-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <?php endif; ?>
      <?php if ($flashInfo): ?>
      <div x-data="{show:true}" x-show="show" x-transition
           class="flex items-center gap-3 rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-300">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= e($flashInfo) ?>
        <button @click="show=false" class="ml-auto text-blue-400 hover:text-blue-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Page content ───────────────────────────────────────────────── -->
    <main class="flex-1 overflow-y-auto">
      <div class="px-4 sm:px-6 py-6 page-enter">
        <?= view_slot('content') ?>
      </div>
    </main>
  </div>
</div>

<?= view_slot('scripts') ?>

<script>
function appShell() {
  return {
    mobileSidebarOpen: false
  }
}
</script>
</body>
</html>
