<?php view_layout('admin'); view_start('title'); ?>Configurações Globais<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Configurações<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-8">
  <p class="text-xs font-semibold uppercase tracking-widest text-red-500 mb-1">Platform Admin</p>
  <h1 class="text-2xl font-bold text-white">Configurações Globais</h1>
  <p class="mt-1 text-sm text-gray-400">Credenciais compartilhadas por todos os tenants.</p>
</div>

<form action="/admin/configuracoes" method="POST" class="space-y-6">
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="POST">

  <!-- Evolution API -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h2 class="text-sm font-semibold text-white">Evolution API — WhatsApp</h2>
        <p class="text-xs text-gray-500 mt-0.5">Credenciais globais. Cada tenant terá sua própria instância.</p>
      </div>
      <button type="button" id="btnTestEvo"
              class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Testar conexão
      </button>
    </div>
    <div id="evoTestResult" class="hidden mb-4 rounded-xl px-4 py-2.5 text-sm"></div>

    <div class="space-y-4">
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="evolution_enabled" value="1"
                 <?= ($allSettings['evolution_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>
                 class="w-4 h-4 rounded border-white/20 bg-white/5 text-red-500">
          <span class="text-sm text-gray-300">WhatsApp habilitado</span>
        </label>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">URL da Evolution API</label>
        <input type="url" name="evolution_api_url"
               value="<?= e($allSettings['evolution_api_url'] ?? '') ?>"
               placeholder="https://sua-evolution-api.com"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">API Key Global</label>
        <input type="password" name="evolution_api_key"
               value=""
               placeholder="<?= !empty($allSettings['evolution_api_key']) ? '••••••••' : 'Cole a chave aqui' ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 transition-colors">
        <p class="text-xs text-gray-600 mt-1">Deixe em branco para manter a chave atual.</p>
      </div>
    </div>
  </div>

  <!-- E-mail / SMTP -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-5">SMTP — E-mail transacional</h2>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Host SMTP</label>
        <input type="text" name="mail_host" value="<?= e($allSettings['mail_host'] ?? '') ?>"
               placeholder="smtp.exemplo.com"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Porta</label>
        <input type="number" name="mail_port" value="<?= e($allSettings['mail_port'] ?? '587') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Usuário SMTP</label>
        <input type="text" name="mail_username" value="<?= e($allSettings['mail_username'] ?? '') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Criptografia</label>
        <select name="mail_encryption" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
          <?php foreach (['tls'=>'TLS','ssl'=>'SSL','none'=>'Nenhuma'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($allSettings['mail_encryption'] ?? 'tls') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Remetente (e-mail)</label>
        <input type="email" name="mail_from_address" value="<?= e($allSettings['mail_from_address'] ?? '') ?>"
               placeholder="no-reply@yveagency.com"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Remetente (nome)</label>
        <input type="text" name="mail_from_name" value="<?= e($allSettings['mail_from_name'] ?? '') ?>"
               placeholder="YVE Agency"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
      </div>
    </div>
  </div>

  <!-- Meta Ads API -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-1">Meta Ads API</h2>
    <p class="text-xs text-gray-500 mb-5">Credenciais do aplicativo Meta usadas para trocar tokens de usuário por tokens de longa duração.</p>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">App ID</label>
        <input type="text" name="meta_app_id" value="<?= e($allSettings['meta_app_id'] ?? '') ?>"
               placeholder="123456789"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">App Secret</label>
        <input type="password" name="meta_app_secret" value=""
               placeholder="<?= !empty($allSettings['meta_app_secret']) ? '••••••••' : 'Cole o secret aqui' ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
        <p class="text-xs text-gray-600 mt-1">Deixe em branco para manter o valor atual.</p>
      </div>
    </div>
  </div>

  <!-- IA / LLM -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-1">Inteligência Artificial</h2>
    <p class="text-xs text-gray-500 mb-5">Provedor e chave de API para geração de insights e recomendações de tráfego.</p>
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Provedor *</label>
          <select name="ai_provider" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white focus:border-red-500 focus:outline-none transition-colors">
            <option value="openai" <?= ($allSettings['ai_provider'] ?? 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
            <option value="claude" <?= ($allSettings['ai_provider'] ?? '') === 'claude' ? 'selected' : '' ?>>Claude (Anthropic)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5">Modelo</label>
          <input type="text" name="ai_model" value="<?= e($allSettings['ai_model'] ?? '') ?>"
                 placeholder="gpt-4o ou claude-sonnet-4-6"
                 class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">OpenAI API Key</label>
        <input type="password" name="openai_api_key" value=""
               placeholder="<?= !empty($allSettings['openai_api_key']) ? '••••••••' : 'sk-...' ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
        <p class="text-xs text-gray-600 mt-1">Deixe em branco para manter o valor atual.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Anthropic API Key</label>
        <input type="password" name="anthropic_api_key" value=""
               placeholder="<?= !empty($allSettings['anthropic_api_key']) ? '••••••••' : 'sk-ant-...' ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-red-500 focus:outline-none transition-colors">
        <p class="text-xs text-gray-600 mt-1">Deixe em branco para manter o valor atual.</p>
      </div>
    </div>
  </div>

  <!-- WhatsApp instances summary -->
  <?php if (!empty($instances)): ?>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold text-white mb-4">Instâncias WhatsApp ativas (<?= count($instances) ?>)</h2>
    <div class="space-y-2">
      <?php foreach ($instances as $inst): ?>
      <div class="flex items-center justify-between rounded-xl px-4 py-2.5 bg-white/[0.02]">
        <div>
          <p class="text-sm text-white"><?= e($inst['agency_name']) ?></p>
          <code class="text-xs text-gray-500"><?= e($inst['instance_name']) ?></code>
        </div>
        <span class="text-xs px-2.5 py-1 rounded-full <?= $inst['status'] === 'connected' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' ?>">
          <?= $inst['status'] === 'connected' ? 'Conectado' : 'Desconectado' ?>
          <?php if ($inst['phone_number']): ?> · <?= e($inst['phone_number']) ?><?php endif; ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="flex justify-end">
    <button type="submit"
            class="rounded-xl bg-red-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/20 hover:bg-red-500 transition-all hover:scale-105 active:scale-95">
      Salvar configurações
    </button>
  </div>
</form>

<?php view_end(); ?>

<?php view_start('scripts'); ?>
<script>
document.getElementById('btnTestEvo')?.addEventListener('click', async () => {
  const el = document.getElementById('evoTestResult');
  el.className = 'mb-4 rounded-xl px-4 py-2.5 text-sm bg-white/5 text-gray-400';
  el.textContent = 'Testando...';
  el.classList.remove('hidden');
  try {
    const r = await fetch('/admin/configuracoes/test-evolution');
    const d = await r.json();
    if (d.ok) {
      el.className = 'mb-4 rounded-xl px-4 py-2.5 text-sm bg-emerald-500/10 text-emerald-300';
      el.textContent = '✓ Conexão OK (HTTP ' + d.http + ')';
    } else {
      el.className = 'mb-4 rounded-xl px-4 py-2.5 text-sm bg-red-500/10 text-red-300';
      el.textContent = '✗ Falha na conexão (HTTP ' + d.http + ')';
    }
  } catch { el.textContent = '✗ Erro de rede.'; }
});
</script>
<?php view_end(); ?>
