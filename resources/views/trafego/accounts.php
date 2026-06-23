<?php view_layout('app'); view_start('title'); ?>Contas de Anúncios<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Contas de Anúncios</h1>
    <p class="text-sm text-gray-400 mt-0.5">Gerencie as contas conectadas ao sistema</p>
  </div>
  <div class="flex gap-3">
    <a href="/trafego" class="btn-secondary text-sm px-4 py-2">← Dashboard</a>
    <a href="/trafego/contas/nova" class="btn-primary text-sm px-4 py-2">+ Conectar conta</a>
  </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card p-12 text-center">
  <p class="text-gray-400 mb-4">Nenhuma conta conectada.</p>
  <a href="/trafego/contas/nova" class="btn-primary px-6 py-2.5 text-sm">Conectar conta Meta Ads</a>
</div>
<?php else: ?>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/[0.06]">
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Conta</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Plataforma</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Moeda</th>
        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Última sync</th>
        <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/[0.03]">
      <?php foreach ($accounts as $a): ?>
      <tr class="hover:bg-white/[0.02] transition-colors">
        <td class="px-5 py-3">
          <p class="font-medium text-white"><?= e($a['name']) ?></p>
          <p class="text-xs text-gray-500"><?= e($a['platform_account_id']) ?></p>
        </td>
        <td class="px-5 py-3">
          <span class="inline-flex items-center gap-1.5 text-gray-300 text-xs">
            <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            Meta Ads
          </span>
        </td>
        <td class="px-5 py-3 text-gray-400"><?= e($a['client_name'] ?? '—') ?></td>
        <td class="px-5 py-3 text-gray-400"><?= e($a['currency']) ?></td>
        <td class="px-5 py-3 text-gray-400 text-xs">
          <?= $a['last_synced_at'] ? date('d/m/Y H:i', strtotime($a['last_synced_at'])) : 'Nunca' ?>
        </td>
        <td class="px-5 py-3 text-center">
          <?php
            $statusStyles = [
              'active'        => ['bg-green-500/15 text-green-400', 'Ativa'],
              'token_expired' => ['bg-amber-500/15 text-amber-400', 'Token expirado'],
            ];
            [$badgeClass, $badgeLabel] = $statusStyles[$a['status']] ?? ['bg-red-500/15 text-red-400', 'Inativa'];
          ?>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
            <?= $badgeLabel ?>
          </span>
        </td>
        <td class="px-5 py-3 text-right">
          <div class="flex items-center justify-end gap-2">
            <?php if ($a['status'] === 'token_expired'): ?>
            <a href="/trafego/contas/oauth?client_id=<?= (int) ($a['client_id'] ?? 0) ?>"
               class="text-xs text-amber-400 hover:text-amber-300 transition-colors font-medium">
              Reconectar
            </a>
            <?php else: ?>
            <form method="POST" action="/trafego/contas/<?= $a['id'] ?>/sync">
              <?= csrf_field() ?>
              <button type="submit" class="text-xs text-violet-400 hover:text-violet-300 transition-colors">
                Sincronizar
              </button>
            </form>
            <?php endif; ?>
            <form method="POST" action="/trafego/contas/<?= $a['id'] ?>"
                  onsubmit="return confirm('Remover esta conta?')">
              <?= csrf_field() ?>
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">
                Remover
              </button>
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
