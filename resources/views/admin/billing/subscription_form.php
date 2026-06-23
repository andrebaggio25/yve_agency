<?php view_layout('admin'); view_start('title'); ?>Assinatura — <?= e($agency['name']) ?><?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/assinaturas" class="hover:text-white">Assinaturas</a> / <?= e($agency['name']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-xl mb-6">
  <h1 class="text-xl font-semibold text-white"><?= e($agency['name']) ?></h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= $existing ? 'Editar assinatura existente' : 'Atribuir plano a este tenant' ?></p>
</div>

<form method="POST" action="/admin/assinaturas/<?= $agency['id'] ?>" class="max-w-xl card p-6 space-y-5">
  <?= csrf_field() ?>

  <div>
    <label class="label-field">Plano <span class="text-red-400">*</span></label>
    <select name="plan_id" required class="input-field w-full">
      <?php foreach ($plans as $p): ?>
      <option value="<?= $p['id'] ?>" <?= ($existing['plan_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
        <?= e($p['name']) ?>
        <?php if ($p['price_monthly'] > 0): ?>
          — R$ <?= number_format($p['price_monthly'], 0, ',', '.') ?>/mês
        <?php else: ?>
          (grátis)
        <?php endif; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="label-field">Ciclo de cobrança</label>
      <select name="billing_cycle" class="input-field w-full">
        <option value="monthly" <?= ($existing['billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Mensal</option>
        <option value="yearly"  <?= ($existing['billing_cycle'] ?? '') === 'yearly'  ? 'selected' : '' ?>>Anual</option>
      </select>
    </div>
    <div>
      <label class="label-field">Status</label>
      <select name="status" class="input-field w-full">
        <?php
        $statuses = ['active' => 'Ativa', 'trialing' => 'Trial', 'suspended' => 'Suspensa', 'cancelled' => 'Cancelada', 'past_due' => 'Atrasada'];
        foreach ($statuses as $val => $label):
        ?>
        <option value="<?= $val ?>" <?= ($existing['status'] ?? 'trialing') === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <?php if ($existing): ?>
  <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4 space-y-1.5">
    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Informações atuais</p>
    <div class="flex justify-between text-xs">
      <span class="text-gray-500">Assinatura ID</span>
      <span class="text-gray-300">#<?= $existing['id'] ?></span>
    </div>
    <?php if ($existing['current_period_start']): ?>
    <div class="flex justify-between text-xs">
      <span class="text-gray-500">Período atual</span>
      <span class="text-gray-300">
        <?= date('d/m/Y', strtotime($existing['current_period_start'])) ?>
        <?php if ($existing['current_period_end']): ?>
        → <?= date('d/m/Y', strtotime($existing['current_period_end'])) ?>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
    <?php if ($existing['created_at']): ?>
    <div class="flex justify-between text-xs">
      <span class="text-gray-500">Criada em</span>
      <span class="text-gray-300"><?= date('d/m/Y', strtotime($existing['created_at'])) ?></span>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="flex items-center justify-between pt-2">
    <a href="/admin/assinaturas" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
    <button type="submit" class="btn-primary text-sm px-6 py-2">Salvar</button>
  </div>
</form>

<?php view_end(); ?>
