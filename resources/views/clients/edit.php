<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">
  <div class="mb-8">
    <a href="/clientes/<?= e($client['id']) ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= e($client['name']) ?>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= t('clients.edit') ?></h1>
  </div>

  <?php if (has_flash('error')): ?>
  <div class="mb-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
    <?= e(flash('error')) ?>
  </div>
  <?php endif; ?>

  <form id="client-edit-form" action="/clientes/<?= e($client['id']) ?>" method="POST" class="space-y-6">
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500"><?= t('common.basic_info') ?></h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
          <?= t('clients.name') ?> <span class="text-red-400">*</span>
        </label>
        <input type="text" name="name" value="<?= old('name', $client['name']) ?>" required
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.segment') ?></label>
        <input type="text" name="segment" value="<?= old('segment', $client['segment'] ?? '') ?>"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.status') ?></label>
          <select name="status"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
            <option value="active" <?= old('status', $client['status']) === 'active' ? 'selected' : '' ?>><?= t('status.active') ?></option>
            <option value="inactive" <?= old('status', $client['status']) === 'inactive' ? 'selected' : '' ?>><?= t('status.inactive') ?></option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.currency') ?></label>
          <select name="currency_code"
                  class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
            <?php foreach (['BRL' => 'BRL — Real', 'USD' => 'USD — Dollar', 'EUR' => 'EUR — Euro'] as $code => $label): ?>
            <option value="<?= $code ?>" <?= old('currency_code', $client['currency_code'] ?? 'BRL') === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5"><?= t('clients.approval_language') ?></label>
        <select name="language"
                class="w-full rounded-xl border border-white/10 bg-[#0d0d14] px-4 py-2.5 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
          <?php foreach (\App\Core\Lang::supportedLocales() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= old('language', $client['language'] ?? 'pt') === $code ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-500"><?= t('clients.approval_language_hint') ?></p>
      </div>
    </div>

    <!-- Visual do Portal -->
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Visual do Portal</h2>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Logo do cliente (URL)</label>
        <input type="url" name="logo_url" value="<?= old('logo_url', $client['logo_url'] ?? '') ?>"
               placeholder="https://cdn.exemplo.com/logo.png"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
        <p class="mt-1 text-xs text-gray-500">Exibido no portal de aprovação do cliente. URL pública (PNG, SVG, JPG).</p>
        <?php if (!empty($client['logo_url'])): ?>
        <div class="mt-3 flex items-center gap-3">
          <img src="<?= e($client['logo_url']) ?>" alt="Logo atual" class="h-10 w-auto max-w-[120px] object-contain rounded-lg border border-white/10 bg-white/5 p-1">
          <span class="text-xs text-gray-500">Logo atual</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Portal do Cliente -->
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-4" x-data="{enabled: <?= !empty($client['portal_token']) ? 'true' : 'false' ?>}">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Portal do Cliente</h2>

      <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="enable_portal" value="1" x-model="enabled"
               <?= !empty($client['portal_token']) ? 'checked' : '' ?>
               class="w-4 h-4 mt-0.5 rounded accent-violet-500">
        <span>
          <span class="block text-sm text-white">Habilitar portal de aprovação</span>
          <span class="block text-xs text-gray-500 mt-0.5">O cliente acessa planos, faturas e contratos pelo link. Desativar revoga o acesso imediatamente.</span>
        </span>
      </label>

      <div x-show="enabled" x-transition style="display:none">
        <?php if (!empty($client['portal_token'])): ?>
        <?php
          $portalLink = rtrim(env('APP_URL', ''), '/') . '/portal/' . $client['portal_token'];
        ?>
        <div class="flex items-center gap-2 rounded-xl bg-white/[0.03] border border-white/10 px-3 py-2.5">
          <span class="flex-1 text-xs text-gray-400 truncate"><?= e($portalLink) ?></span>
          <button type="button" x-data="{c:false}"
                  @click="navigator.clipboard.writeText('<?= e($portalLink) ?>').then(()=>{c=true;setTimeout(()=>c=false,2000)})"
                  :class="c ? 'text-emerald-400' : 'text-gray-500 hover:text-white'"
                  class="flex-shrink-0 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <template x-if="!c"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></template>
              <template x-if="c"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></template>
            </svg>
          </button>
        </div>
        <?php else: ?>
        <p class="text-xs text-violet-400">Um link de acesso será gerado ao salvar.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-500">Notificações & automações</h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">WhatsApp do cliente</label>
        <input type="text" name="whatsapp" value="<?= old('whatsapp', $client['whatsapp'] ?? '') ?>"
               placeholder="5511999998888"
               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors">
        <p class="mt-1 text-xs text-gray-500">Com DDI + DDD. Necessário para os avisos por WhatsApp.</p>
      </div>

      <div class="flex flex-wrap gap-5">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="notify_whatsapp" value="1" <?= ($client['notify_whatsapp'] ?? true) ? 'checked' : '' ?>
                 class="w-4 h-4 rounded accent-violet-500">
          <span class="text-sm text-gray-300">Permitir avisos por WhatsApp</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="notify_email" value="1" <?= ($client['notify_email'] ?? true) ? 'checked' : '' ?>
                 class="w-4 h-4 rounded accent-violet-500">
          <span class="text-sm text-gray-300">Permitir avisos por e-mail</span>
        </label>
      </div>

      <?php if (!empty($clientAutomations)): ?>
      <div class="pt-2 border-t border-white/[0.06]">
        <p class="text-xs font-medium text-gray-400 mb-1">Automações deste cliente</p>
        <p class="text-xs text-gray-600 mb-3">Só disparam se a automação estiver ativada na agência (em Automações).</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <?php foreach ($clientAutomations as $key => $def): ?>
          <label class="flex items-start gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5 cursor-pointer hover:bg-white/[0.04] transition-colors">
            <input type="checkbox" name="automations[<?= e($key) ?>]" value="1"
                   <?= !empty($clientAutoSettings[$key]) ? 'checked' : '' ?>
                   class="w-4 h-4 mt-0.5 rounded accent-violet-500">
            <span>
              <span class="block text-sm text-white"><?= e($def['label'] ?? $key) ?></span>
              <span class="block text-xs text-gray-600"><?= e($def['description'] ?? '') ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </form>

  <!-- Barra de ações (FORA do form de edição: forms aninhados quebram o submit) -->
  <div class="flex items-center justify-between mt-6">
    <?php if (\App\Support\Auth::can('clients.delete')): ?>
    <?php
      // UX-02: "Excluir" nunca excluiu (soft-delete). A tela agora diz a verdade
      // e mostra o impacto real — o que é preservado e o que é revogado.
      $r        = $related ?? ['invoices' => 0, 'contracts' => 0, 'plans' => 0, 'files' => 0, 'tasks' => 0];
      $preserved = array_filter([
        $r['invoices']  ? $r['invoices']  . ' fatura'  . ($r['invoices']  > 1 ? 's' : '')  : null,
        $r['contracts'] ? $r['contracts'] . ' contrato'. ($r['contracts'] > 1 ? 's' : '') : null,
        $r['plans']     ? $r['plans']     . ' plano'   . ($r['plans']     > 1 ? 's' : '') . ' de conteúdo' : null,
        $r['files']     ? $r['files']     . ' arquivo' . ($r['files']     > 1 ? 's' : '')  : null,
        $r['tasks']     ? $r['tasks']     . ' tarefa'  . ($r['tasks']     > 1 ? 's' : '')  : null,
      ]);
      $isArchived = ($client['status'] ?? '') === 'cancelled';
    ?>

    <div x-data="{ open: false }">
      <?php if ($isArchived): ?>
        <form action="/clientes/<?= e($client['id']) ?>/reativar" method="POST" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="text-sm text-emerald-400 hover:text-emerald-300 transition-colors">
            Reativar cliente
          </button>
        </form>
      <?php else: ?>
        <button type="button" @click="open = true" class="text-sm text-red-400 hover:text-red-300 transition-colors">
          Arquivar cliente
        </button>
      <?php endif; ?>

      <!-- Diálogo informativo: a decisão precisa mostrar a consequência -->
      <div x-show="open" x-cloak x-transition.opacity @keydown.escape.window="open = false" @click.self="open = false"
           class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background:rgba(0,0,0,.7)">
        <div class="w-full max-w-md rounded-2xl bg-[#16161f] border border-white/10 shadow-xl p-6">
          <div class="flex items-start gap-3 mb-4">
            <span class="w-9 h-9 rounded-full bg-amber-500/15 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            </span>
            <div class="min-w-0">
              <p class="text-base font-semibold text-white mb-1">Arquivar <?= e($client['name']) ?>?</p>
              <p class="text-sm text-gray-400">O cliente sai da lista ativa. Nada é apagado.</p>
            </div>
          </div>

          <div class="rounded-xl bg-rose-500/[0.07] border border-rose-500/20 px-4 py-3 mb-3">
            <p class="text-xs font-semibold text-rose-200 mb-1">O acesso é revogado</p>
            <p class="text-xs text-gray-400">O portal deste cliente é desligado — o link dele deixa de funcionar imediatamente.</p>
          </div>

          <?php if ($preserved): ?>
          <div class="rounded-xl bg-emerald-500/[0.07] border border-emerald-500/20 px-4 py-3 mb-5">
            <p class="text-xs font-semibold text-emerald-200 mb-1">O histórico é preservado</p>
            <p class="text-xs text-gray-400">
              <?= e(implode(' · ', $preserved)) ?> — tudo continua disponível, e o cliente pode ser reativado depois.
            </p>
          </div>
          <?php else: ?>
          <p class="text-xs text-gray-500 mb-5">Este cliente ainda não tem faturas, planos ou arquivos vinculados.</p>
          <?php endif; ?>

          <div class="flex justify-end gap-2">
            <button type="button" @click="open = false" class="btn-secondary text-sm px-4 py-2"><?= t('common.cancel') ?></button>
            <form action="/clientes/<?= e($client['id']) ?>" method="POST" class="inline">
              <?= csrf_field() ?>
              <?= method_field('DELETE') ?>
              <button type="submit" class="btn-danger text-sm px-4 py-2">Arquivar</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
    <div class="flex items-center gap-3">
      <a href="/clientes/<?= e($client['id']) ?>" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm text-gray-400 hover:text-white transition-colors">
        <?= t('common.cancel') ?>
      </a>
      <button type="submit" form="client-edit-form"
              class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all hover:scale-105 active:scale-95">
        <?= t('common.save') ?>
      </button>
    </div>
  </div>
</div>

<?php view_end(); ?>
