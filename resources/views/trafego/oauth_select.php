<?php view_layout('app'); view_start('title'); ?>Selecionar Conta Meta Ads<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg mx-auto">
  <div class="mb-6">
    <div class="flex items-center gap-3 mb-1">
      <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h1 class="text-xl font-semibold text-white">Autorização concedida!</h1>
    </div>
    <p class="text-sm text-gray-400">Selecione a conta de anúncios que deseja conectar.</p>
  </div>

  <form method="POST" action="/trafego/contas/oauth/salvar" class="card p-6 space-y-5">
    <?= csrf_field() ?>

    <div>
      <label class="label-field">Conta de anúncios *</label>
      <div class="space-y-2" x-data="{selected: ''}">
        <?php foreach ($accounts as $acc): ?>
        <?php
          $rawId = $acc['id'];
          $cleanId = ltrim($rawId, 'act_');
          $status = (int)($acc['account_status'] ?? 1);
          $statusLabel = match($status) { 1=>'Ativa', 2=>'Desativada', 3=>'Não confirmada', 7=>'Pendente', 9=>'Em revisão', default=>'Desconhecida' };
          $statusColor = $status === 1 ? 'text-green-400' : 'text-red-400';
        ?>
        <label class="flex items-center gap-3 p-3 rounded-xl border border-white/[0.06] cursor-pointer hover:bg-white/[0.03] transition-colors has-[:checked]:border-brand-500/40 has-[:checked]:bg-brand-500/5">
          <input aria-label="Conta" type="radio" name="account_id" value="<?= e($cleanId) ?>" required
                 x-on:change="selected='<?= e($cleanId) ?>'"
                 class="text-brand-500">
          <input type="hidden" name="account_name_<?= e($cleanId) ?>" value="<?= e($acc['name'] ?? "Conta {$cleanId}") ?>">
          <input type="hidden" name="currency_<?= e($cleanId) ?>"     value="<?= e($acc['currency'] ?? 'BRL') ?>">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white"><?= e($acc['name'] ?? "Conta {$cleanId}") ?></p>
            <p class="text-xs text-gray-400">ID: <?= e($cleanId) ?> &middot;
              <span class="<?= $statusColor ?>"><?= $statusLabel ?></span>
            </p>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <!-- Hidden fields propagated via JS on submit -->
      <input type="hidden" name="account_name" id="js_account_name">
      <input type="hidden" name="currency"     id="js_currency">
    </div>

    <div>
      <label class="label-field">Cliente (opcional)</label>
      <select aria-label="Cliente" name="client_id" class="input-field w-full">
        <option value="">— Sem cliente —</option>
        <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="label-field">Dias de sincronização retroativa</label>
      <input type="number" name="sync_days_back" value="30" min="1" max="365" class="input-field w-32">
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/trafego/contas" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Conectar conta</button>
    </div>
  </form>
</div>

<script>
// Propagate account name + currency to hidden fields on submit
document.querySelector('form').addEventListener('submit', function() {
  const selected = document.querySelector('input[name="account_id"]:checked');
  if (!selected) return;
  const id = selected.value;
  document.getElementById('js_account_name').value =
    (document.querySelector(`input[name="account_name_${id}"]`) || {value:''}).value;
  document.getElementById('js_currency').value =
    (document.querySelector(`input[name="currency_${id}"]`) || {value:'BRL'}).value;
});
</script>

<?php view_end(); ?>
