<?php view_layout('app'); view_start('content'); ?>
<?php
$statusLabels = [
    'draft'     => ['Rascunho', 'bg-gray-500/15 text-gray-400 ring-gray-500/30'],
    'sent'      => ['Enviada',  'bg-blue-500/15 text-blue-300 ring-blue-500/30'],
    'paid'      => ['Paga',     'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'],
    'overdue'   => ['Vencida',  'bg-red-500/15 text-red-400 ring-red-500/30'],
    'partial'   => ['Parcial',  'bg-amber-500/15 text-amber-300 ring-amber-500/30'],
    'cancelled' => ['Cancelada','bg-gray-500/15 text-gray-400 ring-gray-500/30'],
];
[$ilabel, $icls] = $statusLabels[$invoice['status']] ?? ['—',''];
$remaining = (float)$invoice['total'] - (float)$invoice['amount_paid'];
?>

<div class="max-w-3xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-start justify-between">
    <div>
      <a href="/faturas" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Faturas</a>
      <div class="mt-2 flex items-center gap-3">
        <h1 class="text-2xl font-bold text-white"><?= e($invoice['title']) ?></h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 <?= $icls ?>"><?= $ilabel ?></span>
      </div>
      <p class="text-sm text-gray-400 mt-1">
        <span class="font-mono"><?= e($invoice['invoice_number']) ?></span>
        · <?= e($invoice['client_name']) ?>
        <?php if ($invoice['due_date']): ?>
        · Vencimento: <span class="<?= $invoice['status'] === 'overdue' ? 'text-red-400 font-semibold' : '' ?>"><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></span>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex gap-2 flex-wrap" x-data="{ emailModal: false }">
      <!-- PDF -->
      <a href="/faturas/<?= $invoice['id'] ?>/pdf" target="_blank"
         class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        PDF
      </a>

      <?php if (\App\Support\Auth::can('invoices.send')): ?>
      <!-- Email -->
      <button @click="emailModal = true"
         class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Enviar E-mail
      </button>

      <!-- Email modal -->
      <div x-show="emailModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.outside="emailModal = false" class="w-full max-w-md rounded-2xl border border-white/10 bg-[#111118] p-6 shadow-2xl">
          <h3 class="font-semibold text-white text-lg mb-4">Enviar Fatura por E-mail</h3>
          <form method="POST" action="/faturas/<?= $invoice['id'] ?>/email">
            <?= csrf_field() ?>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">E-mail do destinatário <span class="text-red-400">*</span></label>
                <input type="email" name="email" required autofocus
                  class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Nome do destinatário</label>
                <input type="text" name="name" value="<?= e($invoice['client_name']) ?>"
                  class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
              </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" @click="emailModal = false"
                class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-gray-300 hover:text-white transition-colors">Cancelar</button>
              <button type="submit"
                class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500 transition-all">Enviar</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (\App\Support\Auth::can('invoices.edit') && $invoice['status'] === 'draft'): ?>
      <a href="/faturas/<?= $invoice['id'] ?>/editar"
         class="inline-flex items-center gap-2 rounded-xl bg-white/[0.06] border border-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-all">
        Editar
      </a>
      <?php endif; ?>
      <?php if (\App\Support\Auth::can('invoices.send') && $invoice['status'] === 'draft'): ?>
      <form method="POST" action="/faturas/<?= $invoice['id'] ?>/enviar">
        <?= csrf_field() ?>
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 transition-all">
          Marcar Enviada
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Itens -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] overflow-hidden">
    <div class="px-6 py-4 border-b border-white/5">
      <h2 class="font-semibold text-white">Itens</h2>
    </div>
    <?php if (!empty($invoice['items'])): ?>
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-6 py-3 text-left text-xs text-gray-500">Descrição</th>
          <th class="px-6 py-3 text-right text-xs text-gray-500">Qtd</th>
          <th class="px-6 py-3 text-right text-xs text-gray-500">Preço Unit.</th>
          <th class="px-6 py-3 text-right text-xs text-gray-500">Total</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php foreach ($invoice['items'] as $item): ?>
        <tr>
          <td class="px-6 py-3 text-gray-300"><?= e($item['description']) ?></td>
          <td class="px-6 py-3 text-right text-gray-400 font-mono"><?= number_format((float)$item['quantity'], 3, ',', '.') ?></td>
          <td class="px-6 py-3 text-right text-gray-400 font-mono">R$ <?= number_format((float)$item['unit_price'], 2, ',', '.') ?></td>
          <td class="px-6 py-3 text-right text-white font-mono">R$ <?= number_format((float)$item['total_price'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <!-- Totals -->
    <div class="px-6 py-4 border-t border-white/5 space-y-1.5 text-sm">
      <div class="flex justify-between text-gray-400"><span>Subtotal</span><span class="font-mono">R$ <?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?></span></div>
      <?php if ((float)$invoice['discount'] > 0): ?>
      <div class="flex justify-between text-gray-400"><span>Desconto</span><span class="font-mono text-red-400">- R$ <?= number_format((float)$invoice['discount'], 2, ',', '.') ?></span></div>
      <?php endif; ?>
      <?php if ((float)$invoice['tax'] > 0): ?>
      <div class="flex justify-between text-gray-400"><span>Impostos</span><span class="font-mono">R$ <?= number_format((float)$invoice['tax'], 2, ',', '.') ?></span></div>
      <?php endif; ?>
      <div class="flex justify-between font-bold text-white border-t border-white/5 pt-2">
        <span>Total</span>
        <span class="font-mono text-violet-300">R$ <?= number_format((float)$invoice['total'], 2, ',', '.') ?></span>
      </div>
      <?php if ((float)$invoice['amount_paid'] > 0): ?>
      <div class="flex justify-between text-emerald-400"><span>Recebido</span><span class="font-mono">R$ <?= number_format((float)$invoice['amount_paid'], 2, ',', '.') ?></span></div>
      <div class="flex justify-between <?= $remaining > 0 ? 'text-amber-400' : 'text-emerald-400' ?>">
        <span><?= $remaining > 0 ? 'Saldo restante' : 'Quitada' ?></span>
        <span class="font-mono">R$ <?= number_format($remaining, 2, ',', '.') ?></span>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhum item nesta fatura.</p>
    <?php endif; ?>
  </div>

  <!-- Pagamentos -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] overflow-hidden">
    <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
      <h2 class="font-semibold text-white">Pagamentos</h2>
      <?php if (\App\Support\Auth::can('payments.create') && in_array($invoice['status'], ['sent','overdue','partial'])): ?>
      <a href="/pagamentos/novo?invoice_id=<?= $invoice['id'] ?>"
         class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600/80 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 transition-all">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Registrar
      </a>
      <?php endif; ?>
    </div>
    <?php if (!empty($invoice['payments'])): ?>
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 bg-white/[0.02]">
          <th class="px-6 py-3 text-left text-xs text-gray-500">Data</th>
          <th class="px-6 py-3 text-left text-xs text-gray-500">Método</th>
          <th class="px-6 py-3 text-left text-xs text-gray-500">Referência</th>
          <th class="px-6 py-3 text-right text-xs text-gray-500">Valor</th>
          <th class="px-6 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.04]">
        <?php
        $methodLabels = ['pix'=>'PIX','boleto'=>'Boleto','credit_card'=>'Cartão','bank_transfer'=>'TED/DOC','cash'=>'Dinheiro','other'=>'Outro'];
        foreach ($invoice['payments'] as $p): ?>
        <tr class="group">
          <td class="px-6 py-3 text-gray-300"><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
          <td class="px-6 py-3 text-gray-400"><?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></td>
          <td class="px-6 py-3 text-gray-500 text-xs"><?= e($p['reference'] ?? '—') ?></td>
          <td class="px-6 py-3 text-right text-emerald-400 font-mono">R$ <?= number_format((float)$p['amount'], 2, ',', '.') ?></td>
          <td class="px-6 py-3 text-right">
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
    <?php else: ?>
    <p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhum pagamento registrado.</p>
    <?php endif; ?>
  </div>

  <?php if (\App\Support\Auth::can('invoices.delete') && $invoice['status'] === 'draft'): ?>
  <form method="POST" action="/faturas/<?= $invoice['id'] ?>" class="flex justify-end" onsubmit="return confirm('Remover esta fatura?')">
    <input type="hidden" name="_method" value="DELETE">
    <?= csrf_field() ?>
    <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition-colors">Remover fatura</button>
  </form>
  <?php endif; ?>
</div>

<?php view_end(); ?>
