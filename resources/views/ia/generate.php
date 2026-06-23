<?php view_layout('app'); view_start('title'); ?>Gerar Insight IA<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-lg mx-auto">
  <div class="mb-6">
    <h1 class="text-xl font-semibold text-white">Gerar insight com IA</h1>
    <p class="text-sm text-gray-400 mt-1">A IA analisará as métricas da conta no período selecionado.</p>
  </div>

  <form method="POST" action="/ia/gerar" class="card p-6 space-y-5">
    <?= csrf_field() ?>

    <div>
      <label class="label-field">Conta de anúncios *</label>
      <select name="ad_account_id" required class="input-field w-full">
        <option value="">Selecione…</option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= $a['id'] ?>">
          <?= e($a['name']) ?>
          <?= $a['client_name'] ? '(' . e($a['client_name']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="label-field">Tipo de análise *</label>
      <select name="type" class="input-field w-full">
        <option value="performance_summary">Resumo de performance</option>
        <option value="recommendation">Recomendações de otimização</option>
        <option value="alert">Alertas de atenção</option>
        <option value="report">Relatório executivo</option>
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Período — De</label>
        <input type="date" name="since" value="<?= date('Y-m-d', strtotime('-30 days')) ?>"
               class="input-field w-full">
      </div>
      <div>
        <label class="label-field">Até</label>
        <input type="date" name="until" value="<?= date('Y-m-d') ?>"
               class="input-field w-full">
      </div>
    </div>

    <?php if (empty($accounts)): ?>
    <div class="rounded-xl bg-yellow-500/10 border border-yellow-500/20 px-4 py-3 text-sm text-yellow-300">
      Nenhuma conta de anúncios conectada.
      <a href="/trafego/contas/nova" class="underline hover:text-yellow-200">Conectar agora</a>
    </div>
    <?php endif; ?>

    <?php if (empty($aiConfigured)): ?>
    <div class="rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-3 text-sm text-amber-300">
      A IA ainda não foi configurada. Peça ao administrador da plataforma para definir o provedor e a chave de API em
      <a href="/admin/configuracoes" class="underline hover:text-amber-200">Configurações globais</a>.
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-end gap-3 pt-2">
      <a href="/ia" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" <?= (empty($accounts) || empty($aiConfigured)) ? 'disabled' : '' ?>
              class="btn-primary text-sm px-6 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
        Gerar insight
      </button>
    </div>
  </form>

  <div class="mt-4 rounded-xl bg-white/[0.03] border border-white/[0.06] px-4 py-3 text-xs text-gray-500">
    <p class="font-medium text-gray-400 mb-1">Sobre o uso de IA</p>
    <p>O provedor e modelo são configurados em <a href="/admin/configuracoes" class="text-violet-400 hover:underline">Configurações globais</a>. Suporta OpenAI (gpt-4o) e Claude (claude-sonnet-4-6).</p>
  </div>
</div>

<?php view_end(); ?>
