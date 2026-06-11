<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto">

  <div class="mb-8">
    <a href="/conteudo/<?= e($plan['id']) ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Voltar ao plano
    </a>
    <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-1">Conteúdo</p>
    <h1 class="text-2xl font-bold text-white">Editar Plano</h1>
  </div>

  <?php if ($err = flash('error')): ?>
  <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300"><?= e($err) ?></div>
  <?php endif; ?>

  <form method="POST" action="/conteudo/<?= e($plan['id']) ?>" class="space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold text-white/80 uppercase tracking-wide">Informações do Plano</h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Cliente</label>
        <select name="client_id" disabled
                class="w-full rounded-xl bg-white/[0.02] border border-white/5 text-gray-400 px-4 py-3 text-sm cursor-not-allowed">
          <?php foreach ($clientList as $c): ?>
          <option value="<?= e($c['id']) ?>" <?= $c['id'] == $plan['client_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-600">O cliente não pode ser alterado após a criação.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Título do Plano <span class="text-rose-400">*</span>
        </label>
        <input type="text" name="title" value="<?= e($plan['title']) ?>" required
               class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            Início da Semana <span class="text-rose-400">*</span>
          </label>
          <input type="date" name="week_start" value="<?= e($plan['week_start'] ?? '') ?>" required
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-colors">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Fim da Semana</label>
          <input type="date" name="week_end" value="<?= e($plan['week_end'] ?? '') ?>"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-colors">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Notas Internas</label>
        <textarea name="notes" rows="3"
                  placeholder="Observações para a equipe..."
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 focus:border-violet-500/50 transition-colors resize-none"><?= e($plan['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button type="submit"
              class="flex-1 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 transition-all hover:bg-violet-500 hover:scale-[1.01] active:scale-95">
        Salvar alterações
      </button>
      <a href="/conteudo/<?= e($plan['id']) ?>"
         class="rounded-xl border border-white/10 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:border-white/20 transition-all">
        Cancelar
      </a>
    </div>
  </form>
</div>

<?php view_end(); ?>
