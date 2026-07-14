<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(view_slot('title', 'YVE Agency')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>

    <!-- Assets locais (FE-01): sem CDN em runtime — CSS purgado + vendor self-hosted.
         Fonte Inter continua no Google Fonts (é CSS/webfont, não script executável). -->
    <!-- Marca YVE Beauty (ícone oficial: monograma dourado sobre preto) -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/brand/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('/assets/brand/favicon-16.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('/assets/brand/apple-touch-icon.png') ?>">
    <meta name="theme-color" content="#09090f">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
    <script src="<?= asset('/js/api.js') ?>"></script>
    <!-- Chart.js ANTES do Alpine, de propósito: o bundle do Alpine chama
         Alpine.start() assim que executa, e os `defer` rodam em ordem de
         documento. Com o Alpine primeiro, todo x-init que usa `new Chart(...)`
         morria com "Chart is not defined" — os gráficos simplesmente não
         apareciam. -->
    <script defer src="<?= asset('/js/vendor/chart.umd.min.js') ?>"></script>
    <script defer src="<?= asset('/js/vendor/alpine.min.js') ?>"></script>

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

    <!-- Marca: monograma oficial (YB dourado). O nome sai do env, então uma
         agência white-label troca a marca sem editar o layout. -->
    <a href="/dashboard" class="flex items-center gap-3 px-5 py-5 border-b border-white/[0.06] hover:bg-white/[0.02] transition-colors">
      <img src="<?= asset('/assets/brand/monograma.png') ?>" alt=""
           class="h-9 w-9 object-contain flex-shrink-0" width="36" height="36">
      <div class="min-w-0">
        <p class="text-sm font-bold truncate gradient-text"><?= e(env('APP_NAME', 'YVE Beauty')) ?></p>
        <p class="text-xs text-gray-400 truncate"><?= e(\App\Support\Auth::user()['name'] ?? 'Usuário') ?></p>
      </div>
    </a>

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
      <button @click="mobileSidebarOpen = !mobileSidebarOpen" aria-label="Abrir menu" class="lg:hidden -ml-1 flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <!-- Breadcrumb / page title -->
      <div class="flex-1 min-w-0">
        <span class="text-sm text-gray-400 truncate"><?= view_slot('breadcrumb', '') ?></span>
      </div>

      <!-- Notifications bell -->
      <div class="flex-shrink-0 relative" x-data="notifBell()" x-init="init()">
        <button @click="toggle()" aria-label="Notificações" :aria-expanded="open" class="relative flex items-center justify-center w-9 h-9 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span x-show="count > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 flex items-center justify-center rounded-full bg-brand-500 text-[10px] font-bold text-gray-950 px-1"
                x-text="count > 9 ? '9+' : count"></span>
        </button>
        <!-- Dropdown -->
        <div x-show="open" x-transition @click.outside="open = false"
             class="absolute right-0 top-12 w-80 rounded-2xl border border-white/10 bg-[#0d0d14] shadow-2xl shadow-black/40 z-50 overflow-hidden"
             style="display:none">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
            <p class="text-sm font-semibold text-white">Notificações</p>
            <button x-show="count > 0" @click="markAllRead()" class="text-xs text-brand-400 hover:text-brand-300 transition-colors">
              Marcar todas como lidas
            </button>
          </div>
          <div class="max-h-72 overflow-y-auto">
            <template x-if="notifications.length === 0">
              <p class="px-4 py-6 text-center text-sm text-gray-400">Nenhuma notificação.</p>
            </template>
            <template x-for="n in notifications" :key="n.id">
              <a :href="n.action_url || '#'" @click="markRead(n.id)"
                 class="flex items-start gap-3 px-4 py-3 hover:bg-white/[0.04] transition-colors border-b border-white/[0.03] last:border-0">
                <div class="mt-0.5 w-2 h-2 rounded-full bg-brand-500 flex-shrink-0"></div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-white truncate" x-text="n.title"></p>
                  <p class="text-xs text-gray-400 mt-0.5 line-clamp-2" x-text="n.body"></p>
                </div>
              </a>
            </template>
          </div>
        </div>
      </div>

      <!-- User pill -->
      <div class="flex-shrink-0 flex items-center gap-2">
        <div class="hidden sm:flex h-8 w-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 items-center justify-center text-xs font-bold text-gray-950 shadow-lg shadow-brand-500/20">
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
    $flashErrors  = flash('errors');
    ?>
    <?php if ($flashSuccess || $flashError || $flashInfo || (is_array($flashErrors) && $flashErrors)): ?>
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
      <?php if (is_array($flashErrors) && $flashErrors): ?>
      <div x-data="{show:true}" x-show="show" x-transition
           class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <ul class="flex-1 list-disc list-inside space-y-1">
          <?php foreach ($flashErrors as $msg): ?>
          <li><?= e((string) $msg) ?></li>
          <?php endforeach; ?>
        </ul>
        <button @click="show=false" class="text-rose-400 hover:text-rose-200 transition-colors">
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
  return { mobileSidebarOpen: false }
}

function notifBell() {
  return {
    open: false,
    count: 0,
    notifications: [],
    pollInterval: null,

    init() {
      this.fetchCount();
      this.pollInterval = setInterval(() => this.fetchCount(), 30000);
    },

    toggle() {
      this.open = !this.open;
      if (this.open) this.fetchList();
    },

    async fetchCount() {
      try {
        const r = await fetch('/notifications/count');
        const d = await r.json();
        this.count = d.count || 0;
      } catch {}
    },

    async fetchList() {
      try {
        const r = await fetch('/notifications');
        const d = await r.json();
        this.notifications = d.notifications || [];
      } catch {}
    },

    async markRead(id) {
      try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        await fetch(`/notifications/${id}/read`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf }
        });
        this.count = Math.max(0, this.count - 1);
        this.notifications = this.notifications.filter(n => n.id !== id);
      } catch {}
    },

    async markAllRead() {
      try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        await fetch('/notifications/mark-all-read', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf }
        });
        this.count = 0;
        this.notifications = [];
      } catch {}
    },
  };
}
</script>
<?= view_partial('form_loading') ?>
</body>
</html>
