<?php
/** @var array $clients
 *  @var array $contract  (optional — edit mode)
 *  @var string $action
 *  @var string $method  (POST|PUT)
 */
$old = flash_old();
$v = fn(string $k, mixed $def = '') => $old[$k] ?? $contract[$k] ?? $def;
?>

<form method="POST" action="<?= e($action) ?>" class="space-y-6">
  <?php if ($method !== 'POST'): ?>
  <input type="hidden" name="_method" value="<?= e($method) ?>">
  <?php endif; ?>
  <?= csrf_field() ?>

  <div class="grid sm:grid-cols-2 gap-6">
    <!-- Título -->
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Título <span class="text-red-400">*</span></label>
      <input type="text" name="title" value="<?= e($v('title')) ?>" required
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none transition-colors">
    </div>

    <!-- Cliente -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Cliente <span class="text-red-400">*</span></label>
      <select name="client_id" required
        class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        <option value="">Selecione...</option>
        <?php foreach ($clients as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= (string)$v('client_id') === (string)$cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Status -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Status</label>
      <select name="status" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        <?php foreach (['draft'=>'Rascunho','active'=>'Ativo','expired'=>'Expirado','cancelled'=>'Cancelado'] as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $v('status','draft') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Valor -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Valor (R$)</label>
      <input type="number" name="value" value="<?= e($v('value', '0.00')) ?>" step="0.01" min="0"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
    </div>

    <!-- Moeda -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Moeda</label>
      <select name="currency_code" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        <?php foreach (['BRL'=>'R$ — Real','USD'=>'$ — Dólar','EUR'=>'€ — Euro'] as $code => $label): ?>
        <option value="<?= $code ?>" <?= $v('currency_code','BRL') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Início -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Data Início</label>
      <input type="date" name="start_date" value="<?= e($v('start_date')) ?>"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
    </div>

    <!-- Fim -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Data Fim</label>
      <input type="date" name="end_date" value="<?= e($v('end_date')) ?>"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
    </div>

    <!-- Assinado em -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Assinado em</label>
      <input type="date" name="signed_at" value="<?= e(substr($v('signed_at',''), 0, 10)) ?>"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
    </div>

    <!-- Recorrente -->
    <div class="flex items-center gap-3 pt-6">
      <input type="checkbox" id="recurring" name="recurring" value="1" <?= $v('recurring') ? 'checked' : '' ?>
        class="h-4 w-4 rounded border-white/20 bg-white/[0.03] text-brand-500 focus:ring-brand-500">
      <label for="recurring" class="text-sm text-gray-300">Contrato recorrente</label>
    </div>

    <!-- Recorrência -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Periodicidade</label>
      <select name="recurrence" class="w-full rounded-xl border border-white/10 bg-[#09090f] px-4 py-2.5 text-white focus:border-brand-500 focus:outline-none transition-colors">
        <option value="">—</option>
        <?php foreach (['monthly'=>'Mensal','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'] as $v2 => $l): ?>
        <option value="<?= $v2 ?>" <?= $v('recurrence') === $v2 ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Descrição -->
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Descrição</label>
      <textarea name="description" rows="3"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none transition-colors resize-none"><?= e($v('description')) ?></textarea>
    </div>

    <!-- Notas -->
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Notas internas</label>
      <textarea name="notes" rows="2"
        class="w-full rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none transition-colors resize-none"><?= e($v('notes')) ?></textarea>
    </div>
  </div>

  <div class="flex items-center justify-end gap-3 pt-2">
    <a href="/contratos" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-gray-300 hover:text-white hover:border-white/20 transition-all">Cancelar</a>
    <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
      Salvar Contrato
    </button>
  </div>
</form>
