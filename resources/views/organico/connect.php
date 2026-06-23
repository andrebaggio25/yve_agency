<?php view_layout('app'); view_start('title'); ?>Conectar Conta Orgânica<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg mx-auto">
  <div class="mb-6">
    <h1 class="text-xl font-semibold text-white">Conectar conta orgânica</h1>
    <p class="text-sm text-gray-400 mt-1">Instagram Business ou Facebook Page.</p>
  </div>

  <!-- Instruções -->
  <div class="card p-4 mb-6 border border-blue-500/20 bg-blue-500/5">
    <p class="text-xs font-semibold text-blue-300 mb-2">Como conectar</p>
    <ol class="text-xs text-gray-400 space-y-1 list-decimal list-inside">
      <li>Gere um <strong class="text-gray-300">User Token</strong> com permissões <code class="bg-white/[0.07] px-1 rounded">pages_read_engagement</code>, <code class="bg-white/[0.07] px-1 rounded">instagram_basic</code>, <code class="bg-white/[0.07] px-1 rounded">instagram_manage_insights</code></li>
      <li>O sistema troca automaticamente por um <strong class="text-gray-300">Page Token de longa duração</strong></li>
      <li>Para Instagram, a página precisa ter uma <strong class="text-gray-300">Conta Business</strong> vinculada</li>
      <li>O ID da página está na URL da página ou em Configurações → Informações gerais</li>
    </ol>
  </div>

  <form method="POST" action="/organico/conectar" class="card p-6 space-y-5">
    <?= csrf_field() ?>

    <div>
      <label class="label-field">Plataforma *</label>
      <select name="platform" class="input-field w-full">
        <option value="instagram">Instagram Business</option>
        <option value="facebook">Facebook Page</option>
      </select>
    </div>

    <div>
      <label class="label-field">ID da página Facebook *</label>
      <input type="text" name="platform_page_id" required placeholder="123456789012345"
             class="input-field w-full">
      <p class="text-xs text-gray-500 mt-1">Necessário mesmo para Instagram (a conta IG deve estar vinculada à página).</p>
    </div>

    <div>
      <label class="label-field">User Access Token *</label>
      <input type="text" name="access_token" required placeholder="EAABsbCS..."
             class="input-field w-full font-mono text-xs">
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
      <label class="label-field">Dias de histórico</label>
      <input type="number" name="sync_days_back" value="30" min="1" max="90"
             class="input-field w-28">
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/organico/contas" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">Conectar conta</button>
    </div>
  </form>
</div>

<?php view_end(); ?>
