<?php view_layout('portal'); view_start('title'); ?><?= t('portal.nav.home') ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$firstName   = explode(' ', $client['name'])[0];
$hour        = (int) date('H');
$greeting    = $hour < 12 ? t('portal.home.greeting_morning') : ($hour < 18 ? t('portal.home.greeting_afternoon') : t('portal.home.greeting_evening'));
$hasPending  = $stats['plans_pending'] > 0;
$hasOverdue  = count(array_filter($invoices, fn($i) => $i['status'] === 'overdue')) > 0;
$recentPlans = array_slice($plans, 0, 4);
$openInvoices = array_filter($invoices, fn($i) => in_array($i['status'], ['sent', 'overdue']));
// 'sent' e 'revision' são os status que o sistema grava; 'pending_approval'
// e 'in_revision' ficam por compatibilidade de leitura.
$statusLabels = ['draft' => t('portal.pstatus.draft'), 'sent' => t('portal.pstatus.sent'), 'pending_approval' => t('portal.pstatus.pending_approval'), 'approved' => t('portal.pstatus.approved'), 'revision' => t('portal.pstatus.revision'), 'in_revision' => t('portal.pstatus.in_revision'), 'published' => t('portal.pstatus.published')];
$statusColors = [
  'draft'            => 'text-gray-400 bg-gray-500/10',
  'sent'             => 'text-amber-300 bg-amber-500/10',
  'pending_approval' => 'text-amber-300 bg-amber-500/10',
  'approved'         => 'text-green-300 bg-green-500/10',
  'revision'         => 'text-blue-300 bg-blue-500/10',
  'in_revision'      => 'text-blue-300 bg-blue-500/10',
  'published'        => 'text-brand-300 bg-brand-500/10',
];
?>

