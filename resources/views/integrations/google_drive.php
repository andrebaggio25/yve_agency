<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-xl mx-auto">
  <div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Configurações</p>
    <h1 class="text-2xl font-bold text-white">Google Drive</h1>
    <p class="mt-1 text-sm text-gray-400">Conecte o Drive da agência para que os clientes enviem conteúdos direto pelas pastas certas — sem precisar de conta Google.</p>
  </div>

  <?php $connected = !empty($integration) && ($integration['status'] ?? '') === 'active'; ?>

  <?php if (!$configured): ?>
  <!-- Credenciais do servidor ausentes ──────────────────────────────────────── -->
  <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 mb-6">
    <p class="text-sm font-semibold text-amber-300 mb-1">Credenciais do Google não configuradas</p>
    <p class="text-xs text-gray-400">Defina <code class="text-amber-300">GOOGLE_CLIENT_ID</code> e <code class="text-amber-300">GOOGLE_CLIENT_SECRET</code> no <code>.env</code> do servidor. No Google Cloud, ative a Drive API, crie uma credencial OAuth (tipo Web) e configure o redirect URI:</p>
    <div class="mt-2 rounded-xl border border-white/[0.06] bg-black/20 px-3 py-2">
      <code class="text-xs text-brand-300 break-all"><?= e(rtrim(env('APP_URL', ''), '/') . '/integrations/google-drive/oauth/callback') ?></code>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($connected): ?>

  <!-- Estado: conectado ───────────────────────────────────────────────────────── -->
  <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 mb-6 flex items-center gap-4">
    <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 shrink-0"></div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-emerald-300">Conectado</p>
      <?php if (!empty($integration['connected_email'])): ?>
      <p class="text-xs text-gray-400 mt-0.5">Conta: <span class="text-gray-300"><?= e($integration['connected_email']) ?></span></p>
      <?php endif; ?>
      <?php if (!empty($integration['root_folder_id'])): ?>
      <p class="text-xs text-gray-400 mt-0.5">Pasta raiz criada no Drive ✓</p>
      <?php endif; ?>
    </div>
    <form action="/integrations/google-drive/disconnect" method="POST" onsubmit="return confirm('Desconectar o Google Drive? Os arquivos já enviados permanecem no Drive.')">
      <?= csrf_field() ?>
      <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">Desconectar</button>
    </form>
  </div>

  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400 mb-3">Como funciona</h2>
    <ul class="space-y-2 text-sm text-gray-400">
      <li class="flex gap-2"><span class="text-brand-400">1.</span> Cada cliente ganha uma pasta própria sob a raiz da agência.</li>
      <li class="flex gap-2"><span class="text-brand-400">2.</span> No portal, o cliente cria subpastas (por dia, modelo etc.) e envia vídeos/fotos.</li>
      <li class="flex gap-2"><span class="text-brand-400">3.</span> Os arquivos caem direto no seu Drive — usando a sua quota, não a do cliente.</li>
      <li class="flex gap-2"><span class="text-brand-400">4.</span> Sua equipe visualiza e baixa tudo aqui no sistema.</li>
    </ul>
  </div>

  <?php else: ?>

  <!-- Estado: não conectado ───────────────────────────────────────────────────── -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 mb-6">
    <div class="flex items-start gap-4 mb-5">
      <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
        <svg class="w-6 h-6" viewBox="0 0 24 24">
          <path fill="#0066da" d="M8.52 3.5l-4.9 8.48h6.13l4.9-8.48z"/>
          <path fill="#00ac47" d="M3.62 11.98L.55 17.3a2 2 0 001.73 1h6.13l-4.9-8.48z"/>
          <path fill="#ea4335" d="M21.45 11.98h-6.13l3.07 5.32a2 2 0 01-1.73 1h6.13a2 2 0 001.73-3l-3.07-3.32z" opacity=".9"/>
          <path fill="#00832d" d="M8.52 3.5h6.96l4.9 8.48h-6.96z" opacity=".0"/>
          <path fill="#2684fc" d="M15.48 3.5H8.52l3.48 6.03L15.48 3.5z" opacity="0"/>
          <path fill="#ffba00" d="M12 9.53L8.52 3.5h6.96L12 9.53z"/>
          <path fill="#ea4335" d="M15.32 11.98H9.75l-1.13 1.96-1.94 3.36a2 2 0 00.27.0h10.16a2 2 0 001.73-1l-1.45-2.5z"/>
          <path fill="#0066da" d="M15.48 3.5l4.9 8.48 1.07-1.86a2 2 0 000-2L18.2 4.5a2 2 0 00-1.73-1z"/>
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-white mb-1">Drive não conectado</p>
        <p class="text-xs text-gray-400">Conecte a conta Google da agência. Você autoriza uma vez; o sistema cria e gerencia as pastas dos clientes automaticamente.</p>
      </div>
    </div>

    <?php if ($configured): ?>
    <a href="/integrations/google-drive/oauth/start"
       class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-gray-800 shadow hover:bg-gray-100 transition-colors">
      <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.76h3.56c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.56-2.76c-.98.66-2.23 1.06-3.72 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0012 23z"/><path fill="#FBBC05" d="M5.84 14.09a6.6 6.6 0 010-4.18V7.07H2.18a11 11 0 000 9.86l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0012 1 11 11 0 002.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
      Conectar Google Drive
    </a>
    <?php else: ?>
    <button disabled class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-5 py-2.5 text-sm font-semibold text-gray-400 cursor-not-allowed">
      Configure as credenciais primeiro
    </button>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>

<?php view_end(); ?>
