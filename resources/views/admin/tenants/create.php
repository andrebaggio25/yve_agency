<?php view_layout('admin'); view_start('title'); ?>Novo Tenant<?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/tenants" class="hover:text-white">Tenants</a> / Novo<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-2xl mb-8">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Tenants</p>
  <h1 class="text-2xl font-bold text-white">Novo tenant</h1>
</div>

<form action="/admin/tenants" method="POST" class="max-w-2xl space-y-6">
  <?= csrf_field() ?>

  <!-- Dados da agência -->
  <div class="card p-6 space-y-4">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">Dados da agência</p>

    <div>
      <label class="label-field">Nome <span class="text-red-400">*</span></label>
      <input type="text" name="name" value="<?= e(old('name')) ?>" required autofocus class="input-field w-full">
      <p class="text-xs text-gray-600 mt-1">O slug será gerado automaticamente.</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">País</label>
        <select name="country" class="input-field w-full">
          <option value="BR">Brasil</option>
          <option value="PT">Portugal</option>
          <option value="US">USA</option>
          <option value="ES">Espanha</option>
        </select>
      </div>
      <div>
        <label class="label-field">Moeda</label>
        <select name="currency_code" class="input-field w-full">
          <option value="BRL">BRL — Real</option>
          <option value="USD">USD — Dólar</option>
          <option value="EUR">EUR — Euro</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Fuso horário</label>
        <select name="timezone" class="input-field w-full">
          <option value="America/Sao_Paulo">America/Sao_Paulo</option>
          <option value="America/Manaus">America/Manaus</option>
          <option value="America/New_York">America/New_York</option>
          <option value="Europe/Lisbon">Europe/Lisbon</option>
          <option value="Europe/Madrid">Europe/Madrid</option>
          <option value="UTC">UTC</option>
        </select>
      </div>
      <div>
        <label class="label-field">Status</label>
        <select name="status" class="input-field w-full">
          <option value="active">Ativo</option>
          <option value="inactive">Inativo</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Super admin do tenant -->
  <div class="card p-6 space-y-4">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">Administrador do tenant</p>
    <p class="text-xs text-gray-500">Será criado automaticamente com o perfil <strong class="text-gray-300">super_admin</strong>.</p>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Nome do admin</label>
        <input type="text" name="admin_name" value="<?= e(old('admin_name', 'Super Admin')) ?>" placeholder="Super Admin" class="input-field w-full">
      </div>
      <div>
        <label class="label-field">E-mail <span class="text-red-400">*</span></label>
        <input type="email" name="admin_email" value="<?= e(old('admin_email')) ?>" required placeholder="admin@empresa.com" class="input-field w-full">
      </div>
    </div>

    <div>
      <label class="label-field">Senha (opcional)</label>
      <input type="text" name="admin_password" value="" placeholder="Deixe em branco para gerar automaticamente" class="input-field w-full font-mono text-sm">
      <p class="text-xs text-gray-600 mt-1">Se vazio, uma senha segura será gerada e exibida após a criação.</p>
    </div>
  </div>

  <!-- Assinatura inicial -->
  <div class="card p-6 space-y-4">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">Assinatura inicial <span class="text-gray-600">(opcional)</span></p>

    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-1">
        <label class="label-field">Plano</label>
        <select name="plan_id" class="input-field w-full">
          <option value="0">Sem plano</option>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?><?= $p['price_monthly'] > 0 ? ' — R$ ' . number_format($p['price_monthly'], 0, ',', '.') . '/mês' : ' (grátis)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label-field">Ciclo</label>
        <select name="billing_cycle" class="input-field w-full">
          <option value="monthly">Mensal</option>
          <option value="yearly">Anual</option>
        </select>
      </div>
      <div>
        <label class="label-field">Status</label>
        <select name="subscription_status" class="input-field w-full">
          <option value="trialing">Trial</option>
          <option value="active">Ativa</option>
        </select>
      </div>
    </div>
  </div>

  <div class="flex items-center justify-between">
    <a href="/admin/tenants" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
    <button type="submit" class="btn-primary text-sm px-6 py-2.5">Criar tenant</button>
  </div>
</form>

<?php view_end(); ?>
