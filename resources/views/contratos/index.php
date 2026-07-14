<?php view_layout('app'); view_start('content'); ?>

<?php
$statusLabels = [
    'draft'     => ['Rascunho',   'bg-gray-500/15 text-gray-400 ring-gray-500/30'],
    'active'    => ['Ativo',      'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'],
    'expired'   => ['Expirado',   'bg-amber-500/15 text-amber-300 ring-amber-500/30'],
    'cancelled' => ['Cancelado',  'bg-red-500/15 text-red-400 ring-red-500/30'],
];
?>

<div class="min-h-screen">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Financeiro</p>
      <h1 class="text-2xl font-bold text-white">Contratos</h1>
      <p class="mt-1 text-sm text-gray-400"><?= count($contracts) ?> contrato<?= count($contracts) !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('contracts.create')): ?>
    <a href="/contratos/novo" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 hover:scale-105 active:scale-95 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Novo Contrato
    </a>
    <?php endif; ?>
  </div>

  <!-- Filtros -->
  <form method="GET" class="mb-6 flex flex-wrap gap-3 items-end">
    <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar título..."
      class="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none w-56">
    <select name="client_id" class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-4 py-2.5 focus:border-brand-500 focus:outline-none">
      <option value="">Todos os clientes</option>
      <?php foreach ($clients as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= ($filters['client_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="rounded-xl border border-white/10 bg-[#09090f] text-sm text-white px-4 py-2.5 focus:border-brand-500 focus:outline-none">
      <option value="">Todos os status</option>
      <?php foreach ($statusLabels as $v => [$l, $_]): ?>
      <option value="<?= $v ?>" <?= ($filters['status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2.5 text-sm font-medium text-white hover:bg-white/10 transition-all">Filtrar</button>
    <?php if (!empty($filters['q']) || !empty($filters['client_id']) || !empty($filters['status'])): ?>
    <a href="/contratos" class="text-sm text-gray-400 hover:text-white transition-colors">Limpar</a>
    <?php endif; ?>
  </form>

  <?php if (empty($contracts)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-brand-500/10 p-6">
      <svg class="w-12 h-12 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
    </div>
    <p class="text-gray-400">Nenhum contrato encontrado.</p>
    <?php if (\App\Support\Auth::can('contracts.create')): ?>
    <a href="/contratos/novo" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">Criar primeiro contrato →</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="overflow-hidden rounded-2xl border border-white/5">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Título</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cliente</th>
          <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Valor</th>
          <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
          <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Validade</th>
          <th class="px-5 py-3.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($contracts as $c):
          [$slabel, $scls] = $statusLabels[$c['status']] ?? ['—', 'bg-gray-500/15 text-gray-400 ring-gray-500/30'];
        ?>
        <tr class="group hover:bg-white/[0.03] transition-colors">
          <td class="px-5 py-4">
            <a href="/contratos/<?= $c['id'] ?>" class="font-medium text-white hover:text-brand-300 transition-colors"><?= e($c['title']) ?></a>
          </td>
          <td class="px-5 py-4 text-gray-400"><?= e($c['client_name']) ?></td>
          <td class="px-5 py-4 text-right font-mono text-white">R$ <?= number_format((float)$c['value'], 2, ',', '.') ?></td>
          <td class="px-5 py-4 text-center">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 <?= $scls ?>"><?= $slabel ?></span>
          </td>
          <td class="px-5 py-4 text-center text-gray-400 text-xs">
            <?php if ($c['end_date']): ?>
              <?= date('d/m/Y', strtotime($c['end_date'])) ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="px-5 py-4 text-right">
            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <a href="/contratos/<?= $c['id'] ?>/editar" class="text-xs text-gray-400 hover:text-white transition-colors">Editar</a>
              <?php if (\App\Support\Auth::can('contracts.delete')): ?>
              <form method="POST" action="/contratos/<?= $c['id'] ?>" onsubmit="return confirm('Remover este contrato?')">
                <input type="hidden" name="_method" value="DELETE">
                <?= csrf_field() ?>
                <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">Remover</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
