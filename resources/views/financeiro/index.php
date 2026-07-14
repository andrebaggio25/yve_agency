<?php view_layout('app'); view_start('content'); ?>

<?php
$cs  = $contractSummary;
$is  = $invoiceSummary;
function fmtBrl(mixed $v): string {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
$months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$monthly = array_fill(1, 12, 0);
foreach ($monthlyPayments as $row) {
    $m = (int) substr($row['month'], 5, 2);
    $monthly[$m] = (float) $row['total'];
}
$maxVal = max(array_values($monthly)) ?: 1;
?>

<div class="min-h-screen space-y-8">

  <!-- Header -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Módulo</p>
      <h1 class="text-2xl font-bold text-white">Financeiro</h1>
      <p class="mt-1 text-sm text-gray-400">Visão geral de contratos, faturas e recebimentos</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <?php if (\App\Support\Auth::can('contracts.create')): ?>
      <a href="/contratos/novo" class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Novo Contrato
      </a>
      <?php endif; ?>
      <?php if (\App\Support\Auth::can('invoices.create')): ?>
      <a href="/faturas/nova" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nova Fatura
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
    <?php
    $kpis = [
      ['label'=>'Faturas Enviadas',  'value'=>fmtBrl($is['billed_total'] ?? 0),   'sub'=>($is['total']??0).' faturas',    'color'=>'brand'],
      ['label'=>'Recebido',          'value'=>fmtBrl($is['received_total'] ?? 0),  'sub'=>($is['paid']??0).' pagas',       'color'=>'emerald'],
      ['label'=>'A Receber',         'value'=>fmtBrl($is['pending_total'] ?? 0),   'sub'=>($is['sent']??0)+($is['overdue']??0).' pendentes', 'color'=>'amber'],
      ['label'=>'Contratos Ativos',  'value'=>$cs['active']??0,                    'sub'=>fmtBrl($cs['active_value']??0),  'color'=>'blue'],
    ];
    $colorMap = ['brand'=>'brand-500','emerald'=>'emerald-500','amber'=>'amber-500','blue'=>'blue-500'];
    foreach ($kpis as $k):
        $c = $colorMap[$k['color']];
    ?>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-5">
      <p class="text-xs text-gray-400 mb-1"><?= e($k['label']) ?></p>
      <p class="text-2xl font-bold text-white"><?= e($k['value']) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= e($k['sub']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Overdue alert -->
  <?php if (($is['overdue'] ?? 0) > 0): ?>
  <div class="flex items-center gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4">
    <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    <p class="text-sm text-red-300">
      <span class="font-semibold"><?= (int)($is['overdue'] ?? 0) ?> <?= (int)($is['overdue'] ?? 0) === 1 ? 'fatura vencida' : 'faturas vencidas' ?></span>
      — <a href="/faturas?status=overdue" class="underline hover:text-red-200">Ver faturas</a>
    </p>
  </div>
  <?php endif; ?>

  <!-- Gráfico mensal -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="font-semibold text-white">Recebimentos <?= e($year) ?></h2>
        <p class="text-xs text-gray-400 mt-0.5">Pagamentos registrados por mês</p>
      </div>
      <form method="GET">
        <select aria-label="Ano" name="year" onchange="this.form.submit()"
          class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-3 py-1.5 focus:border-brand-500 focus:outline-none">
          <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
          <option value="<?= $y ?>" <?= $y === (int)$year ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
    <div class="flex items-end gap-2 h-40">
      <?php foreach ($monthly as $m => $val): ?>
      <?php $pct = $maxVal > 0 ? ($val / $maxVal * 100) : 0; ?>
      <div class="flex-1 flex flex-col items-center gap-1">
        <div class="w-full rounded-t-lg bg-brand-500/20 hover:bg-brand-500/40 transition-all relative group"
             style="height: <?= max(4, round($pct * 1.2)) ?>px">
          <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
            <?= fmtBrl($val) ?>
          </div>
        </div>
        <span class="text-[10px] text-gray-400"><?= $months[$m - 1] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Shortcuts -->
  <div class="grid sm:grid-cols-3 gap-4">
    <?php
    $shortcuts = [
      ['href'=>'/contratos', 'label'=>'Contratos', 'total'=>$cs['total']??0, 'icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
      ['href'=>'/faturas',   'label'=>'Faturas',   'total'=>$is['total']??0, 'icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
      ['href'=>'/pagamentos','label'=>'Pagamentos','total'=>null,            'icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ];
    foreach ($shortcuts as $s):
    ?>
    <a href="<?= $s['href'] ?>" class="group flex items-center gap-4 rounded-2xl border border-white/5 bg-white/[0.03] p-5 hover:border-brand-500/30 hover:bg-white/[0.06] transition-all">
      <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-500/10">
        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="<?= $s['icon'] ?>"/>
        </svg>
      </div>
      <div>
        <p class="font-semibold text-white group-hover:text-brand-200 transition-colors"><?= $s['label'] ?></p>
        <?php if ($s['total'] !== null): ?>
        <p class="text-xs text-gray-400 mt-0.5"><?= (int)$s['total'] ?> registros</p>
        <?php endif; ?>
      </div>
      <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endforeach; ?>
  </div>

</div>

<?php view_end(); ?>
