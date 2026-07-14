<?php view_layout('admin'); view_start('title'); ?>Tenants<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Tenants<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-8">
  <div>
    <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Platform Admin</p>
    <h1 class="text-2xl font-bold text-white">Tenants</h1>
  </div>
  <a href="/admin/tenants/criar"
     class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/20 hover:bg-red-500 transition-all hover:scale-105 active:scale-95">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Novo tenant
  </a>
</div>

<div class="rounded-2xl border border-white/5 overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/5 bg-white/[0.02]">
        <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Nome</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Slug</th>
        <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Usuários</th>
        <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Clientes</th>
        <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.04]">
    <?php foreach ($agencies as $agency): ?>
      <tr class="hover:bg-white/[0.03] transition-colors">
        <td class="px-5 py-3.5">
          <div>
            <p class="font-medium text-white"><?= e($agency['name']) ?></p>
            <p class="text-xs text-gray-400"><?= e($agency['country']) ?> · <?= e($agency['currency_code']) ?></p>
          </div>
        </td>
        <td class="px-4 py-3.5">
          <code class="text-xs text-gray-400 bg-white/5 px-2 py-0.5 rounded"><?= e($agency['slug'] ?? '—') ?></code>
        </td>
        <td class="px-4 py-3.5 text-center">
          <span class="text-gray-300"><?= (int)$agency['user_count'] ?></span>
        </td>
        <td class="px-4 py-3.5 text-center">
          <span class="text-gray-300"><?= (int)$agency['client_count'] ?></span>
        </td>
        <td class="px-4 py-3.5 text-center">
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium
            <?= $agency['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $agency['status'] === 'active' ? 'bg-emerald-400' : 'bg-gray-500' ?>"></span>
            <?= $agency['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
          </span>
        </td>
        <td class="px-4 py-3.5 text-right">
          <a href="/admin/tenants/<?= $agency['id'] ?>/editar"
             class="text-xs text-gray-400 hover:text-white transition-colors">Editar →</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($agencies)): ?>
      <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">Nenhum tenant cadastrado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php view_end(); ?>
