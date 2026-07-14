<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-xl mx-auto">
  <div class="mb-8">
    <?php if ($invoice): ?>
    <a href="/faturas/<?= $invoice['id'] ?>" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Fatura <?= e($invoice['invoice_number']) ?></a>
    <?php else: ?>
    <a href="/pagamentos" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Pagamentos</a>
    <?php endif; ?>
    <h1 class="text-2xl font-bold text-white mt-2">Registrar Pagamento</h1>
    <?php if ($invoice): ?>
    <p class="text-sm text-gray-400 mt-1">
      <?= e($invoice['title']) ?> · <?= e($invoice['client_name']) ?>
      — Total: <span class="text-white font-semibold">R$ <?= number_format((float)$invoice['total'], 2, ',', '.') ?></span>
      <?php $rem = (float)$invoice['total'] - (float)$invoice['amount_paid']; ?>
      <?php if ($rem < (float)$invoice['total']): ?>
      · Restante: <span class="text-amber-400 font-semibold">R$ <?= number_format($rem, 2, ',', '.') ?></span>
      <?php endif; ?>
    </p>
    <?php endif; ?>
  </div>

  <form method="POST" action="/pagamentos" class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="invoice_id" value="<?= $invoice ? $invoice['id'] : '' ?>">

    <div class="grid sm:grid-cols-2 gap-6">
      <!-- Valor -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Valor (R$) <span class="text-red-400">*</span></label>
        <input type="number" name="amount" value="<?= $invoice ? number_format(max(0, (float)$invoice['total'] - (float)$invoice['amount_paid']), 2, '.', '') : '' ?>"
          step="0.01" min="0.01" required
          class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
      </div>

      <!-- Data -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Data do Pagamento <span class="text-red-400">*</span></label>
        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required
          class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
      </div>

      <!-- Método -->
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Método</label>
        <div class="grid grid-cols-3 gap-2">
          <?php
          $methods = ['pix'=>'PIX','boleto'=>'Boleto','credit_card'=>'Cartão','bank_transfer'=>'TED/DOC','cash'=>'Dinheiro','other'=>'Outro'];
          foreach ($methods as $v => $l): ?>
          <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 cursor-pointer hover:border-brand-500/40 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-500/10">
            <input type="radio" name="payment_method" value="<?= $v ?>" <?= $v === 'pix' ? 'checked' : '' ?> class="text-brand-500 focus:ring-brand-500">
            <span class="text-sm text-gray-300"><?= $l ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Referência -->
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Referência / Comprovante</label>
        <input type="text" name="reference" placeholder="Ex: txid do PIX, número do boleto..."
          class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none transition-colors">
      </div>

      <!-- Notas -->
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Notas</label>
        <textarea name="notes" rows="2"
          class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none transition-colors resize-none"></textarea>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <?php if ($invoice): ?>
      <a href="/faturas/<?= $invoice['id'] ?>" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-gray-300 hover:text-white transition-all">Cancelar</a>
      <?php else: ?>
      <a href="/pagamentos" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-gray-300 hover:text-white transition-all">Cancelar</a>
      <?php endif; ?>
      <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-500 transition-all">
        Confirmar Pagamento
      </button>
    </div>
  </form>
</div>

<?php view_end(); ?>
