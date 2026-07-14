<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-2xl mx-auto" x-data="createPlan()">

  <!-- Header -->
  <div class="mb-8">
    <a href="/conteudo" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Voltar
    </a>
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Conteúdo</p>
    <h1 class="text-2xl font-bold text-white">Novo Plano de Conteúdo</h1>
  </div>

  <!-- Flash -->
  <?php if ($err = flash('error')): ?>
  <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300"><?= e($err) ?></div>
  <?php endif; ?>

  <form method="POST" action="/conteudo" class="space-y-6">
    <?= csrf_field() ?>

    <!-- Cliente -->
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 space-y-5">
      <h2 class="text-sm font-semibold text-white/80 uppercase tracking-wide">Informações do Plano</h2>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Cliente <span class="text-rose-400">*</span>
        </label>
        <select name="client_id" required
                class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
          <option value="">Selecione um cliente...</option>
          <?php foreach ($clientList as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= old('client_id') == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Título do Plano <span class="text-rose-400">*</span>
        </label>
        <input type="text" name="title" value="<?= e(old('title')) ?>" required
               placeholder="Ex: Semana 01 — Janeiro 2025"
               class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            Início da Semana <span class="text-rose-400">*</span>
          </label>
          <input type="date" name="week_start" value="<?= e(old('week_start', date('Y-m-d'))) ?>" required
                 x-model="weekStart" @change="updateWeekEnd()"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Fim da Semana</label>
          <input type="date" name="week_end" x-model="weekEnd"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Notas Internas</label>
        <textarea name="notes" rows="3"
                  placeholder="Observações para a equipe..."
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors resize-none"><?= e(old('notes')) ?></textarea>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <button type="submit"
              class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-[1.01] active:scale-95">
        Criar Plano
      </button>
      <a href="/conteudo"
         class="rounded-xl border border-white/10 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:border-white/20 transition-all">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
function createPlan() {
  return {
    weekStart: '<?= date('Y-m-d') ?>',
    weekEnd: '<?= date('Y-m-d', strtotime('+6 days')) ?>',
    updateWeekEnd() {
      if (!this.weekStart) return;
      const d = new Date(this.weekStart + 'T12:00:00');
      d.setDate(d.getDate() + 6);
      this.weekEnd = d.toISOString().split('T')[0];
    }
  }
}
</script>

<?php view_end(); ?>
