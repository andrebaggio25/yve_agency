<?php
use App\Support\Auth;

$user = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function nav_active(string $prefix, string $current): string {
    return str_starts_with($current, $prefix)
        ? 'bg-indigo-700 text-white'
        : 'text-indigo-100 hover:bg-indigo-700 hover:text-white';
}
?>
<nav class="w-64 bg-indigo-800 flex flex-col">
    <div class="flex h-16 items-center px-4">
        <span class="text-white font-bold text-lg"><?= e(env('APP_NAME', 'YVE Agency')) ?></span>
    </div>

    <div class="flex-1 overflow-y-auto py-4 px-2 space-y-1">

        <?php if (Auth::can('dashboard.view')): ?>
        <a href="/dashboard"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/dashboard', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
        <?php endif; ?>

        <?php if (Auth::canAny('clients.view', 'clients.view_all')): ?>
        <a href="/clientes"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/clientes', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Clientes
        </a>
        <?php endif; ?>

        <?php if (Auth::can('content.view')): ?>
        <a href="/planificacoes"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/planificacoes', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Planificações
        </a>
        <?php endif; ?>

        <?php if (Auth::can('ads_metrics.view')): ?>
        <a href="/trafego"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/trafego', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Tráfego Pago
        </a>
        <?php endif; ?>

        <?php if (Auth::can('ai_insights.view')): ?>
        <a href="/ia"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/ia', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            IA Insights
        </a>
        <?php endif; ?>

        <?php if (Auth::canAny('invoices.view', 'contracts.view')): ?>
        <a href="/financeiro"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/financeiro', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Financeiro
        </a>
        <?php endif; ?>

        <?php if (Auth::can('users.view')): ?>
        <a href="/usuarios"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/usuarios', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            Usuários
        </a>
        <?php endif; ?>

        <?php if (Auth::can('settings.view')): ?>
        <a href="/configuracoes"
           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium <?= nav_active('/configuracoes', $currentPath) ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Configurações
        </a>
        <?php endif; ?>

    </div>
</nav>
