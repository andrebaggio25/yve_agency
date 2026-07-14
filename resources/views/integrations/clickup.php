<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-xl mx-auto">
  <div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Configurações</p>
    <h1 class="text-2xl font-bold text-white">ClickUp</h1>
    <p class="mt-1 text-sm text-gray-400">Sincronize tarefas do YVE com seus projetos no ClickUp.</p>
  </div>

  <?php if (!empty($integration) && ($integration['status'] ?? '') === 'active'): ?>

  <!-- Estado: conectado ────────────────────────────────────────────────────── -->
  <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 mb-6 flex items-center gap-4">
    <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 shrink-0"></div>
    <div class="flex-1">
      <p class="text-sm font-semibold text-emerald-300">Conectado</p>
      <p class="text-xs text-gray-500 mt-0.5">Lista padrão: <span class="text-gray-300"><?= e($integration['default_list_id']) ?></span></p>
    </div>
    <form action="/integrations/clickup" method="POST" onsubmit="return confirm('Desativar integração ClickUp?')">
      <?= csrf_field() ?>
      <?= method_field('DELETE') ?>
      <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">Desativar</button>
    </form>
  </div>

  <?php else: ?>

  <!-- Estado: não configurado ─────────────────────────────────────────────── -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 mb-6">
    <div class="flex items-start gap-4">
      <div class="w-10 h-10 rounded-xl bg-[#7B68EE]/10 border border-[#7B68EE]/20 flex items-center justify-center shrink-0">
        <svg class="w-6 h-6 text-[#7B68EE]" viewBox="0 0 24 24" fill="currentColor">
          <path d="M3.53 12.3L6.1 9.74a9.03 9.03 0 006.25 2.59 9.03 9.03 0 006.25-2.59l2.58 2.56A12.44 12.44 0 0112.35 16a12.44 12.44 0 01-8.82-3.7zm8.82-8.8a9.07 9.07 0 00-6.15 2.4L3.6 3.34A12.5 12.5 0 0112.35 0a12.5 12.5 0 018.75 3.34l-2.6 2.56a9.07 9.07 0 00-6.15-2.4zm0 10.14a5.68 5.68 0 01-3.9-1.54l2.58-2.56a2.13 2.13 0 001.32.47c.5 0 .96-.18 1.32-.47l2.58 2.56a5.68 5.68 0 01-3.9 1.54zM12.35 24l-3.46-3.44 3.46-3.44 3.46 3.44z"/>
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-white mb-1">Integração não configurada</p>
        <p class="text-xs text-gray-500">Preencha os dados abaixo para ativar a sincronização bidirecional de tarefas com o ClickUp.</p>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <!-- Formulário de configuração ──────────────────────────────────────────── -->
  <form action="/integrations/clickup" method="POST" class="space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-4">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Conexão</h2>

      <div>
        <label class="label-field">Token Pessoal ClickUp <span class="text-red-400">*</span></label>
        <input type="password" name="api_token"
               placeholder="pk_XXXXXXXXXXXXXXXX"
               value="<?= !empty($integration['api_token']) ? '••••••••' : '' ?>"
               class="input-field"
               autocomplete="off">
        <p class="mt-1 text-xs text-gray-600">Configurações do ClickUp → Apps → API Token. <a href="https://app.clickup.com/settings/apps" target="_blank" class="text-brand-400 hover:text-brand-300">Abrir ClickUp ↗</a></p>
      </div>

      <div>
        <label class="label-field">Workspace ID <span class="text-gray-600 font-normal">(para webhook)</span></label>
        <input type="text" name="workspace_id"
               value="<?= e($integration['workspace_id'] ?? '') ?>"
               placeholder="12345678"
               class="input-field">
        <p class="mt-1 text-xs text-gray-600">Na URL do ClickUp: app.clickup.com/<strong class="text-gray-400">{workspace_id}</strong>/home</p>
      </div>

      <div>
        <label class="label-field">Lista padrão (List ID) <span class="text-red-400">*</span></label>
        <input type="text" name="default_list_id"
               value="<?= e($integration['default_list_id'] ?? '') ?>"
               placeholder="901234567"
               class="input-field">
        <p class="mt-1 text-xs text-gray-600">Abra a lista no ClickUp, copie o ID da URL: .../li/<strong class="text-gray-400">{list_id}</strong></p>
      </div>
    </div>

    <!-- Mapa de status ──────────────────────────────────────────────────────── -->
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-4">
      <div>
        <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Mapa de Status</h2>
        <p class="text-xs text-gray-600 mt-1">Informe o nome exato do status no ClickUp que corresponde a cada status do YVE.</p>
      </div>

      <?php
      $defaultMap = ['todo' => 'to do', 'in_progress' => 'in progress', 'review' => 'review', 'done' => 'complete'];
      $currentMap = $integration['status_map'] ?? $defaultMap;
      if (is_string($currentMap)) $currentMap = json_decode($currentMap, true) ?? $defaultMap;
      $yveLabels  = ['todo' => 'A Fazer', 'in_progress' => 'Em Andamento', 'review' => 'Revisão', 'done' => 'Concluída'];
      ?>

      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($yveLabels as $key => $label): ?>
        <div>
          <label class="label-field"><?= $label ?> (YVE)</label>
          <input type="text" name="map_<?= $key ?>"
                 value="<?= e($currentMap[$key] ?? $defaultMap[$key]) ?>"
                 class="input-field text-sm">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($integration) && ($integration['status'] ?? '') === 'active' && !empty($integration['webhook_token'])): ?>
    <!-- Info do webhook ─────────────────────────────────────────────────────── -->
    <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-5">
      <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Webhook (entrada ClickUp → YVE)</h3>
      <?php
      $appUrl     = env('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
      $webhookUrl = rtrim($appUrl, '/') . '/webhook/clickup/' . $integration['webhook_token'];
      ?>
      <p class="text-xs text-gray-500 mb-2">URL configurada automaticamente ao salvar com Workspace ID:</p>
      <div class="flex items-center gap-2 rounded-xl border border-white/[0.06] bg-black/20 px-3 py-2">
        <code class="text-xs text-brand-300 flex-1 break-all"><?= e($webhookUrl) ?></code>
        <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl) ?>').then(()=>this.textContent='✓').catch(()=>{})"
                class="text-xs text-gray-500 hover:text-white shrink-0 transition-colors">Copiar</button>
      </div>
      <?php if (!empty($integration['webhook_id'])): ?>
      <p class="text-xs text-gray-600 mt-2">Webhook ID: <span class="text-gray-400"><?= e($integration['webhook_id']) ?></span></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="flex justify-end">
      <button type="submit" class="btn-primary">
        <?= !empty($integration) && ($integration['status'] ?? '') === 'active' ? 'Atualizar configuração' : 'Ativar integração' ?>
      </button>
    </div>
  </form>
</div>

<?php view_end(); ?>
