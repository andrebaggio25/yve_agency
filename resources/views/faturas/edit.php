<?php view_layout('app'); view_start('content'); ?>

<?php $old = flash_old(); ?>

<div class="max-w-4xl mx-auto">
  <div class="mb-8">
    <a href="/faturas/<?= $invoice['id'] ?>" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Fatura</a>
    <h1 class="text-2xl font-bold text-white mt-2">Editar Fatura</h1>
    <p class="text-sm text-gray-400 mt-1"><?= e($invoice['title']) ?></p>
  </div>

  <form method="POST" action="/faturas/<?= $invoice['id'] ?>" x-data="invoiceEditForm()" class="space-y-6">
    <input type="hidden" name="_method" value="PUT">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-6">
      <h2 class="font-semibold text-white border-b border-white/5 pb-3">Dados</h2>
      <div class="grid sm:grid-cols-2 gap-6">

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Cliente <span class="text-red-400">*</span></label>
          <select name="client_id" required class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
            <?php foreach ($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= (string)$invoice['client_id'] === (string)$cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Contrato (opcional)</label>
          <select name="contract_id" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
            <option value="">—</option>
            <?php foreach ($contracts as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string)($invoice['contract_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Título <span class="text-red-400">*</span></label>
          <input type="text" name="title" value="<?= e($invoice['title']) ?>" required
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Status</label>
          <select name="status" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
            <?php foreach (['draft'=>'Rascunho','sent'=>'Enviada','cancelled'=>'Cancelada'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $invoice['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Vencimento</label>
          <input type="date" name="due_date" value="<?= e($invoice['due_date'] ?? '') ?>"
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Moeda</label>
          <select name="currency_code" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors">
            <?php foreach (['BRL'=>'R$ — Real','USD'=>'$ — Dólar','EUR'=>'€ — Euro'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= $invoice['currency_code'] === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Notas</label>
          <textarea name="notes" rows="2"
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-violet-500 focus:outline-none transition-colors resize-none"><?= e($invoice['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Itens -->
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-4">
      <h2 class="font-semibold text-white border-b border-white/5 pb-3">Itens</h2>

      <div class="space-y-3">
        <template x-for="(item, i) in items" :key="i">
          <div class="grid grid-cols-12 gap-3 items-start">
            <div class="col-span-6">
              <input type="text" :name="`items[${i}][description]`" x-model="item.description" placeholder="Descrição" required
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-2">
              <input type="number" :name="`items[${i}][quantity]`" x-model.number="item.quantity" @input="calcTotals()" step="0.001" min="0"
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-3">
              <input type="number" :name="`items[${i}][unit_price]`" x-model.number="item.unit_price" @input="calcTotals()" step="0.01" min="0"
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-1 flex items-center justify-end pt-1">
              <button type="button" @click="removeItem(i)" class="text-gray-500 hover:text-red-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
        </template>
      </div>

      <button type="button" @click="addItem()" class="inline-flex items-center gap-2 text-sm text-violet-400 hover:text-violet-300 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Adicionar item
      </button>

      <div class="border-t border-white/5 pt-4 space-y-2">
        <div class="flex justify-between text-sm">
          <span class="text-gray-400">Subtotal</span>
          <span class="text-white font-mono" x-text="fmtBrl(subtotal)"></span>
        </div>
        <div class="flex items-center justify-between text-sm gap-4">
          <span class="text-gray-400">Desconto (R$)</span>
          <input type="number" name="discount" x-model.number="discount" @input="calcTotals()" step="0.01" min="0"
            class="w-32 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-white text-right focus:border-violet-500 focus:outline-none transition-colors">
        </div>
        <div class="flex items-center justify-between text-sm gap-4">
          <span class="text-gray-400">Impostos (R$)</span>
          <input type="number" name="tax" x-model.number="tax" @input="calcTotals()" step="0.01" min="0"
            class="w-32 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-white text-right focus:border-violet-500 focus:outline-none transition-colors">
        </div>
        <div class="flex justify-between text-base font-bold border-t border-white/5 pt-2">
          <span class="text-white">Total</span>
          <span class="text-violet-300 font-mono" x-text="fmtBrl(total)"></span>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3">
      <a href="/faturas/<?= $invoice['id'] ?>" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-gray-300 hover:text-white transition-all">Cancelar</a>
      <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all">
        Salvar Alterações
      </button>
    </div>
  </form>
</div>

<script>
function invoiceEditForm() {
  const existingItems = <?= json_encode(array_values($invoice['items'] ?? [])) ?>;
  return {
    items: existingItems.length
      ? existingItems.map(i => ({ description: i.description, quantity: Number(i.quantity), unit_price: Number(i.unit_price) }))
      : [{ description: '', quantity: 1, unit_price: 0 }],
    discount: <?= (float)($invoice['discount'] ?? 0) ?>,
    tax: <?= (float)($invoice['tax'] ?? 0) ?>,
    subtotal: 0, total: 0,
    addItem() { this.items.push({ description: '', quantity: 1, unit_price: 0 }); },
    removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); this.calcTotals(); },
    calcTotals() {
      this.subtotal = this.items.reduce((s, it) => s + (Number(it.quantity) * Number(it.unit_price)), 0);
      this.total    = Math.max(0, this.subtotal - Number(this.discount) + Number(this.tax));
    },
    fmtBrl(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 }); },
    init() { this.calcTotals(); },
  };
}
</script>

<?php view_end(); ?>
