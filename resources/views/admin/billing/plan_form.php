<?php view_layout('admin'); view_start('title'); ?><?= $plan ? 'Editar Plano' : 'Novo Plano' ?><?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-xl">
  <div class="mb-6">
    <h1 class="text-xl font-semibold text-white"><?= $plan ? 'Editar plano' : 'Novo plano' ?></h1>
  </div>

  <form method="POST" action="<?= $plan ? '/admin/planos/' . $plan['id'] : '/admin/planos' ?>" class="card p-6 space-y-5">
    <?= csrf_field() ?>
    <?php if ($plan): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Nome *</label>
        <input aria-label="Nome" type="text" name="name" required value="<?= e($plan['name'] ?? '') ?>" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Slug *</label>
        <input type="text" name="slug" required value="<?= e($plan['slug'] ?? '') ?>"
               class="input-field w-full font-mono text-sm"
               <?= $plan ? 'readonly' : '' ?>>
      </div>
    </div>

    <div>
      <label class="label-field">Descrição</label>
      <input type="text" name="description" value="<?= e($plan['description'] ?? '') ?>" class="input-field w-full">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Preço mensal (R$)</label>
        <input type="number" name="price_monthly" step="0.01" min="0"
               value="<?= $plan['price_monthly'] ?? 0 ?>" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Preço anual (R$)</label>
        <input type="number" name="price_yearly" step="0.01" min="0"
               value="<?= $plan['price_yearly'] ?? 0 ?>" class="input-field w-full">
      </div>
    </div>

    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Limites (vazio = ilimitado)</p>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Máx. clientes</label>
        <input type="number" name="max_clients" min="0"
               value="<?= $plan['max_clients'] ?? '' ?>" placeholder="∞" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Máx. usuários</label>
        <input type="number" name="max_users" min="0"
               value="<?= $plan['max_users'] ?? '' ?>" placeholder="∞" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Máx. contas Meta</label>
        <input type="number" name="max_meta_accounts" min="0"
               value="<?= $plan['max_meta_accounts'] ?? '' ?>" placeholder="∞" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Máx. contas orgânicas</label>
        <input type="number" name="max_organic_accounts" min="0"
               value="<?= $plan['max_organic_accounts'] ?? '' ?>" placeholder="∞" class="input-field w-full">
      </div>
    </div>

    <div>
      <label class="label-field">Features (uma por linha)</label>
      <textarea aria-label="content_plans&#10;approvals&#10;financial&#10;tasks&#10;portal&#10;ads&#10;organic&#10;ai_insights" name="features" rows="5" class="input-field w-full font-mono text-xs resize-none"
                placeholder="content_plans&#10;approvals&#10;financial&#10;tasks&#10;portal&#10;ads&#10;organic&#10;ai_insights"><?= e(implode("\n", $plan['features'] ?? [])) ?></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Ordem</label>
        <input type="number" name="sort_order" min="0"
               value="<?= $plan['sort_order'] ?? 0 ?>" class="input-field w-28">
      </div>
      <div class="flex items-end pb-1">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="is_active" value="1"
                 <?= ($plan['is_active'] ?? true) ? 'checked' : '' ?>
                 class="w-4 h-4 rounded accent-brand-500">
          <span class="text-sm text-gray-300">Plano ativo</span>
        </label>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/admin/planos" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Salvar</button>
    </div>
  </form>
</div>

<?php view_end(); ?>
