<?php
use App\Support\Auth;

$cp = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// function_exists: uma view pode ser renderizada mais de uma vez no mesmo
// processo (os testes de feature fazem vários requests em sequência). Sem esta
// guarda, o segundo render morre com "Cannot redeclare function navItem()".
// Em produção nunca apareceu — 1 request = 1 processo — mas é frágil.
if (!function_exists('navItem')):
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
endif;

$icons = [
    'dashboard'   => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    'organic'     => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    'clients'     => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    'content'     => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    'approvals'   => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'traffic'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'ia'          => 'M13 10V3L4 14h7v7l9-11h-7z',
    'tasks'       => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    'financial'   => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'users'       => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'settings'    => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'report'      => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'actions'     => 'M13 10V3L4 14h7v7l9-11h-7z',
    'automations' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
];
?>

<?php if (Auth::can('dashboard.view')): ?>
<?= navItem('/', $icons['dashboard'], t('nav.dashboard'), $cp) ?>
<?php endif; ?>

<?php if (Auth::canAny('clients.view', 'clients.view_all')): ?>
<?= navItem('/clients', $icons['clients'], t('nav.clients'), $cp) ?>
<?php endif; ?>

<?php if (Auth::can('content.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600"><?= t('nav.section.content') ?></p>
<?= navItem('/content', $icons['content'], t('nav.content_plans'), $cp) ?>
<?php endif; ?>

<?php if (Auth::can('approvals.view')): ?>
<?= navItem('/approvals', $icons['approvals'], t('nav.approvals'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('tasks.view')): ?>
<?= navItem('/tasks', $icons['tasks'], t('nav.tasks'), $cp) ?>
<?php endif; ?>

<?php if (Auth::canAny('ads_metrics.view', 'ai_insights.view', 'ads_actions.view', 'organic_metrics.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600"><?= t('nav.section.marketing') ?></p>
<?php if (Auth::can('ads_metrics.view')): ?>
<?= navItem('/traffic', $icons['traffic'], t('nav.traffic'), $cp) ?>
<?php if (Auth::can('ads_actions.view')): ?>
<?= navItem('/traffic/actions', $icons['actions'], t('nav.traffic_actions'), $cp) ?>
<?php endif; ?>
<?php endif; ?>
<?php if (Auth::can('ai_insights.view')): ?>
<?= navItem('/ai', $icons['ia'], t('nav.ai_insights'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('organic_metrics.view')): ?>
<?= navItem('/organic', $icons['organic'], t('nav.organic'), $cp) ?>
<?php endif; ?>
<?php endif; ?>

<?php if (Auth::canAny('invoices.view', 'contracts.view', 'payments.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600"><?= t('nav.section.financial') ?></p>
<?= navItem('/financial', $icons['financial'], t('nav.financial_overview'), $cp, '/financial') ?>
<?php if (Auth::can('contracts.view')): ?>
<?= navItem('/contracts', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', t('nav.contracts'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('invoices.view')): ?>
<?= navItem('/invoices', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', t('nav.invoices'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('payments.view')): ?>
<?= navItem('/payments', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', t('nav.payments'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('financial_reports.view')): ?>
<?= navItem('/financial/reports', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', t('nav.financial_reports'), $cp) ?>
<?php endif; ?>
<?php endif; ?>

<?php if (Auth::canAny('users.view', 'dashboard.view')): ?>
<div class="mt-1"></div>
<p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-gray-600"><?= t('nav.section.admin') ?></p>
<?php if (Auth::can('users.view')): ?>
<?= navItem('/users', $icons['users'], t('nav.users'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('automations.view')): ?>
<?= navItem('/automations', $icons['automations'], t('nav.automations'), $cp) ?>
<?php endif; ?>
<?php if (Auth::can('dashboard.view')): ?>
<?= navItem('/executive-report', $icons['report'], t('nav.executive_report'), $cp) ?>
<?php endif; ?>
<?php endif; ?>

<?= navItem('/subscription', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', t('nav.subscription'), $cp) ?>
<?php if (\App\Support\Auth::can('settings.manage')): ?>
<?= navItem('/integrations/clickup', 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', t('nav.clickup'), $cp) ?>
<?= navItem('/integrations/google-drive', 'M7.71 3.5L1.15 15l3.43 5.95 6.56-11.45zm8.58 0H9.42l6.56 11.45h6.87zm-1.66 12.7l-3.43 5.95h11.66a1 1 0 00.86-1.5l-2.6-4.45z', 'Google Drive', $cp) ?>
<?php endif; ?>
<?php if (Auth::can('settings.view')): ?>
<?= navItem('/settings', $icons['settings'], t('nav.settings'), $cp) ?>
<?php endif; ?>