<!-- Hero -->
<div class="relative overflow-hidden rounded-2xl mb-6 p-6 sm:p-8"
     style="background: linear-gradient(135deg, rgba(198,161,91,0.25) 0%, rgba(59,130,246,0.15) 50%, rgba(16,185,129,0.1) 100%); border: 1px solid rgba(198,161,91,0.2);">
  <div class="absolute inset-0 pointer-events-none">
    <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full opacity-10" style="background:radial-gradient(circle, #c6a15b, transparent)"></div>
    <div class="absolute -bottom-8 -left-8 w-32 h-32 rounded-full opacity-10" style="background:radial-gradient(circle, #3b82f6, transparent)"></div>
  </div>
  <div class="relative flex items-start justify-between gap-4 flex-wrap">
    <div>
      <p class="text-sm text-brand-300/80 mb-1"><?= $greeting ?>,</p>
      <h1 class="text-2xl sm:text-3xl font-bold text-white"><?= e($firstName) ?> 👋</h1>
      <p class="text-sm text-gray-400 mt-1.5"><?= t('portal.home.summary') ?></p>
    </div>
    <?php if ($hasPending): ?>
    <a href="/portal/<?= $token ?>/planos"
       class="flex-shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-all"
       style="background:linear-gradient(135deg,#c6a15b,#4f46e5)">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      <?= t($stats['plans_pending'] === 1 ? 'portal.home.plans_waiting' : 'portal.home.plans_waiting_plural', ['n' => $stats['plans_pending']]) ?>
    </a>
    <?php endif; ?>
  </div>

  <!-- KPIs inline no hero -->
  <div class="relative grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
    <?php
    $kpis = [
      ['label' => t('portal.home.kpi_pending'),       'value' => $stats['plans_pending'],  'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'border' => 'rgba(245,158,11,0.2)', 'href' => "/portal/{$token}/planos"],
      ['label' => t('portal.home.kpi_approved'),      'value' => $stats['plans_approved'], 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',  'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'border' => 'rgba(16,185,129,0.2)', 'href' => "/portal/{$token}/planos"],
      ['label' => t('portal.home.kpi_invoices_open'), 'value' => $stats['invoices_open'],  'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)', 'border' => 'rgba(239,68,68,0.2)', 'href' => "/portal/{$token}/faturas"],
      ['label' => t('portal.home.kpi_invoices_paid'), 'value' => $stats['invoices_paid'],  'icon' => 'M5 13l4 4L19 7', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'border' => 'rgba(59,130,246,0.2)', 'href' => "/portal/{$token}/faturas"],
    ];
    foreach ($kpis as $k): ?>
    <a href="<?= $k['href'] ?>" class="rounded-xl p-3.5 transition-all hover:scale-[1.02]"
       style="background:<?= $k['bg'] ?>; border:1px solid <?= $k['border'] ?>">
      <div class="flex items-center gap-2 mb-2">
        <svg class="w-4 h-4" style="color:<?= $k['color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $k['icon'] ?>"/>
        </svg>
        <p class="text-[11px] text-gray-400 leading-tight"><?= $k['label'] ?></p>
      </div>
      <p class="text-2xl font-bold text-white"><?= $k['value'] ?></p>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Sua semana: os posts planejados de segunda a domingo -->
<?php
$weekDays = [];
$cursor = strtotime($weekMonday);
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = date('Y-m-d', $cursor);
    $cursor = strtotime('+1 day', $cursor);
}
$itemsByDay = [];
foreach ($weekItems as $wi) {
    $itemsByDay[$wi['publish_date']][] = $wi;
}
?>
<div class="mb-6">
  <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
    <h2 class="text-sm font-semibold text-gray-300">
      <?= t('portal.home.your_week') ?>
      <span class="font-normal text-gray-400">· <?= t('portal.plan.week_of', ['from' => date('d/m', strtotime($weekMonday)), 'to' => date('d/m', strtotime($weekSunday))]) ?></span>
    </h2>
    <a href="/portal/<?= $token ?>/planos/calendario" class="text-xs text-brand-300 hover:text-brand-200 transition-colors">
      <?= t('portal.calendar.view') ?> →
    </a>
  </div>

  <?php if (empty($weekItems)): ?>
  <div class="card-solid p-4 text-center text-sm text-gray-400"><?= t('portal.home.week_empty') ?></div>
  <?php else: ?>
  <div class="overflow-x-auto pb-1">
    <div class="grid grid-cols-7 gap-1.5 min-w-[42rem]">
      <?php foreach ($weekDays as $day):
        $dayItems = $itemsByDay[$day] ?? [];
        $isToday  = $day === date('Y-m-d');
      ?>
      <div class="rounded-xl border p-2 min-h-[5.5rem] <?= $isToday ? 'border-brand-500/40 bg-brand-500/[0.05]' : 'border-white/[0.07] bg-white/[0.02]' ?>">
        <p class="text-[10px] uppercase tracking-wide mb-1.5 <?= $isToday ? 'text-brand-300' : 'text-gray-400' ?>">
          <?= e(t('portal.dow.' . (int) date('N', strtotime($day)))) ?> <?= date('d/m', strtotime($day)) ?>
        </p>
        <?php if (empty($dayItems)): ?>
        <p class="text-[11px] text-gray-500">—</p>
        <?php else: foreach ($dayItems as $wi): ?>
        <a href="/portal/<?= $token ?>/planos/<?= (int) $wi['content_plan_id'] ?>#item-<?= (int) $wi['id'] ?>"
           class="block mb-1 rounded-lg border border-white/[0.07] bg-white/[0.04] px-1.5 py-1 hover:border-brand-500/40 transition-all">
          <span class="block text-[11px] text-gray-200 truncate"><?= e($wi['title'] ?: ($wi['content_type'] ?: 'Post')) ?></span>
          <?php if (!empty($wi['publish_time'])): ?>
          <span class="block text-[10px] text-gray-400"><?= substr($wi['publish_time'], 0, 5) ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Ação urgente: planos pendentes -->
<?php $pending = array_filter($plans, fn($p) => in_array($p['status'], ['sent', 'pending_approval'], true)); ?>
<?php if (!empty($pending)): ?>
<div class="mb-6">
  <div class="flex items-center gap-2 mb-3">
    <span class="flex h-2 w-2 relative">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background:#f59e0b"></span>
      <span class="relative inline-flex rounded-full h-2 w-2" style="background:#f59e0b"></span>
    </span>
    <h2 class="text-sm font-semibold text-amber-300"><?= t('portal.home.awaiting_approval') ?></h2>
  </div>
  <div class="space-y-2">
    <?php foreach ($pending as $p): ?>
    <a href="/portal/<?= $token ?>/planos/<?= $p['id'] ?>"
       class="flex items-center justify-between rounded-xl p-4 transition-all hover:scale-[1.01]"
       style="background:rgba(245,158,11,0.06); border:1px solid rgba(245,158,11,0.18)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
             style="background:rgba(245,158,11,0.12)">
          <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
          </svg>
        </div>
        <div>
          <p class="text-sm font-medium text-white"><?= e($p['title']) ?></p>
          <?php if ($p['period_label'] ?? null): ?>
          <p class="text-xs text-gray-400"><?= e($p['period_label']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-300">
        <?= t('portal.home.review') ?>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Grid principal -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Coluna esquerda: planos recentes -->
  <div class="lg:col-span-2">
    <?php if (!empty($recentPlans)): ?>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-sm font-semibold text-gray-300"><?= t('portal.home.content_plans') ?></h2>
      <a href="/portal/<?= $token ?>/planos" class="text-xs text-brand-400 hover:text-brand-300"><?= t('portal.home.see_all') ?></a>
    </div>
    <div class="space-y-2 mb-6">
      <?php foreach ($recentPlans as $p):
        $sc = $statusColors[$p['status']] ?? 'text-gray-400 bg-gray-500/10';
        $sl = $statusLabels[$p['status']] ?? $p['status'];
        $total    = (int)($p['total_items']    ?? 0);
        $approved = (int)($p['approved_items'] ?? 0);
        $pct      = $total > 0 ? round($approved / $total * 100) : 0;
      ?>
      <a href="/portal/<?= $token ?>/planos/<?= $p['id'] ?>"
         class="flex items-center gap-4 rounded-xl p-4 transition-all hover:bg-white/[0.04]"
         style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06)">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background:rgba(198,161,91,0.12); border:1px solid rgba(198,161,91,0.15)">
          <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <p class="text-sm font-medium text-white truncate"><?= e($p['title']) ?></p>
            <span class="flex-shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full <?= $sc ?>"><?= $sl ?></span>
          </div>
          <?php if ($total > 0): ?>
          <div class="flex items-center gap-2">
            <div class="flex-1 h-1.5 rounded-full bg-white/[0.06] overflow-hidden">
              <div class="h-full rounded-full transition-all"
                   style="width:<?= $pct ?>%; background:linear-gradient(90deg,#c6a15b,#3b82f6)"></div>
            </div>
            <span class="text-[10px] text-gray-400 flex-shrink-0"><?= $approved ?>/<?= $total ?> <?= t('portal.home.approved_count') ?></span>
          </div>
          <?php elseif ($p['period_label'] ?? null): ?>
          <p class="text-xs text-gray-400"><?= e($p['period_label']) ?></p>
          <?php endif; ?>
        </div>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Métricas de tráfego pago -->
    <?php if (!empty($adsSummary)): ?>
    <div class="rounded-2xl p-5 mb-4" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06)">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:rgba(59,130,246,0.12)">
            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <h3 class="text-sm font-semibold text-white"><?= t('portal.home.paid_traffic') ?></h3>
        </div>
        <span class="text-[10px] text-gray-400"><?= date('d/m', strtotime($since)) ?> – <?= date('d/m', strtotime($until)) ?></span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php
        $adsKpis = [
          ['label' => t('portal.home.ads_impressions'), 'value' => number_format((float)($adsSummary['impressions']??0),0,',','.'), 'color'=>'#c6a15b'],
          ['label' => t('portal.home.ads_clicks'),      'value' => number_format((float)($adsSummary['clicks']??0),     0,',','.'), 'color'=>'#3b82f6'],
          ['label' => t('portal.home.ads_spend'),       'value' => 'R$ '.number_format((float)($adsSummary['spend']??0),2,',','.'), 'color'=>'#f59e0b'],
          ['label' => t('portal.home.ads_results'),     'value' => number_format((float)($adsSummary['conversions']??$adsSummary['results']??0),0,',','.'), 'color'=>'#10b981'],
        ];
        foreach ($adsKpis as $k): ?>
        <div class="rounded-xl p-3" style="background:rgba(255,255,255,0.03)">
          <p class="text-[10px] text-gray-400 mb-1"><?= $k['label'] ?></p>
          <p class="text-lg font-bold" style="color:<?= $k['color'] ?>"><?= $k['value'] ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Métricas orgânicas -->
    <?php if (!empty($organicSummary)): ?>
    <div class="rounded-2xl p-5" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06)">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:rgba(16,185,129,0.12)">
            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
          </div>
          <h3 class="text-sm font-semibold text-white"><?= t('portal.home.social') ?></h3>
        </div>
        <span class="text-[10px] text-gray-400"><?= date('d/m', strtotime($since)) ?> – <?= date('d/m', strtotime($until)) ?></span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php
        $orgKpis = [
          ['label' => t('portal.home.org_reach'),       'value' => number_format((float)($organicSummary['reach']??0),        0,',','.'), 'color'=>'#10b981'],
          ['label' => t('portal.home.org_impressions'), 'value' => number_format((float)($organicSummary['impressions']??0),  0,',','.'), 'color'=>'#c6a15b'],
          ['label' => t('portal.home.org_engagement'),  'value' => number_format((float)($organicSummary['engagement']??0),   0,',','.'), 'color'=>'#3b82f6'],
          ['label' => t('portal.home.org_followers'),   'value' => '+'.number_format((float)($organicSummary['followers_gained']??0),0,',','.'), 'color'=>'#ec4899'],
        ];
        foreach ($orgKpis as $k): ?>
        <div class="rounded-xl p-3" style="background:rgba(255,255,255,0.03)">
          <p class="text-[10px] text-gray-400 mb-1"><?= $k['label'] ?></p>
          <p class="text-lg font-bold" style="color:<?= $k['color'] ?>"><?= $k['value'] ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Coluna direita: atalhos + faturas -->
  <div class="space-y-4">

    <!-- Atalhos de navegação -->
    <div class="rounded-2xl p-4" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06)">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3"><?= t('portal.home.quick_access') ?></h3>
      <div class="space-y-1.5">
        <?php
        $navLinks = [
          ['href' => "/portal/{$token}/planos",    'label' => t('portal.home.content_plans'), 'icon' => 'M4 6h16M4 10h16M4 14h10',                                                   'color' => '#c6a15b'],
          ['href' => "/portal/{$token}/faturas",   'label' => t('portal.nav.invoices'),       'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2', 'color' => '#f59e0b'],
          ['href' => "/portal/{$token}/contratos", 'label' => t('portal.nav.contracts'),      'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'color' => '#3b82f6'],
        ];
        foreach ($navLinks as $l): ?>
        <a href="<?= $l['href'] ?>"
           class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all hover:bg-white/[0.04] group">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 transition-all"
               style="background:<?= $l['color'] ?>18">
            <svg class="w-3.5 h-3.5" style="color:<?= $l['color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $l['icon'] ?>"/>
            </svg>
          </div>
          <span class="text-sm text-gray-300 group-hover:text-white transition-colors"><?= $l['label'] ?></span>
          <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-400 ml-auto transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Faturas em aberto -->
    <?php if (!empty($openInvoices)): ?>
    <div class="rounded-2xl p-4" style="background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.15)">
      <div class="flex items-center gap-2 mb-3">
        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h3 class="text-sm font-semibold text-red-300"><?= t('portal.home.open_invoices') ?></h3>
      </div>
      <div class="space-y-2">
        <?php foreach (array_slice($openInvoices, 0, 3) as $inv): ?>
        <div class="flex items-center justify-between text-sm">
          <div>
            <p class="text-white font-medium"><?= e($inv['invoice_number'] ?? "#$inv[id]") ?></p>
            <p class="text-[11px] text-gray-400"><?= t('portal.home.due') ?> <?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></p>
          </div>
          <span class="font-semibold <?= $inv['status'] === 'overdue' ? 'text-red-400' : 'text-white' ?>">
            R$ <?= number_format((float)$inv['total'], 2, ',', '.') ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="/portal/<?= $token ?>/faturas" class="block mt-3 text-center text-xs text-red-400 hover:text-red-300 transition-colors">
        <?= t('portal.home.see_all_invoices') ?>
      </a>
    </div>
    <?php endif; ?>

    <!-- Mensagem vazia amigável -->
    <?php if (empty($plans) && empty($openInvoices)): ?>
    <div class="rounded-2xl p-6 text-center" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06)">
      <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background:rgba(198,161,91,0.1)">
        <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
        </svg>
      </div>
      <p class="text-sm text-gray-400"><?= t('portal.home.all_clear') ?></p>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php view_end(); ?>
