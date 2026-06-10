<?php
use App\Support\Auth;

$cp = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function navItem(string $href, string $icon, string $label, string $current, string $prefix = ''): string
{
    $target  = $prefix ?: $href;
    $active  = $href === '/' ? $current === '/' || $current === '/dashboard' : str_starts_with($current, $target);
    $baseCls = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150 ';
    $cls     = $active
        ? $baseCls . 'bg-violet-500/15 text-violet-200 nav-active'
        : $baseCls . 'text-gray-400 hover:text-white hover:bg-white/[0.06]';

    return <<<HTML
    <a href="{$href}" class="{$cls}">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{$icon}"/>
      </svg>
      {$label}
    </a>
    HTML;
}

$icons = [
    'dashboard'   => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    'clients'     => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    'content'     => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    'approvals'   => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'traffic'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'ia'          => 'M13 10V3L4 14h7v7l9-11h-7z',
    'financial'   => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'users'       => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'settings'    => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
];
?>

<?php if (Auth::can('dashboard.view')): ?>
<?= navItem('/', $icons['dashboard'], 'Dashboard', $cp) ?>
<?php endif; ?>

<?php if (Auth::canAny('clients.view', 'clients.view_all')): ?>
<?= navItem('/clientes', $icons['clients'], 'Clientes', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('content.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600">Conteúdo</p>
<?= navItem('/conteudo', $icons['content'], 'Planos', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('approvals.view')): ?>
<?= navItem('/aprovacoes', $icons['approvals'], 'Aprovações', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('ads_metrics.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600">Marketing</p>
<?= navItem('/trafego', $icons['traffic'], 'Tráfego Pago', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('ai_insights.view')): ?>
<?= navItem('/ia', $icons['ia'], 'IA Insights', $cp) ?>
<?php endif; ?>

<?php if (Auth::canAny('invoices.view', 'contracts.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600">Financeiro</p>
<?= navItem('/financeiro', $icons['financial'], 'Financeiro', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('users.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600">Admin</p>
<?= navItem('/usuarios', $icons['users'], 'Usuários', $cp) ?>
<?php endif; ?>

<?php if (Auth::can('settings.view')): ?>
<?= navItem('/configuracoes', $icons['settings'], 'Configurações', $cp) ?>
<?php endif; ?>
