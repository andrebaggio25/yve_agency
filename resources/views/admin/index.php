<?php view_layout('admin'); view_start('title'); ?>Platform Admin<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Dashboard<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-8">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Platform Admin</p>
  <h1 class="text-2xl font-bold text-white">Dashboard</h1>
</div>

<?php
  $pdo = \App\Core\Database::connection();
  $tenantCount = (int) $pdo->query("SELECT COUNT(*) FROM agencies")->fetchColumn();
  $userCount   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_platform_admin = FALSE")->fetchColumn();
  $activeWa    = (int) $pdo->query("SELECT COUNT(*) FROM whatsapp_instances WHERE status = 'connected'")->fetchColumn();
  $totalWa     = (int) $pdo->query("SELECT COUNT(*) FROM whatsapp_instances")->fetchColumn();
?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
  <?php
    $stats = [
      ['label' => 'Tenants', 'value' => $tenantCount, 'color' => 'text-red-400', 'bg' => 'bg-red-500/10', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
      ['label' => 'Usuários', 'value' => $userCount,   'color' => 'text-orange-400', 'bg' => 'bg-orange-500/10', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
      ['label' => 'WhatsApp conectados', 'value' => "{$activeWa}/{$totalWa}", 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ];
    foreach ($stats as $s):
  ?>
  <div class="rounded-2xl border border-white/5 <?= $s['bg'] ?> p-5">
    <div class="flex items-center justify-between">
      <p class="text-sm text-gray-400"><?= e($s['label']) ?></p>
      <svg class="w-5 h-5 <?= $s['color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $s['icon'] ?>"/>
      </svg>
    </div>
    <p class="text-3xl font-bold <?= $s['color'] ?> mt-2"><?= e($s['value']) ?></p>
  </div>
  <?php endforeach; ?>
</div>

<div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
  <h2 class="text-sm font-semibold text-white mb-4">Tenants recentes</h2>
  <?php
    $recent = $pdo->query("SELECT id, name, status, created_at FROM agencies ORDER BY created_at DESC LIMIT 8")->fetchAll();
  ?>
  <div class="space-y-2">
  <?php foreach ($recent as $a): ?>
    <a href="/admin/tenants/<?= $a['id'] ?>/editar"
       class="flex items-center justify-between rounded-xl px-4 py-2.5 hover:bg-white/[0.04] transition-colors">
      <span class="text-sm text-white"><?= e($a['name']) ?></span>
      <span class="text-xs px-2 py-0.5 rounded-full <?= $a['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' ?>">
        <?= $a['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
      </span>
    </a>
  <?php endforeach; ?>
  </div>
</div>

<?php view_end(); ?>
