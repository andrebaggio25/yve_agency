<?php view_layout('admin'); view_start('title'); ?>Usuários<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Usuários<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Usuários</h1>
    <p class="text-sm text-gray-400 mt-0.5"><?= count($users) ?> usuário(s) encontrado(s)</p>
  </div>
  <a href="/admin/usuarios/novo" class="btn-primary text-sm px-4 py-2">+ Novo usuário</a>
</div>

<!-- Filters -->
<form method="GET" action="/admin/usuarios" class="flex items-center gap-3 mb-6">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome ou e-mail..."
         class="input-field flex-1 max-w-sm">
  <select name="agency_id" class="input-field w-48">
    <option value="">Todos os tenants</option>
    <?php foreach ($agencies as $a): ?>
    <option value="<?= $a['id'] ?>" <?= $agencyId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary text-sm px-4 py-2">Filtrar</button>
</form>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Usuário</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Tenant</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Perfil</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Criado em</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
    <?php foreach ($users as $u): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3.5">
          <p class="font-medium text-white"><?= e($u['name']) ?></p>
          <p class="text-xs text-gray-500"><?= e($u['email']) ?></p>
        </td>
        <td class="px-5 py-3.5">
          <a href="/admin/tenants/<?= $u['agency_id'] ?>/editar" class="text-gray-300 hover:text-white transition-colors text-sm">
            <?= e($u['agency_name'] ?? '—') ?>
          </a>
        </td>
        <td class="px-5 py-3.5 text-gray-400 text-xs"><?= e($u['roles'] ?: '—') ?></td>
        <td class="px-5 py-3.5 text-center">
          <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
            <?= $u['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' ?>">
            <?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
          </span>
        </td>
        <td class="px-5 py-3.5 text-gray-500 text-xs"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td class="px-5 py-3.5 text-right">
          <a href="/admin/usuarios/<?= $u['id'] ?>/editar"
             class="text-xs text-gray-500 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition-all">
            Editar
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
      <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Nenhum usuário encontrado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php view_end(); ?>
