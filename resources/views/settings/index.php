<?php view_layout('app'); view_start('title'); ?>Configurações<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-6">
  <h1 class="text-xl font-semibold text-white">Configurações da Agência</h1>
  <p class="text-sm text-gray-400 mt-0.5">Informações e preferências da sua agência</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Formulário -->
  <div class="lg:col-span-2">
    <form method="POST" action="/configuracoes" class="space-y-6" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <!-- Dados da agência -->
      <div class="card p-6">
        <h2 class="text-sm font-semibold text-gray-300 mb-5">Dados da agência</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div class="sm:col-span-2">
            <label class="label-field">Nome da agência *</label>
            <input aria-label="Nome" type="text" name="name" required value="<?= e($agency['name'] ?? '') ?>"
                   class="input-field w-full">
          </div>
          <div>
            <label class="label-field">Razão social</label>
            <input type="text" aria-label="Razão social" name="legal_name" value="<?= e($agency['legal_name'] ?? '') ?>"
                   class="input-field w-full">
          </div>
          <div>
            <label class="label-field">CNPJ / Documento</label>
            <input type="text" name="document_number" value="<?= e($agency['document_number'] ?? '') ?>"
                   placeholder="00.000.000/0001-00" class="input-field w-full">
          </div>
          <div>
            <label class="label-field">E-mail</label>
            <input aria-label="E-mail" type="email" name="email" value="<?= e($agency['email'] ?? '') ?>"
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
          <!-- Upload de arquivo em vez de URL: pedir uma URL obrigava a pessoa a
               hospedar a imagem em outro lugar antes de usar o sistema — e um
               host externo que sai do ar faz o logo sumir do portal da cliente. -->
          <div class="sm:col-span-2">
            <label class="label-field" for="logo_file">Logotipo</label>

            <div class="flex items-center gap-4">
              <?php if (!empty($agency['logo_url'])): ?>
                <span class="h-14 w-14 rounded-xl bg-white/[0.04] border border-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                  <img src="<?= e($agency['logo_url']) ?>" alt="Logo atual" class="max-h-12 max-w-12 object-contain">
                </span>
              <?php endif; ?>

              <div class="min-w-0 flex-1">
                <input type="file" id="logo_file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif"
                       class="block w-full text-sm text-gray-400
                              file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                              file:text-sm file:font-medium file:bg-brand-600 file:text-gray-950
                              hover:file:bg-brand-500 file:cursor-pointer cursor-pointer">
                <p class="text-xs text-gray-400 mt-1.5">PNG, JPG, WEBP ou GIF · até 2 MB. Aparece no portal do cliente.</p>
              </div>
            </div>

            <?php if (!empty($agency['logo_url'])): ?>
              <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-400 cursor-pointer">
                <input aria-label="Remover logotipo" type="checkbox" name="remove_logo" value="1" class="rounded border-white/20 bg-white/5">
                Remover o logotipo atual
              </label>
            <?php endif; ?>
          </div>

          <!-- PROD-06: white-label — a cor da agência, não a nossa -->
          <div class="sm:col-span-2">
            <label class="label-field" for="brand_color">Cor da marca</label>
            <div class="flex items-center gap-3">
              <input type="color" id="brand_color" name="brand_color"
                     value="<?= e($agency['brand_color'] ?? '#c6a15b') ?>"
                     class="h-10 w-14 rounded-lg bg-white/5 border border-white/10 cursor-pointer p-1">
              <div class="min-w-0">
                <p class="text-xs text-gray-400">
                  Usada nos botões e destaques do <strong class="text-gray-400">portal do cliente</strong>.
                  Deixe no padrão se preferir o tema do sistema.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Preferências -->
      <div class="card p-6">
        <h2 class="text-sm font-semibold text-gray-300 mb-5">Preferências</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="label-field">Idioma padrão</label>
            <select aria-label="Idioma" name="language" class="input-field w-full">
              <option value="pt" <?= ($agency['language'] ?? 'pt') === 'pt' ? 'selected' : '' ?>>Português (BR)</option>
              <option value="en" <?= ($agency['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
              <option value="es" <?= ($agency['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
            </select>
          </div>
          <div>
            <label class="label-field">Fuso horário</label>
            <select aria-label="Fuso horário" name="timezone" class="input-field w-full">
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

    <!-- WhatsApp -->
    <div class="card p-5">
      <h3 class="text-sm font-semibold text-gray-300 mb-3">WhatsApp</h3>
      <p class="text-xs text-gray-400 mb-4">Configure a instância do WhatsApp para notificações aos clientes.</p>
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
