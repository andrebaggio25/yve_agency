<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-4xl mx-auto">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <a href="/clientes" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <?= t('clients.title') ?>
      </a>
      <div class="flex items-center gap-4">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/10 text-xl font-bold text-brand-300">
          <?= strtoupper(substr($client['name'], 0, 2)) ?>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white"><?= e($client['name']) ?></h1>
          <?php if (!empty($client['segment'])): ?>
          <p class="text-sm text-gray-400"><?= e($client['segment']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if (\App\Support\Auth::can('clients.edit')): ?>
    <div class="flex gap-2">
      <a href="/clientes/<?= e($client['id']) ?>/conteudos"
         class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-gray-300 hover:border-white/20 hover:text-white transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
        Conteúdos
      </a>
      <a href="/clientes/<?= e($client['id']) ?>/acesso"
         class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-gray-300 hover:border-white/20 hover:text-white transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        <?= t('clients.manage_access') ?>
      </a>
      <a href="/clientes/<?= e($client['id']) ?>/editar"
         class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <?= t('common.edit') ?>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-8">
    <?php
      $statusCls = $client['status'] === 'active'
        ? 'text-emerald-300 bg-emerald-500/10 border-emerald-500/20'
        : 'text-gray-400 bg-white/5 border-white/5';
    ?>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('clients.status') ?></p>
      <p class="font-semibold <?= $statusCls ?>">
        <?= $client['status'] === 'active' ? t('status.active') : t('status.inactive') ?>
      </p>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('clients.currency') ?></p>
      <p class="font-semibold text-white"><?= e($client['currency_code'] ?? 'BRL') ?></p>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('clients.approval_language') ?></p>
      <p class="font-semibold text-white"><?= e(strtoupper($client['language'] ?? 'pt')) ?></p>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-4">
      <p class="text-xs text-gray-500 mb-1"><?= t('clients.since') ?></p>
      <p class="font-semibold text-white"><?= date_fmt($client['created_at'], 'd/m/Y') ?></p>
    </div>
  </div>

  <!-- Pasta no Google Drive -->
  <?php if (\App\Support\Auth::can('clients.edit')): ?>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-5 mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
      <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-300 flex-shrink-0">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
      </span>
      <div>
        <p class="text-sm font-semibold text-white">Pasta no Google Drive</p>
        <?php
          $hasFolder = !empty($client['drive_folder_id']);
          $folderOk  = $hasFolder && !empty($driveFolderOk);
        ?>
        <?php if (empty($driveConnected)): ?>
        <p class="text-xs text-amber-400">Google Drive não conectado — conecte em Integrações.</p>
        <?php elseif ($folderOk): ?>
        <p class="text-xs text-emerald-400">Pasta criada ✓</p>
        <?php elseif ($hasFolder): ?>
        <p class="text-xs text-rose-400">Pasta não encontrada no Drive (apagada?) — recrie ao lado.</p>
        <?php else: ?>
        <p class="text-xs text-gray-400">Este cliente ainda não possui pasta criada.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($driveConnected)): ?>
    <form action="/clientes/<?= e($client['id']) ?>/drive/pasta" method="POST"
          <?= $folderOk ? "onsubmit=\"return confirm('Recriar a pasta deste cliente no Drive? O vínculo atual será substituído.')\"" : '' ?>>
      <?= csrf_field() ?>
      <?php if ($hasFolder): ?>
      <input type="hidden" name="force" value="1">
      <button type="submit" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-gray-300 hover:text-white hover:border-brand-500/40 transition-all">Recriar pasta</button>
      <?php else: ?>
      <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-500 transition-all">Criar pasta</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($recentPlans)): ?>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4"><?= t('content.recent_plans') ?></h2>
    <div class="space-y-3">
      <?php foreach ($recentPlans as $plan): ?>
      <a href="/conteudo/<?= e($plan['id']) ?>"
         class="flex items-center justify-between rounded-xl border border-white/5 bg-white/[0.02] px-4 py-3 hover:border-brand-500/20 transition-all">
        <div>
          <p class="text-sm font-medium text-white"><?= e($plan['title']) ?></p>
          <p class="text-xs text-gray-500"><?= date_fmt($plan['week_start'], 'd/m') ?> – <?= date_fmt($plan['week_end'], 'd/m/Y') ?></p>
        </div>
        <?php
          $colors = ['draft'=>'text-gray-400','sent'=>'text-blue-300','approved'=>'text-emerald-300','revision'=>'text-amber-300'];
          $cls = $colors[$plan['status']] ?? 'text-gray-400';
        ?>
        <span class="text-xs font-semibold <?= $cls ?>"><?= \App\Services\ContentPlanService::statusLabel($plan['status']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Portal do Cliente -->
<div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 mt-6">
  <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-4">Portal do Cliente</h2>
  <?php if ($client['portal_token']): ?>
  <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
    <div class="flex-1 min-w-0">
      <p class="text-xs text-gray-500 mb-1">Link de acesso</p>
      <div class="flex items-center gap-2">
        <code class="text-xs text-brand-300 bg-brand-500/10 px-3 py-1.5 rounded-lg truncate max-w-xs font-mono">
          <?= rtrim(env('APP_URL', ''), '/') ?>/portal/<?= e($client['portal_token']) ?>
        </code>
        <button onclick="navigator.clipboard.writeText('<?= rtrim(env('APP_URL', ''), '/') ?>/portal/<?= e($client['portal_token']) ?>'); this.textContent='✓'"
                class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded border border-white/10 hover:border-white/20 transition-colors flex-shrink-0">
          Copiar
        </button>
        <a href="<?= rtrim(env('APP_URL', ''), '/') ?>/portal/<?= e($client['portal_token']) ?>"
           target="_blank"
           class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded border border-white/10 hover:border-white/20 transition-colors flex-shrink-0">
          Abrir
        </a>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="text-xs <?= $client['portal_enabled'] ? 'text-green-400' : 'text-gray-500' ?>">
        <?= $client['portal_enabled'] ? 'Ativo' : 'Desativado' ?>
      </span>
    </div>
  </div>
  <div class="flex gap-2 flex-wrap">
    <form method="POST" action="/clientes/<?= $client['id'] ?>/portal/toggle">
      <?= csrf_field() ?>
      <button class="text-xs px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:border-white/20 transition-colors">
        <?= $client['portal_enabled'] ? 'Desativar portal' : 'Ativar portal' ?>
      </button>
    </form>
    <form method="POST" action="/clientes/<?= $client['id'] ?>/portal/regenerar"
          onsubmit="return confirm('Regenerar o link invalida o link anterior. Continuar?')">
      <?= csrf_field() ?>
      <button class="text-xs px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:border-white/20 transition-colors">
        Gerar novo link
      </button>
    </form>
  </div>
  <?php else: ?>
  <p class="text-sm text-gray-500">Nenhum link gerado ainda.</p>
  <form method="POST" action="/clientes/<?= $client['id'] ?>/portal/regenerar" class="mt-3">
    <?= csrf_field() ?>
    <button class="text-sm text-brand-400 hover:text-brand-300">Gerar link de acesso</button>
  </form>
  <?php endif; ?>
</div>

<?php view_end(); ?>
