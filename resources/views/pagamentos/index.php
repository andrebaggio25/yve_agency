<?php view_layout('app'); view_start('content');
$methodLabels = ['pix'=>'PIX','boleto'=>'Boleto','credit_card'=>'Cartão de Crédito','bank_transfer'=>'TED/DOC','cash'=>'Dinheiro','other'=>'Outro'];
?>

<div class="min-h-screen">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Financeiro</p>
      <h1 class="text-2xl font-bold text-white">Pagamentos</h1>
      <p class="mt-1 text-sm text-gray-400"><?= count($payments) ?> registro<?= count($payments) !== 1 ? 's' : '' ?></p>
    </div>
  </div>

  <?php if (empty($payments)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-brand-500/10 p-6">
      <svg class="w-12 h-12 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
      </svg>
    </div>
    <p class="text-gray-400">Nenhum pagamento registrado ainda.</p>
    <a href="/faturas" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">Ver faturas →</a>
  </div>
  <?php else: ?>
  <div class="overflow-hidden rounded-2xl border border-white/5">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Data</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Fatura</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cliente</th>
          <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Método</th>
          <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Valor</th>
          <th class="px-5 py-3.5"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($payments as $p): ?>
        <tr class="group hover:bg-white/[0.03] transition-colors">
          <td class="px-5 py-4 text-gray-300"><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
          <td class="px-5 py-4">
            <a href="/faturas/<?= $p['invoice_id'] ?>" class="text-white hover:text-brand-300 transition-colors"><?= e($p['invoice_title']) ?></a>
            <p class="text-xs text-gray-500 font-mono"><?= e($p['invoice_number']) ?></p>
          </td>
          <td class="px-5 py-4 text-gray-400"><?= e($p['client_name']) ?></td>
          <td class="px-5 py-4 text-gray-400"><?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></td>
          <td class="px-5 py-4 text-right font-mono text-emerald-400">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
          <td class="px-5 py-4 text-right">
            <?php if (\App\Support\Auth::can('payments.delete')): ?>
            <form method="POST" action="/pagamentos/<?= $p['id'] ?>" class="opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirm('Remover este pagamento?')">
              <input type="hidden" name="_method" value="DELETE">
              <?= csrf_field() ?>
              <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">Remover</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php view_end(); ?>
