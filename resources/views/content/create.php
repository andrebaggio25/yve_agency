<?php
view_layout('app'); view_start('content');

// A semana do plano é sempre seg–dom: o default já nasce na segunda da
// semana atual, e o JS reencaixa qualquer data escolhida.
$defaultWeekStart = \App\Services\ContentPlanService::mondayOf((string) old('week_start', date('Y-m-d')));
?>

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
        <select aria-label="Cliente" name="client_id" required x-ref="client" @change="syncTitle(); checkTemplate()"
                class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
          <option value="">Selecione um cliente...</option>
          <?php foreach ($clientList as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= old('client_id') == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Modelo semanal do cliente (salvo a partir de um plano bem montado) -->
        <div x-show="template.exists" x-cloak
             class="mt-2 rounded-xl border border-brand-500/20 bg-brand-500/5 px-3 py-2.5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="apply_template" value="1" x-model="template.apply"
                   class="w-4 h-4 rounded accent-brand-500">
            <span class="text-xs text-brand-300"
                  x-text="'Aplicar modelo semanal do cliente (' + template.count + (template.count === 1 ? ' post' : ' posts') + ') — dias, horários e formatos já preenchidos'"></span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Título do Plano</label>
        <input aria-label="Título" type="text" name="title" x-model="title"
               @input="titleTouched = $event.target.value.trim() !== ''; if (!titleTouched) syncTitle()"
               placeholder="Gerado automaticamente: CLIENTE | dd/mm – dd/mm"
               class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
        <p class="text-xs text-gray-400 mt-1.5">Deixe em branco para usar o nome padrão — cliente e período da semana.</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">
            Semana (segunda) <span class="text-rose-400">*</span>
          </label>
          <input aria-label="Semana (segunda-feira)" type="date" name="week_start" required
                 x-model="weekStart" @change="snapWeek()"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Domingo</label>
          <input aria-label="Domingo (derivado)" type="date" disabled :value="weekEnd"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-gray-400 px-4 py-3 text-sm cursor-not-allowed">
        </div>
      </div>

      <div class="rounded-xl border border-brand-500/20 bg-brand-500/5 px-4 py-2.5 text-xs text-brand-300"
           x-text="'Planificação semanal: de segunda ' + fmt(weekStart) + ' a domingo ' + fmt(weekEnd) + '. Qualquer data escolhida encaixa na semana.'"></div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Notas Internas</label>
        <textarea aria-label="Observações para a equipe..." name="notes" rows="3"
                  placeholder="Observações para a equipe..."
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition-colors resize-none"><?= e(old('notes')) ?></textarea>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <button type="submit"
              class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-[1.01] active:scale-95">
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
    weekStart: '<?= e($defaultWeekStart) ?>',
    title: <?= json_encode((string) old('title'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    titleTouched: <?= old('title') ? 'true' : 'false' ?>,
    template: { exists: false, count: 0, apply: true },

    init() { this.syncTitle(); this.checkTemplate(); },

    // O cliente tem modelo semanal salvo? (grade padrão de posts)
    async checkTemplate() {
      const sel = this.$refs.client;
      this.template = { exists: false, count: 0, apply: true };
      if (!sel || !sel.value) return;
      try {
        const d = await api.get(`/conteudo/modelo/${sel.value}`);
        this.template = { exists: !!d.exists, count: d.count || 0, apply: true };
      } catch (e) { /* sem modelo ou falha de rede: o form segue sem o bloco */ }
    },

    get weekEnd() {
      if (!this.weekStart) return '';
      const d = new Date(this.weekStart + 'T12:00:00');
      d.setDate(d.getDate() + 6);
      return d.toISOString().split('T')[0];
    },

    // Encaixa qualquer data na segunda-feira daquela semana (seg–dom sempre).
    snapWeek() {
      if (!this.weekStart) return;
      const d = new Date(this.weekStart + 'T12:00:00');
      d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
      this.weekStart = d.toISOString().split('T')[0];
      this.syncTitle();
    },

    fmt(iso) { return iso ? iso.slice(8, 10) + '/' + iso.slice(5, 7) : ''; },

    // Preview do nome padrão — para de sobrescrever assim que o usuário digita.
    syncTitle() {
      if (this.titleTouched) return;
      const sel  = this.$refs.client;
      const name = sel && sel.value ? sel.selectedOptions[0].text.trim() : '';
      this.title = name ? name.toUpperCase() + ' | ' + this.fmt(this.weekStart) + ' – ' + this.fmt(this.weekEnd) : '';
    }
  }
}
</script>

<?php view_end(); ?>
