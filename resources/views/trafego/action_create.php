<?php view_layout('app'); view_start('title'); ?>Nova Ação<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg mx-auto">
  <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
    <a href="/trafego/acoes" class="hover:text-gray-300">Ações</a>
    <span>/</span>
    <span class="text-gray-300">Nova ação</span>
  </nav>

  <h1 class="text-xl font-semibold text-white mb-5">Solicitar ação manual</h1>

  <form method="POST" action="/trafego/acoes"
        class="card p-6 space-y-5"
        x-data="actionForm()" x-init="init()">
    <?= csrf_field() ?>

    <div>
      <label class="label-field">Conta de anúncios *</label>
      <select name="ad_account_id" required class="input-field w-full"
              @change="loadCampaigns($event.target.value)">
        <option value="">Selecione…</option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $accountId == $a['id'] ? 'selected' : '' ?>>
          <?= e($a['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div x-show="campaigns.length > 0">
      <label class="label-field">Campanha (opcional)</label>
      <select name="campaign_id" class="input-field w-full">
        <option value="">— Toda a conta —</option>
        <template x-for="c in campaigns" :key="c.id">
          <option :value="c.id" x-text="c.name"></option>
        </template>
      </select>
    </div>

    <div>
      <label class="label-field">Tipo de ação *</label>
      <select name="action_type" required class="input-field w-full">
        <option value="pause">Pausar</option>
        <option value="resume">Reativar</option>
        <option value="increase_budget">Aumentar orçamento</option>
        <option value="decrease_budget">Reduzir orçamento</option>
        <option value="test_creative">Testar criativo</option>
        <option value="archive">Arquivar</option>
      </select>
    </div>

    <div>
      <label class="label-field">Descrição *</label>
      <input aria-label="Descreva a ação a ser realizada" type="text" name="description" required
             placeholder="Descreva a ação a ser realizada"
             class="input-field w-full">
    </div>

    <div>
      <label class="label-field">Justificativa</label>
      <textarea aria-label="Por que esta ação deve ser realizada?" name="justification" rows="3"
                placeholder="Por que esta ação deve ser realizada?"
                class="input-field w-full resize-none"></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Valor atual</label>
        <input aria-label="Ex: R$ 50/dia" type="text" name="current_value" placeholder="Ex: R$ 50/dia" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Valor proposto</label>
        <input aria-label="Ex: R$ 35/dia" type="text" name="proposed_value" placeholder="Ex: R$ 35/dia" class="input-field w-full">
      </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/trafego/acoes" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Criar solicitação</button>
    </div>
  </form>
</div>

<?php view_start('scripts'); ?>
<script>
function actionForm() {
  return {
    campaigns: <?= json_encode($campaigns) ?>,
    init() {
      <?php if ($accountId): ?>
      // campaigns já carregadas via PHP
      <?php endif; ?>
    },
    async loadCampaigns(accountId) {
      if (!accountId) { this.campaigns = []; return; }
      try {
        const r = await fetch(`/trafego/contas/${accountId}/campanhas`);
        this.campaigns = await r.json();
      } catch { this.campaigns = []; }
    }
  }
}
</script>
<?php view_end(); ?>

<?php view_end(); ?>
