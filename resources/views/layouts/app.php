<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(view_slot('title', 'YVE Agency')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>

    <!-- DNS prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- defer: Chart.js only executes after HTML is parsed, unblocking first render -->
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
      /* Esconde elementos com x-cloak até o Alpine inicializar (evita flash) */
      [x-cloak] { display: none !important; }
      /* Dark base */
      * { -webkit-font-smoothing: antialiased; }
      html { background: #09090f; }
      body { background: #09090f; color: #e5e7eb; }

      /* Selects: o <option> herda o texto branco do <select>, mas o fundo do
         dropdown nativo é branco. Sem isto, a lista fica branco no branco. */
      select { color-scheme: dark; }
      select option, select optgroup { background-color: #12121a; color: #e5e7eb; }

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

      /* ── Shared UI components ───────────────────────────────────────────── */

      /* Card */
      .card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 1rem;
      }

      /* Form inputs */
      .input-field {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.10);
        border-radius: 0.75rem;
        color: #f3f4f6;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        transition: border-color 0.15s, box-shadow 0.15s;
        outline: none;
        width: 100%;
      }
      .input-field::placeholder { color: #6b7280; }
      .input-field:focus {
        border-color: rgba(139,92,246,0.6);
        box-shadow: 0 0 0 3px rgba(139,92,246,0.12);
      }
      .input-field:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      select.input-field { appearance: auto; background-color: #0d0d14; }
      textarea.input-field { resize: vertical; }

      /* Labels */
      .label-field {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #d1d5db;
        margin-bottom: 0.375rem;
      }

      /* Buttons */
      .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: #7c3aed;
        color: #fff;
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.625rem 1.25rem;
        border-radius: 0.75rem;
        border: none;
        cursor: pointer;
        transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
        box-shadow: 0 4px 14px rgba(124,58,237,0.25);
        text-decoration: none;
      }
      .btn-primary:hover { background: #6d28d9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,58,237,0.35); }
      .btn-primary:active { transform: translateY(0); }

      .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: rgba(255,255,255,0.05);
        color: #d1d5db;
        font-size: 0.875rem;
        font-weight: 500;
        padding: 0.625rem 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(255,255,255,0.10);
        cursor: pointer;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        text-decoration: none;
      }
      .btn-secondary:hover { background: rgba(255,255,255,0.09); color: #f9fafb; border-color: rgba(255,255,255,0.18); }

      /* Badge/pill */
      .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.125rem 0.625rem;
      }
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

      <!-- Notifications bell -->
      <div class="flex-shrink-0 relative" x-data="notifBell()" x-init="init()">
        <button @click="toggle()" class="relative flex items-center justify-center w-9 h-9 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span x-show="count > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 flex items-center justify-center rounded-full bg-violet-500 text-[10px] font-bold text-white px-1"
                x-text="count > 9 ? '9+' : count"></span>
        </button>
        <!-- Dropdown -->
        <div x-show="open" x-transition @click.outside="open = false"
             class="absolute right-0 top-12 w-80 rounded-2xl border border-white/10 bg-[#0d0d14] shadow-2xl shadow-black/40 z-50 overflow-hidden"
             style="display:none">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
            <p class="text-sm font-semibold text-white">Notificações</p>
            <button x-show="count > 0" @click="markAllRead()" class="text-xs text-violet-400 hover:text-violet-300 transition-colors">
              Marcar todas como lidas
            </button>
          </div>
          <div class="max-h-72 overflow-y-auto">
            <template x-if="notifications.length === 0">
              <p class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma notificação.</p>
            </template>
            <template x-for="n in notifications" :key="n.id">
              <a :href="n.action_url || '#'" @click="markRead(n.id)"
                 class="flex items-start gap-3 px-4 py-3 hover:bg-white/[0.04] transition-colors border-b border-white/[0.03] last:border-0">
                <div class="mt-0.5 w-2 h-2 rounded-full bg-violet-500 flex-shrink-0"></div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-white truncate" x-text="n.title"></p>
                  <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="n.body"></p>
                </div>
              </a>
            </template>
          </div>
        </div>
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
