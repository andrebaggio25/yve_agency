<?php view_layout('app'); view_start('title'); ?>Conectar Conta de Anúncios<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg mx-auto">
  <div class="mb-6">
    <h1 class="text-xl font-semibold text-white">Conectar conta Meta Ads</h1>
    <p class="text-sm text-gray-400 mt-1">Conecte via OAuth (recomendado) ou insira o token manualmente.</p>
  </div>

  <?php flash_messages(); ?>

  <!-- OAuth (recomendado) -->
  <div class="card p-5 mb-5 border border-violet-500/20 bg-violet-500/5">
    <div class="flex items-start gap-4">
      <div class="w-10 h-10 rounded-xl bg-[#1877f2] flex items-center justify-center flex-shrink-0 mt-0.5">
        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm font-semibold text-white mb-1">Conectar via Facebook Login <span class="text-xs text-violet-400 font-medium">(recomendado)</span></p>
        <p class="text-xs text-gray-500 mb-3">Autorize automaticamente. Mais seguro e sem precisar copiar tokens.</p>
        <?php if ($metaAppConfigured ?? false): ?>
        <a href="/trafego/contas/oauth?client_id=<?= (int)($clients[0]['id'] ?? 0) ?>"
           class="inline-flex items-center gap-2 bg-[#1877f2] hover:bg-[#166fe5] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          Continuar com Facebook
        </a>
        <?php else: ?>
        <p class="text-xs text-yellow-400">⚠️ Meta App ID não configurado. O administrador precisa configurar em <a href="/admin/configuracoes" class="underline hover:text-yellow-300">Admin → Configurações</a>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="flex items-center gap-3 mb-5">
    <div class="flex-1 h-px bg-white/[0.06]"></div>
    <span class="text-xs text-gray-500 font-medium">ou insira manualmente</span>
    <div class="flex-1 h-px bg-white/[0.06]"></div>
  </div>

  <!-- Instruções -->
  <div class="card p-4 mb-6 border border-blue-500/20 bg-blue-500/5">
    <p class="text-xs font-semibold text-blue-300 mb-2">Como obter o token de acesso</p>
    <ol class="text-xs text-gray-400 space-y-1 list-decimal list-inside">
      <li>Acesse o <span class="text-gray-300">Meta Business Suite → Configurações → Tokens do sistema</span></li>
      <li>Gere um token de usuário com permissões <code class="bg-white/[0.07] px-1 rounded">ads_read</code> e <code class="bg-white/[0.07] px-1 rounded">ads_management</code></li>
      <li>O ID da conta está no formato <code class="bg-white/[0.07] px-1 rounded">act_XXXXXXXXX</code> — use apenas os números</li>
    </ol>
  </div>

  <form method="POST" action="/trafego/contas" class="card p-6 space-y-5">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">

    <div>
      <label class="label-field">Token de acesso *</label>
      <input type="text" name="access_token" required
             placeholder="EAABsbCS..."
             class="input-field w-full font-mono text-xs">
      <p class="text-xs text-gray-500 mt-1">O token será trocado por um de longa duração automaticamente.</p>
    </div>

    <div>
      <label class="label-field">ID da conta de anúncios *</label>
      <div class="flex items-center gap-2">
        <span class="text-gray-400 text-sm">act_</span>
        <input type="text" name="platform_account_id" required
               placeholder="123456789"
               class="input-field flex-1">
      </div>
    </div>

    <div>
      <label class="label-field">Cliente (opcional)</label>
      <select name="client_id" class="input-field w-full">
        <option value="">— Sem cliente —</option>
        <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="label-field">Dias de sincronização retroativa</label>
      <input type="number" name="sync_days_back" value="30" min="1" max="365"
             class="input-field w-32">
      <p class="text-xs text-gray-500 mt-1">Quantos dias de histórico buscar na primeira sincronização.</p>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/trafego/contas" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Conectar conta</button>
    </div>
  </form>
</div>

<?php view_end(); ?>
