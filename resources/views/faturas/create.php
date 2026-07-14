<?php view_layout('app'); view_start('content'); ?>

<?php
$preClientId  = (int) ($_GET['client_id']  ?? 0);
$preContractId = (int) ($_GET['contract_id'] ?? 0);
$old = flash_old();
?>

<div class="max-w-4xl mx-auto">
  <div class="mb-8">
    <a href="/faturas" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Faturas</a>
    <h1 class="text-2xl font-bold text-white mt-2">Nova Fatura</h1>
  </div>

  <form method="POST" action="/faturas" x-data="invoiceForm()" class="space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-6">
      <h2 class="font-semibold text-white border-b border-white/5 pb-3">Dados</h2>
      <div class="grid sm:grid-cols-2 gap-6">

        <!-- Cliente -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Cliente <span class="text-red-400">*</span></label>
          <select name="client_id" required x-model="clientId" @change="loadContracts()"
            class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
            <option value="">Selecione...</option>
            <?php foreach ($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= $preClientId === (int)$cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Contrato -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Contrato (opcional)</label>
          <select name="contract_id"
            class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
            <option value="">—</option>
            <template x-for="c in contracts" :key="c.id">
              <option :value="c.id" :selected="c.id == <?= $preContractId ?>" x-text="c.title"></option>
            </template>
          </select>
        </div>

        <!-- Título -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Título <span class="text-red-400">*</span></label>
          <input type="text" name="title" value="<?= e($old['title'] ?? '') ?>" required
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Status</label>
          <select name="status" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
            <?php foreach (['draft'=>'Rascunho','sent'=>'Enviada'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($old['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Vencimento -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Vencimento</label>
          <input type="date" name="due_date" value="<?= e($old['due_date'] ?? '') ?>"
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        </div>

        <!-- Moeda -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Moeda</label>
          <select name="currency_code" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
            <?php foreach (['BRL'=>'R$ — Real','USD'=>'$ — Dólar','EUR'=>'€ — Euro'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= ($old['currency_code'] ?? 'BRL') === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Notas -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Notas</label>
          <textarea name="notes" rows="2"
            class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors resize-none"><?= e($old['notes'] ?? '') ?></textarea>
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
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-2">
              <input type="number" :name="`items[${i}][quantity]`" x-model.number="item.quantity" @input="calcItem(i)" placeholder="Qtd" step="0.001" min="0"
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-3">
              <input type="number" :name="`items[${i}][unit_price]`" x-model.number="item.unit_price" @input="calcItem(i)" placeholder="Preço unit." step="0.01" min="0"
                class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none transition-colors">
            </div>
            <div class="col-span-1 flex items-center justify-end pt-1">
              <button type="button" @click="removeItem(i)" class="text-gray-500 hover:text-red-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
        </template>
      </div>

      <button type="button" @click="addItem()"
        class="inline-flex items-center gap-2 text-sm text-brand-400 hover:text-brand-300 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Adicionar item
      </button>

      <!-- Totais -->
      <div class="border-t border-white/5 pt-4 space-y-2">
        <div class="flex justify-between text-sm">
          <span class="text-gray-400">Subtotal</span>
          <span class="text-white font-mono" x-text="fmtBrl(subtotal)"></span>
        </div>
        <div class="flex items-center justify-between text-sm gap-4">
          <span class="text-gray-400">Desconto (R$)</span>
          <input type="number" name="discount" x-model.number="discount" @input="calcTotals()" step="0.01" min="0"
            class="w-32 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-white text-right focus:border-brand-500 focus:outline-none transition-colors">
        </div>
        <div class="flex items-center justify-between text-sm gap-4">
          <span class="text-gray-400">Impostos (R$)</span>
          <input type="number" name="tax" x-model.number="tax" @input="calcTotals()" step="0.01" min="0"
            class="w-32 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-1.5 text-sm text-white text-right focus:border-brand-500 focus:outline-none transition-colors">
        </div>
        <div class="flex justify-between text-base font-bold border-t border-white/5 pt-2">
          <span class="text-white">Total</span>
          <span class="text-brand-300 font-mono" x-text="fmtBrl(total)"></span>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3">
      <a href="/faturas" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-gray-300 hover:text-white hover:border-white/20 transition-all">Cancelar</a>
      <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
        Criar Fatura
      </button>
    </div>
  </form>
</div>

<script>
function invoiceForm() {
  return {
    clientId: '<?= $preClientId ?>',
    contracts: [],
    items: [{ description: '', quantity: 1, unit_price: 0 }],
    discount: 0,
    tax: 0,
    subtotal: 0,
    total: 0,

    async loadContracts() {
      if (!this.clientId) { this.contracts = []; return; }
      try {
        const r = await fetch(`/clientes/${this.clientId}/contratos-ativos`);
        this.contracts = await r.json();
      } catch { this.contracts = []; }
    },
    addItem() { this.items.push({ description: '', quantity: 1, unit_price: 0 }); },
    removeItem(i) {
      if (this.items.length > 1) this.items.splice(i, 1);
      this.calcTotals();
    },
    calcItem(i) { this.calcTotals(); },
    calcTotals() {
      this.subtotal = this.items.reduce((s, it) => s + (Number(it.quantity) * Number(it.unit_price)), 0);
      this.total    = Math.max(0, this.subtotal - Number(this.discount) + Number(this.tax));
    },
    fmtBrl(v) {
      return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    },
    init() {
      if (this.clientId) this.loadContracts();
      this.calcTotals();
    },
  };
}
</script>

<?php view_end(); ?>
