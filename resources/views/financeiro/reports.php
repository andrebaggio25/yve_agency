<?php view_layout('app'); view_start('content'); ?>

<?php
$months  = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$maxVal  = max(array_values($monthlyMap)) ?: 1;
function fmtBrl(mixed $v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
?>

<div class="min-h-screen space-y-8">

  <!-- Header -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-1">Financeiro</p>
      <h1 class="text-2xl font-bold text-white">Relatórios</h1>
    </div>
    <div class="flex gap-3 items-center">
      <a href="/financeiro" class="text-sm text-gray-400 hover:text-white transition-colors">← Visão Geral</a>
      <form method="GET">
        <select name="year" onchange="this.form.submit()"
          class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-3 py-1.5 focus:border-violet-500 focus:outline-none">
          <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
          <option value="<?= $y ?>" <?= $y === (int)$year ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
    <?php
    $kpis = [
      ['Recebido em ' . $year, fmtBrl($totals['received_year'] ?? 0), 'emerald'],
      ['A Receber',            fmtBrl($totals['pending_total'] ?? 0), 'amber'],
      ['Vencidas',             (int)($totals['overdue_count'] ?? 0) . ' fatura' . ((int)($totals['overdue_count']??0) !== 1 ? 's' : ''), 'red'],
    ];
    foreach ($kpis as [$label, $value, $color]):
    ?>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-5">
      <p class="text-xs text-gray-400 mb-1"><?= $label ?></p>
      <p class="text-xl font-bold text-white"><?= $value ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Gráfico mensal -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="font-semibold text-white mb-6">Recebimentos mensais — <?= e($year) ?></h2>
    <div class="flex items-end gap-2 h-40">
      <?php foreach ($monthlyMap as $m => $val): ?>
      <?php $pct = $maxVal > 0 ? ($val / $maxVal * 100) : 0; ?>
      <div class="flex-1 flex flex-col items-center gap-1">
        <div class="w-full rounded-t-lg bg-violet-500/20 hover:bg-violet-500/40 transition-all relative group"
             style="height: <?= max(4, round($pct * 1.2)) ?>px">
          <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
            <?= fmtBrl($val) ?>
          </div>
        </div>
        <span class="text-[10px] text-gray-500"><?= $months[$m - 1] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Receita por cliente -->
  <?php if (!empty($byClient)): ?>
  <div class="rounded-2xl border border-white/5 overflow-hidden">
    <div class="px-6 py-4 border-b border-white/5 bg-white/[0.02] flex items-center justify-between">
      <h2 class="font-semibold text-white">Receita por Cliente — <?= e($year) ?></h2>
      <?php if (\App\Support\Auth::can('financial_reports.export')): ?>
      <button onclick="exportTable('tbl-clients','clientes-<?= $year ?>.csv')"
        class="text-xs text-gray-400 hover:text-white transition-colors flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Exportar CSV
      </button>
      <?php endif; ?>
    </div>
    <table id="tbl-clients" class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5">
          <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cliente</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Faturado</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Recebido</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Faturas</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">% Recebido</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($byClient as $row):
          $pct = (float)$row['billed'] > 0 ? round((float)$row['received'] / (float)$row['billed'] * 100) : 0;
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-6 py-4 font-medium text-white"><?= e($row['client_name']) ?></td>
          <td class="px-6 py-4 text-right text-gray-300 font-mono"><?= fmtBrl($row['billed']) ?></td>
          <td class="px-6 py-4 text-right text-emerald-400 font-mono font-semibold"><?= fmtBrl($row['received']) ?></td>
          <td class="px-6 py-4 text-right text-gray-400"><?= (int)$row['invoice_count'] ?></td>
          <td class="px-6 py-4 text-right">
            <div class="flex items-center justify-end gap-2">
              <div class="w-16 h-1.5 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full <?= $pct >= 100 ? 'bg-emerald-500' : ($pct > 0 ? 'bg-amber-500' : 'bg-red-500') ?>"
                     style="width:<?= min(100, $pct) ?>%"></div>
              </div>
              <span class="text-xs text-gray-400 w-8 text-right"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Faturas vencidas -->
  <?php if (!empty($overdue)): ?>
  <div class="rounded-2xl border border-red-500/20 overflow-hidden">
    <div class="px-6 py-4 border-b border-red-500/20 bg-red-500/[0.04]">
      <h2 class="font-semibold text-white">Faturas Vencidas / Pendentes</h2>
      <p class="text-xs text-gray-400 mt-0.5"><?= count($overdue) ?> fatura<?= count($overdue) !== 1 ? 's' : '' ?> aguardando pagamento</p>
    </div>
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Fatura</th>
          <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cliente</th>
          <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Vencimento</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Total</th>
          <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Restante</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($overdue as $inv):
          $daysLate = (int) floor((time() - strtotime($inv['due_date'])) / 86400);
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-6 py-4">
            <a href="/faturas/<?= $inv['id'] ?>" class="font-medium text-white hover:text-violet-300 transition-colors"><?= e($inv['title']) ?></a>
            <p class="text-xs text-gray-500 font-mono"><?= e($inv['invoice_number']) ?></p>
          </td>
          <td class="px-6 py-4 text-gray-400"><?= e($inv['client_name']) ?></td>
          <td class="px-6 py-4 text-center">
            <span class="text-red-400 font-semibold"><?= date('d/m/Y', strtotime($inv['due_date'])) ?></span>
            <p class="text-xs text-red-500 mt-0.5"><?= $daysLate ?> dia<?= $daysLate !== 1 ? 's' : '' ?> em atraso</p>
          </td>
          <td class="px-6 py-4 text-right font-mono text-gray-300">R$ <?= number_format((float)$inv['total'],2,',','.') ?></td>
          <td class="px-6 py-4 text-right font-mono font-semibold text-red-400">R$ <?= number_format((float)$inv['remaining'],2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function exportTable(tableId, filename) {
  const rows = document.querySelectorAll('#' + tableId + ' tr');
  const csv  = Array.from(rows).map(r =>
    Array.from(r.querySelectorAll('th,td')).map(c => '"' + c.innerText.replace(/"/g,'""') + '"').join(',')
  ).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent('﻿' + csv);
  a.download = filename;
  a.click();
}
</script>

<?php view_end(); ?>
