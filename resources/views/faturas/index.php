<?php view_layout('app'); view_start('content'); ?>

<?php
$invoices = $paginated['items'];
$statusLabels = [
    'draft'     => ['Rascunho', 'bg-gray-500/15 text-gray-400 ring-gray-500/30'],
    'sent'      => ['Enviada',  'bg-blue-500/15 text-blue-300 ring-blue-500/30'],
    'paid'      => ['Paga',     'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'],
    'overdue'   => ['Vencida',  'bg-red-500/15 text-red-400 ring-red-500/30'],
    'partial'   => ['Parcial',  'bg-amber-500/15 text-amber-300 ring-amber-500/30'],
    'cancelled' => ['Cancelada','bg-gray-500/15 text-gray-400 ring-gray-500/30'],
];
?>

<div class="min-h-screen">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Financeiro</p>
      <h1 class="text-2xl font-bold text-white">Faturas</h1>
      <p class="mt-1 text-sm text-gray-400"><?= $paginated['total'] ?> fatura<?= $paginated['total'] !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('invoices.create')): ?>
    <a href="/faturas/nova" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 hover:bg-brand-500 hover:scale-105 active:scale-95 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Nova Fatura
    </a>
    <?php endif; ?>
  </div>

  <!-- Filtros -->
  <?php
    $qFilter = $filters['q'] ?? '';
    $clientFilter = $filters['client_id'] ?? '';
    $statusFilter = $filters['status'] ?? '';
    $hasFilter = $qFilter || $clientFilter || $statusFilter;
    $filterBase = '?' . http_build_query(array_filter(['q' => $qFilter, 'client_id' => $clientFilter, 'status' => $statusFilter]));
  ?>
  <form method="GET" class="mb-6 flex flex-wrap gap-3 items-end">
    <input aria-label="Buscar" type="text" name="q" value="<?= e($qFilter) ?>" placeholder="Buscar..."
      class="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none w-52">
    <select aria-label="Cliente" name="client_id" class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-4 py-2.5 focus:border-brand-500 focus:outline-none">
      <option value="">Todos os clientes</option>
      <?php foreach ($clients as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= $clientFilter == $cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select aria-label="Situação" name="status" class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-4 py-2.5 focus:border-brand-500 focus:outline-none">
      <option value="">Todos os status</option>
      <?php foreach ($statusLabels as $val => [$lbl, $_]): ?>
      <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2.5 text-sm font-medium text-white hover:bg-white/10 transition-all">Filtrar</button>
    <?php if ($hasFilter): ?>
    <a href="/faturas" class="text-sm text-gray-400 hover:text-white transition-colors">Limpar</a>
    <?php endif; ?>
  </form>

  <?php if (empty($invoices)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-brand-500/10 p-6">
      <svg class="w-12 h-12 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
      </svg>
    </div>
    <p class="text-gray-400">Nenhuma fatura encontrada.</p>
    <?php if (\App\Support\Auth::can('invoices.create') && !$hasFilter): ?>
    <a href="/faturas/nova" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">Criar primeira fatura →</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="overflow-hidden rounded-2xl border border-white/5">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Nº</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Título</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Cliente</th>
          <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Total</th>
          <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Recebido</th>
          <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
          <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-400">Vencimento</th>
          <th class="px-5 py-3.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($invoices as $inv):
          [$ilabel, $icls] = $statusLabels[$inv['status']] ?? ['—',''];
          $isOverdue = $inv['status'] === 'overdue';
        ?>
        <tr class="group hover:bg-white/[0.03] transition-colors <?= $isOverdue ? 'bg-red-500/[0.03]' : '' ?>">
          <td class="px-5 py-4 font-mono text-xs text-gray-400"><?= e($inv['invoice_number']) ?></td>
          <td class="px-5 py-4">
            <a href="/faturas/<?= $inv['id'] ?>" class="font-medium text-white hover:text-brand-300 transition-colors"><?= e($inv['title']) ?></a>
          </td>
          <td class="px-5 py-4 text-gray-400"><?= e($inv['client_name']) ?></td>
          <td class="px-5 py-4 text-right font-mono text-white">R$ <?= number_format((float)$inv['total'], 2, ',', '.') ?></td>
          <td class="px-5 py-4 text-right font-mono <?= (float)$inv['amount_paid'] > 0 ? 'text-emerald-400' : 'text-gray-400' ?>">
            R$ <?= number_format((float)$inv['amount_paid'], 2, ',', '.') ?>
          </td>
          <td class="px-5 py-4 text-center">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 <?= $icls ?>"><?= $ilabel ?></span>
          </td>
          <td class="px-5 py-4 text-center text-xs <?= $isOverdue ? 'text-red-400 font-semibold' : 'text-gray-400' ?>">
            <?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?>
          </td>
          <td class="px-5 py-4 text-right">
            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <?php if (\App\Support\Auth::can('invoices.edit') && $inv['status'] === 'draft'): ?>
              <a href="/faturas/<?= $inv['id'] ?>/editar" class="text-xs text-gray-400 hover:text-white transition-colors">Editar</a>
              <?php endif; ?>
              <?php if (\App\Support\Auth::can('invoices.send') && $inv['status'] === 'draft'): ?>
              <form method="POST" action="/faturas/<?= $inv['id'] ?>/enviar">
                <?= csrf_field() ?>
                <button type="submit" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">Enviar</button>
              </form>
              <?php endif; ?>
              <?php if (\App\Support\Auth::can('payments.create') && in_array($inv['status'], ['sent','overdue','partial'])): ?>
              <a href="/pagamentos/novo?invoice_id=<?= $inv['id'] ?>" class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors">+ Pgto</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paginated['pages'] > 1): ?>
  <?php $filterStr = $hasFilter ? http_build_query(array_filter(['q' => $qFilter, 'client_id' => $clientFilter, 'status' => $statusFilter])) . '&' : ''; ?>
  <div class="mt-6 flex items-center justify-center gap-1">
    <?php if ($paginated['page'] > 1): ?>
    <a href="/faturas?<?= $filterStr ?>page=<?= $paginated['page'] - 1 ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:border-brand-500/50 hover:text-white transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <?php endif; ?>
    <?php for ($p = 1; $p <= $paginated['pages']; $p++): ?>
    <a href="/faturas?<?= $filterStr ?>page=<?= $p ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg text-sm transition-colors
              <?= $p === $paginated['page'] ? 'bg-brand-600 text-gray-950 font-semibold' : 'border border-white/10 text-gray-400 hover:border-brand-500/50 hover:text-gray-950' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
    <?php if ($paginated['page'] < $paginated['pages']): ?>
    <a href="/faturas?<?= $filterStr ?>page=<?= $paginated['page'] + 1 ?>"
       class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:border-brand-500/50 hover:text-white transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php view_end(); ?>
