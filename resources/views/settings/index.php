<?php view_layout('app'); view_start('title'); ?>Configurações<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Configurações da Agência</h1>
  <p class="text-sm text-gray-400 mt-0.5">Informações e preferências da sua agência</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Formulário -->
  <div class="lg:col-span-2">
    <form method="POST" action="/configuracoes" class="space-y-6">
      <input type="hidden" name="_token" value="<?= csrf_token() ?>">

      <!-- Dados da agência -->
      <div class="card p-6">
        <h2 class="text-sm font-semibold text-gray-300 mb-5">Dados da agência</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div class="sm:col-span-2">
            <label class="label-field">Nome da agência *</label>
            <input type="text" name="name" required value="<?= e($agency['name'] ?? '') ?>"
                   class="input-field w-full">
          </div>
          <div>
            <label class="label-field">Razão social</label>
            <input type="text" name="legal_name" value="<?= e($agency['legal_name'] ?? '') ?>"
                   class="input-field w-full">
          </div>
          <div>
            <label class="label-field">CNPJ / Documento</label>
            <input type="text" name="document_number" value="<?= e($agency['document_number'] ?? '') ?>"
                   placeholder="00.000.000/0001-00" class="input-field w-full">
          </div>
          <div>
            <label class="label-field">E-mail</label>
            <input type="email" name="email" value="<?= e($agency['email'] ?? '') ?>"
                   placeholder="contato@agencia.com.br" class="input-field w-full">
          </div>
          <div>
            <label class="label-field">Telefone</label>
            <input type="text" name="phone" value="<?= e($agency['phone'] ?? '') ?>"
                   placeholder="+55 11 99999-9999" class="input-field w-full">
          </div>
          <div class="sm:col-span-2">
            <label class="label-field">Website</label>
            <input type="url" name="website" value="<?= e($agency['website'] ?? '') ?>"
                   placeholder="https://www.agencia.com.br" class="input-field w-full">
          </div>
          <div class="sm:col-span-2">
            <label class="label-field">URL do logotipo</label>
            <input type="url" name="logo_url" value="<?= e($agency['logo_url'] ?? '') ?>"
                   placeholder="https://cdn.agencia.com/logo.png" class="input-field w-full">
            <p class="text-xs text-gray-500 mt-1">URL pública de uma imagem PNG/SVG do logo.</p>
          </div>
        </div>
      </div>

      <!-- Preferências -->
      <div class="card p-6">
        <h2 class="text-sm font-semibold text-gray-300 mb-5">Preferências</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="label-field">Idioma padrão</label>
            <select name="language" class="input-field w-full">
              <option value="pt" <?= ($agency['language'] ?? 'pt') === 'pt' ? 'selected' : '' ?>>Português (BR)</option>
              <option value="en" <?= ($agency['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
              <option value="es" <?= ($agency['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
            </select>
          </div>
          <div>
            <label class="label-field">Fuso horário</label>
            <select name="timezone" class="input-field w-full">
              <?php
              $tzs = [
                'America/Sao_Paulo'   => 'Brasil — São Paulo (GMT-3)',
                'America/Fortaleza'   => 'Brasil — Fortaleza (GMT-3)',
                'America/Manaus'      => 'Brasil — Manaus (GMT-4)',
                'America/Belem'       => 'Brasil — Belém (GMT-3)',
                'America/Cuiaba'      => 'Brasil — Cuiabá (GMT-4)',
                'America/Porto_Velho' => 'Brasil — Porto Velho (GMT-4)',
                'America/Rio_Branco'  => 'Brasil — Rio Branco (GMT-5)',
                'America/Noronha'     => 'Brasil — Fernando de Noronha (GMT-2)',
                'America/Argentina/Buenos_Aires' => 'Argentina — Buenos Aires (GMT-3)',
                'America/Bogota'      => 'Colômbia — Bogotá (GMT-5)',
                'America/Lima'        => 'Peru — Lima (GMT-5)',
                'America/Santiago'    => 'Chile — Santiago (GMT-3)',
                'America/New_York'    => 'EUA — Nova York (GMT-5)',
                'America/Los_Angeles' => 'EUA — Los Angeles (GMT-8)',
                'Europe/Lisbon'       => 'Portugal — Lisboa (GMT+0)',
                'Europe/Madrid'       => 'Espanha — Madri (GMT+1)',
                'UTC'                 => 'UTC',
              ];
              $current = $agency['timezone'] ?? 'America/Sao_Paulo';
              foreach ($tzs as $val => $label): ?>
              <option value="<?= $val ?>" <?= $current === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="btn-primary px-8 py-2.5">Salvar configurações</button>
      </div>
    </form>
  </div>

  <!-- Sidebar: links rápidos -->
  <div class="space-y-4">

    <?php if (!empty($agency['logo_url'])): ?>
    <div class="card p-5 flex items-center gap-4">
      <img src="<?= e($agency['logo_url']) ?>" alt="Logo" class="h-12 w-auto object-contain rounded">
      <p class="text-sm text-gray-400">Logo atual</p>
    </div>
    <?php endif; ?>

    <!-- WhatsApp -->
    <div class="card p-5">
      <h3 class="text-sm font-semibold text-gray-300 mb-3">WhatsApp</h3>
      <p class="text-xs text-gray-500 mb-4">Configure a instância do WhatsApp para notificações aos clientes.</p>
      <a href="/configuracoes/whatsapp" class="btn-secondary text-sm px-4 py-2 w-full block text-center">
        Gerenciar WhatsApp
      </a>
    </div>

    <!-- Usuários e Perfis -->
    <div class="card p-5">
      <h3 class="text-sm font-semibold text-gray-300 mb-3">Equipe</h3>
      <div class="space-y-2">
        <a href="/usuarios" class="flex items-center justify-between text-sm text-gray-400 hover:text-white py-1 transition-colors">
          <span>Usuários</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        <a href="/usuarios/perfis" class="flex items-center justify-between text-sm text-gray-400 hover:text-white py-1 transition-colors">
          <span>Perfis de acesso</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>

    <!-- Assinatura -->
    <div class="card p-5">
      <h3 class="text-sm font-semibold text-gray-300 mb-3">Plano & Assinatura</h3>
      <a href="/assinatura" class="btn-secondary text-sm px-4 py-2 w-full block text-center">
        Ver assinatura
      </a>
    </div>

  </div>
</div>

<?php view_end(); ?>
