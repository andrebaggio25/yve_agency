<?php view_layout('admin'); view_start('title'); ?>Migrations<?php view_end(); ?>
<?php view_start('breadcrumb'); ?>Migrations<?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$env        = $status['env'] ?? 'production';
$migrations = $status['migrations'] ?? [];
$pending    = $status['pending'] ?? 0;
$total      = count($migrations);
?>

<div class="max-w-4xl">
  <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold text-white">Migrations do banco</h1>
      <p class="mt-1 text-sm text-gray-400">
        Rode o schema direto pelo painel — conecta no mesmo banco (Supabase) que a aplicação usa.
      </p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="badge bg-white/5 text-gray-300 border border-white/10">Ambiente: <?= e($env) ?></span>
      <?php if ($pending > 0): ?>
        <span class="badge bg-amber-500/15 text-amber-300"><?= (int) $pending ?> pendente(s)</span>
      <?php else: ?>
        <span class="badge bg-emerald-500/15 text-emerald-300">Atualizado</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Aviso -->
  <div class="rounded-xl border border-amber-500/20 bg-amber-500/[0.06] px-4 py-3 mb-6 text-sm text-amber-200/90 flex gap-3">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    <div>
      Operação sensível. Recomenda-se um <strong>backup do banco</strong> antes de rodar em produção.
      O rollback reverte a última migration e pode remover dados.
    </div>
  </div>

  <!-- Ações -->
  <div class="flex items-center gap-3 mb-6 flex-wrap">
    <form method="POST" action="/admin/migrations/run"
          onsubmit="return confirm('Rodar as migrations pendentes agora?');">
      <?= csrf_field() ?>
      <button type="submit" class="btn-primary text-sm px-5 py-2.5 gap-2 <?= $pending === 0 ? 'opacity-50 pointer-events-none' : '' ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg>
        Rodar pendentes<?= $pending > 0 ? ' (' . (int) $pending . ')' : '' ?>
      </button>
    </form>

    <!-- Rollback: ação irreversível (pode apagar colunas/tabelas com dado de
         cliente). O servidor exige a palavra digitada — o confirm() do navegador
         sozinho é fácil demais de clicar sem ler (ADM-01). -->
    <form method="POST" action="/admin/migrations/rollback" class="flex items-center gap-2"
          onsubmit="return confirm('Reverter a ÚLTIMA migration aplicada? Isso pode APAGAR DADOS e não tem desfazer. Você tem backup?');">
      <?= csrf_field() ?>
      <input aria-label="Digite REVERTER" type="text" name="confirmation" placeholder="Digite REVERTER"
             autocomplete="off" spellcheck="false"
             class="input-field w-40 text-sm py-2 uppercase placeholder:normal-case">
      <button type="submit" class="btn-secondary text-sm px-4 py-2.5 gap-2 text-rose-300 hover:text-rose-200">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 010 10h-2M3 10l4-4M3 10l4 4"/></svg>
        Reverter última
      </button>
    </form>
  </div>

  <!-- Log da última execução -->
  <?php if (!empty($log)): ?>
  <div class="rounded-xl border border-white/10 bg-black/40 mb-6 overflow-hidden">
    <div class="px-4 py-2 border-b border-white/5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Saída da última execução</div>
    <pre class="p-4 text-xs text-gray-300 overflow-x-auto whitespace-pre-wrap leading-relaxed"><?= e($log) ?></pre>
  </div>
  <?php endif; ?>

  <!-- Lista de migrations -->
  <div class="card overflow-hidden">
    <div class="px-4 py-3 border-b border-white/[0.06] flex items-center justify-between">
      <p class="text-sm font-semibold text-white">Migrations (<?= (int) $total ?>)</p>
    </div>
    <?php if (empty($migrations)): ?>
      <p class="px-4 py-8 text-center text-sm text-gray-400">Nenhuma migration encontrada.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-400 border-b border-white/[0.06]">
            <th class="px-4 py-2 font-medium">Versão</th>
            <th class="px-4 py-2 font-medium">Migration</th>
            <th class="px-4 py-2 font-medium">Estado</th>
            <th class="px-4 py-2 font-medium">Executada em</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($migrations as $mig): ?>
          <tr class="border-b border-white/[0.03] last:border-0">
            <td class="px-4 py-2.5 font-mono text-xs text-gray-400"><?= e($mig['version']) ?></td>
            <td class="px-4 py-2.5 text-gray-200"><?= e($mig['name']) ?></td>
            <td class="px-4 py-2.5">
              <?php if ($mig['executed']): ?>
                <span class="badge bg-emerald-500/15 text-emerald-300">Executada</span>
              <?php else: ?>
                <span class="badge bg-amber-500/15 text-amber-300">Pendente</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-400"><?= $mig['executed_at'] ? e((string) $mig['executed_at']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php view_end(); ?>
