<?php view_layout('app'); view_start('content');
$statusLabels = [
    'draft'     => ['Rascunho',   'bg-gray-500/15 text-gray-400 ring-gray-500/30'],
    'active'    => ['Ativo',      'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'],
    'expired'   => ['Expirado',   'bg-amber-500/15 text-amber-300 ring-amber-500/30'],
    'cancelled' => ['Cancelado',  'bg-red-500/15 text-red-400 ring-red-500/30'],
];
[$slabel, $scls] = $statusLabels[$contract['status']] ?? ['—', ''];
?>

<div class="max-w-3xl mx-auto space-y-6">
  <div class="flex items-start justify-between">
    <div>
      <a href="/contratos" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Contratos</a>
      <h1 class="text-2xl font-bold text-white mt-2"><?= e($contract['title']) ?></h1>
      <div class="mt-2 flex items-center gap-3">
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 <?= $scls ?>"><?= $slabel ?></span>
        <span class="text-sm text-gray-400"><?= e($contract['client_name']) ?></span>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="/contratos/<?= $contract['id'] ?>/pdf" target="_blank"
         class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        PDF
      </a>
      <?php if (\App\Support\Auth::can('contracts.edit')): ?>
      <a href="/contratos/<?= $contract['id'] ?>/editar"
         class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Editar
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 grid sm:grid-cols-2 gap-6">
    <?php
    $rows = [
        'Valor'      => 'R$ ' . number_format((float)$contract['value'], 2, ',', '.'),
        'Moeda'      => $contract['currency_code'],
        'Início'     => $contract['start_date'] ? date('d/m/Y', strtotime($contract['start_date'])) : '—',
        'Fim'        => $contract['end_date']   ? date('d/m/Y', strtotime($contract['end_date']))   : '—',
        'Assinado'   => $contract['signed_at']  ? date('d/m/Y', strtotime($contract['signed_at']))  : '—',
        'Recorrente' => $contract['recurring'] ? 'Sim — ' . ($contract['recurrence'] ?? '—') : 'Não',
    ];
    foreach ($rows as $k => $val): ?>
    <div>
      <p class="text-xs text-gray-500 mb-0.5"><?= $k ?></p>
      <p class="text-sm font-medium text-white"><?= e($val) ?></p>
    </div>
    <?php endforeach; ?>

    <?php if ($contract['description']): ?>
    <div class="sm:col-span-2">
      <p class="text-xs text-gray-500 mb-0.5">Descrição</p>
      <p class="text-sm text-gray-300 whitespace-pre-line"><?= e($contract['description']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($contract['notes']): ?>
    <div class="sm:col-span-2">
      <p class="text-xs text-gray-500 mb-0.5">Notas internas</p>
      <p class="text-sm text-gray-300 whitespace-pre-line"><?= e($contract['notes']) ?></p>
    </div>
    <?php endif; ?>
  </div>

  <?php if (\App\Support\Auth::can('invoices.create')): ?>
  <div class="flex justify-end">
    <a href="/faturas/nova?client_id=<?= $contract['client_id'] ?>&contract_id=<?= $contract['id'] ?>"
       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Gerar Fatura
    </a>
  </div>
  <?php endif; ?>

  <?php if (\App\Support\Auth::can('contracts.delete')): ?>
  <form method="POST" action="/contratos/<?= $contract['id'] ?>" class="flex justify-end" onsubmit="return confirm('Remover este contrato permanentemente?')">
    <input type="hidden" name="_method" value="DELETE">
    <?= csrf_field() ?>
    <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors">Remover contrato</button>
  </form>
  <?php endif; ?>
</div>

<?php view_end(); ?>
