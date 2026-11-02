<?php view_layout('admin'); view_start('title'); ?>Tenant criado<?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/tenants" class="hover:text-white">Tenants</a> / <?= e($name) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg rounded-2xl border border-white/[0.06] bg-white/[0.04] p-6">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Tenant criado</p>
  <h1 class="text-2xl font-bold text-white mb-4"><?= e($name) ?></h1>
  <p class="text-sm text-gray-300 mb-4">Copie a senha agora. Ela não fica guardada e não aparece de novo.</p>
  <dl class="space-y-3 text-sm">
    <div>
      <dt class="text-gray-400">Admin</dt>
      <dd class="text-white"><?= e($email) ?></dd>
    </div>
    <div>
      <dt class="text-gray-400">Senha</dt>
      <dd><code class="text-white select-all"><?= e($password) ?></code></dd>
    </div>
  </dl>
  <a href="/admin/tenants/<?= (int) $agencyId ?>/editar"
     class="mt-6 inline-flex items-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-accent-fg">Continuar para o tenant</a>
</div>

<?php view_end(); ?>
