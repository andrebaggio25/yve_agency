<?php view_layout('admin'); view_start('title'); ?>Novo Tenant<?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/tenants" class="hover:text-white">Tenants</a> / Novo<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-xl mb-8">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Tenants</p>
  <h1 class="text-2xl font-bold text-white">Novo tenant</h1>
</div>

<div class="max-w-xl rounded-2xl border border-white/5 bg-white/[0.03] p-6">
  <form action="/admin/tenants" method="POST" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Nome <span class="text-red-400">*</span></label>
      <input type="text" name="name" value="<?= e(old('name')) ?>" required autofocus
             class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-colors">
      <p class="text-xs text-gray-600 mt-1">O slug será gerado automaticamente a partir do nome.</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">País</label>
        <select name="country" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
          <option value="BR">Brasil</option>
          <option value="PT">Portugal</option>
          <option value="US">USA</option>
          <option value="ES">Espanha</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Moeda</label>
        <select name="currency_code" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
          <option value="BRL">BRL — Real</option>
          <option value="USD">USD — Dólar</option>
          <option value="EUR">EUR — Euro</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Fuso horário</label>
      <select name="timezone" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
        <option value="America/Sao_Paulo">America/Sao_Paulo</option>
        <option value="America/Manaus">America/Manaus</option>
        <option value="America/New_York">America/New_York</option>
        <option value="Europe/Lisbon">Europe/Lisbon</option>
        <option value="Europe/Madrid">Europe/Madrid</option>
        <option value="UTC">UTC</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1.5">Status</label>
      <select name="status" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
        <option value="active">Ativo</option>
        <option value="inactive">Inativo</option>
      </select>
    </div>

    <div class="flex items-center justify-between pt-2">
      <a href="/admin/tenants" class="text-sm text-gray-400 hover:text-white transition-colors">Cancelar</a>
      <button type="submit"
              class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/20 hover:bg-red-500 transition-all hover:scale-105 active:scale-95">
        Criar tenant
      </button>
    </div>
  </form>
</div>

<?php view_end(); ?>
