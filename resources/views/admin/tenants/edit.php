<?php view_layout('admin'); view_start('title'); ?>Editar Tenant<?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/tenants" class="hover:text-white">Tenants</a> / <?= e($agency['name']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-8">
  <div>
    <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Tenant</p>
    <h1 class="text-2xl font-bold text-white"><?= e($agency['name']) ?></h1>
    <p class="text-sm text-gray-400 mt-0.5">Slug: <code class="text-gray-400"><?= e($agency['slug'] ?? '—') ?></code></p>
  </div>
  <form method="POST" action="/admin/tenants/<?= $agency['id'] ?>"
        onsubmit="return confirm('Excluir este tenant? Apenas possível se não houver usuários.')">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-500/30 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      Excluir
    </button>
  </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Edit form -->
  <div class="lg:col-span-2 rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-5">Informações</h2>
    <form action="/admin/tenants/<?= $agency['id'] ?>" method="POST" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="_method" value="PUT">

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Nome <span class="text-red-400">*</span></label>
        <input aria-label="Nome" type="text" name="name" value="<?= e($agency['name']) ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">País</label>
          <select name="country" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
            <?php foreach (['BR'=>'Brasil','PT'=>'Portugal','US'=>'USA','ES'=>'Espanha'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= $agency['country'] === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Moeda</label>
          <select name="currency_code" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
            <?php foreach (['BRL'=>'BRL — Real','USD'=>'USD — Dólar','EUR'=>'EUR — Euro'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= $agency['currency_code'] === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Fuso horário</label>
          <select aria-label="Fuso horário" name="timezone" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
            <?php foreach (['America/Sao_Paulo','America/Manaus','America/New_York','Europe/Lisbon','Europe/Madrid','UTC'] as $tz): ?>
            <option value="<?= $tz ?>" <?= $agency['timezone'] === $tz ? 'selected' : '' ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Status</label>
          <select aria-label="Situação" name="status" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
            <option value="active"   <?= $agency['status'] === 'active'   ? 'selected' : '' ?>>Ativo</option>
            <option value="inactive" <?= $agency['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end pt-2">
        <button type="submit"
                class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/20 hover:bg-red-500 transition-all">
          Salvar
        </button>
      </div>
    </form>
  </div>

  <!-- Users list -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-4">Usuários (<?= count($users) ?>)</h2>
    <div class="space-y-2 max-h-96 overflow-y-auto">
      <?php foreach ($users as $u): ?>
      <div class="flex items-center justify-between rounded-xl px-3 py-2 bg-white/[0.02]">
        <div class="min-w-0">
          <p class="text-xs font-medium text-white truncate"><?= e($u['name']) ?></p>
          <p class="text-xs text-gray-400 truncate"><?= e($u['email']) ?></p>
          <?php if ($u['role_name']): ?>
          <p class="text-xs text-gray-400"><?= e($u['role_name']) ?></p>
          <?php endif; ?>
        </div>
        <span class="ml-2 text-xs px-2 py-0.5 rounded-full flex-shrink-0 <?= $u['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' ?>">
          <?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <p class="text-sm text-gray-400 text-center py-4">Nenhum usuário.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php view_end(); ?>
