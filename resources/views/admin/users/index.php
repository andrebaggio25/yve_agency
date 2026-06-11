<?php view_layout('admin'); view_start('title'); ?>Usuários<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Usuários<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-8">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Platform Admin</p>
  <h1 class="text-2xl font-bold text-white">Todos os Usuários</h1>
</div>

<!-- Filters -->
<form method="GET" action="/admin/usuarios" class="flex items-center gap-3 mb-6">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome ou e-mail..."
         class="flex-1 max-w-sm rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
  <select name="agency_id" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
    <option value="">Todos os tenants</option>
    <?php foreach ($agencies as $a): ?>
    <option value="<?= $a['id'] ?>" <?= $agencyId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="rounded-xl bg-red-600/20 border border-red-500/20 px-4 py-2.5 text-sm text-red-300 hover:bg-red-600/30 transition-colors">
    Filtrar
  </button>
</form>

<div class="rounded-2xl border border-white/5 overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/5 bg-white/[0.02]">
        <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500">Usuário</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500">Tenant</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500">Perfil</th>
        <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500">Status</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500">Criado em</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.04]">
    <?php foreach ($users as $u): ?>
      <tr class="hover:bg-white/[0.03] transition-colors">
        <td class="px-5 py-3.5">
          <p class="font-medium text-white"><?= e($u['name']) ?></p>
          <p class="text-xs text-gray-500"><?= e($u['email']) ?></p>
        </td>
        <td class="px-4 py-3.5">
          <a href="/admin/tenants/<?= $u['agency_id'] ?>/editar" class="text-gray-300 hover:text-white transition-colors">
            <?= e($u['agency_name'] ?? '—') ?>
          </a>
        </td>
        <td class="px-4 py-3.5 text-gray-400 text-xs"><?= e($u['roles'] ?: '—') ?></td>
        <td class="px-4 py-3.5 text-center">
          <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
            <?= $u['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' ?>">
            <?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
          </span>
        </td>
        <td class="px-4 py-3.5 text-gray-500 text-xs"><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
      <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Nenhum usuário encontrado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-xs text-gray-600 mt-3"><?= count($users) ?> usuário(s) encontrado(s)</p>

<?php view_end(); ?>
