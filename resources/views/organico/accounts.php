<?php view_layout('app'); view_start('title'); ?>Contas Orgânicas<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Contas Orgânicas</h1>
    <p class="text-sm text-gray-400 mt-0.5">Instagram e Facebook conectados</p>
  </div>
  <div class="flex gap-3">
    <a href="/organico" class="btn-secondary text-sm px-4 py-2">← Dashboard</a>
    <a href="/organico/conectar" class="btn-primary text-sm px-4 py-2">+ Conectar</a>
  </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card p-12 text-center text-gray-500">Nenhuma conta conectada.</div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conta</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Plataforma</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Seguidores</th>
        <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Posts</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Último sync</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($accounts as $a): ?>
      <?php
        $pc = ['instagram' => 'text-pink-400', 'facebook' => 'text-blue-400'];
        $c  = $pc[$a['platform']] ?? 'text-gray-400';
      ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3">
          <div class="flex items-center gap-3">
            <?php if ($a['profile_picture_url']): ?>
            <img src="<?= e($a['profile_picture_url']) ?>" alt=""
                 class="w-8 h-8 rounded-full object-cover bg-white/5 flex-shrink-0"
                 onerror="this.style.display='none'">
            <?php endif; ?>
            <div>
              <p class="font-medium text-white"><?= e($a['name']) ?></p>
              <?php if ($a['username']): ?>
              <p class="text-xs text-gray-500">@<?= e($a['username']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td class="px-5 py-3 <<?= $c ?> text-sm font-medium"><?= ucfirst($a['platform']) ?></td>
        <td class="px-5 py-3 text-gray-400"><?= e($a['client_name'] ?? '—') ?></td>
        <td class="px-5 py-3 text-right font-medium text-white"><?= number_format((int)$a['followers_count'], 0, ',', '.') ?></td>
        <td class="px-5 py-3 text-right text-gray-400"><?= number_format((int)$a['media_count'], 0, ',', '.') ?></td>
        <td class="px-5 py-3 text-gray-400 text-xs">
          <?= $a['last_synced_at'] ? date('d/m/Y H:i', strtotime($a['last_synced_at'])) : 'Nunca' ?>
        </td>
        <td class="px-5 py-3 text-center">
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
            <?= $a['status'] === 'active' ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400' ?>">
            <?= $a['status'] === 'active' ? 'Ativa' : 'Inativa' ?>
          </span>
        </td>
        <td class="px-5 py-3 text-right">
          <div class="flex items-center justify-end gap-3">
            <a href="/organico/contas/<?= $a['id'] ?>" class="text-xs text-violet-400 hover:text-violet-300">Ver</a>
            <form method="POST" action="/organico/contas/<?= $a['id'] ?>/sync">
              <input type="hidden" name="_token" value="<?= csrf_token() ?>">
              <button class="text-xs text-violet-400 hover:text-violet-300">Sync</button>
            </form>
            <form method="POST" action="/organico/contas/<?= $a['id'] ?>"
                  onsubmit="return confirm('Remover esta conta?')">
              <input type="hidden" name="_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="_method" value="DELETE">
              <button class="text-xs text-red-400 hover:text-red-300">Remover</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php view_end(); ?>
