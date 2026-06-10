<?php view_layout('app'); view_start('content'); ?>

<div class="min-h-screen" x-data="contentIndex()">

  <!-- ── Page header ──────────────────────────────────────────────────────── -->
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-1">Conteúdo</p>
      <h1 class="text-2xl font-bold text-white">Planos de Conteúdo</h1>
      <p class="mt-1 text-sm text-gray-400"><?= count($plans) ?> plano<?= count($plans) !== 1 ? 's' : '' ?> encontrado<?= count($plans) !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (\App\Support\Auth::can('content.create')): ?>
    <a href="/conteudo/criar"
       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 transition-all hover:bg-violet-500 hover:scale-105 active:scale-95">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Novo Plano
    </a>
    <?php endif; ?>
  </div>

  <!-- ── Filters ───────────────────────────────────────────────────────────── -->
  <div class="mb-6 rounded-2xl border border-white/5 bg-white/[0.03] p-4">
    <form method="GET" action="/conteudo" class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[140px]">
        <label class="block text-xs font-medium text-gray-400 mb-1">Cliente</label>
        <select name="client_id"
                class="w-full rounded-lg bg-white/5 border border-white/10 text-sm text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          <option value="">Todos</option>
          <?php foreach ($clientList as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= ($filters['client_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex-1 min-w-[120px]">
        <label class="block text-xs font-medium text-gray-400 mb-1">Status</label>
        <select name="status"
                class="w-full rounded-lg bg-white/5 border border-white/10 text-sm text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          <option value="">Todos</option>
          <option value="draft"    <?= ($filters['status'] ?? '') === 'draft'    ? 'selected' : '' ?>>Rascunho</option>
          <option value="sent"     <?= ($filters['status'] ?? '') === 'sent'     ? 'selected' : '' ?>>Enviado</option>
          <option value="revision" <?= ($filters['status'] ?? '') === 'revision' ? 'selected' : '' ?>>Em Revisão</option>
          <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Aprovado</option>
        </select>
      </div>
      <div class="flex-1 min-w-[140px]">
        <label class="block text-xs font-medium text-gray-400 mb-1">A partir de</label>
        <input type="date" name="week_start" value="<?= e($filters['week_start'] ?? '') ?>"
               class="w-full rounded-lg bg-white/5 border border-white/10 text-sm text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500/50">
      </div>
      <button type="submit"
              class="rounded-lg bg-violet-600/80 px-4 py-2 text-sm font-medium text-white hover:bg-violet-600 transition-colors">
        Filtrar
      </button>
      <a href="/conteudo" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
        Limpar
      </a>
    </form>
  </div>

  <!-- ── Plans grid ────────────────────────────────────────────────────────── -->
  <?php if (empty($plans)): ?>
  <div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-4 rounded-2xl bg-violet-500/10 p-6">
      <svg class="w-12 h-12 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1">Nenhum plano encontrado</h3>
    <p class="text-gray-400 text-sm mb-6">Crie seu primeiro plano de conteúdo semanal.</p>
    <?php if (\App\Support\Auth::can('content.create')): ?>
    <a href="/conteudo/criar"
       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-500 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Criar Plano
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($plans as $plan):
      $statusColors = [
        'draft'    => ['bg' => 'bg-gray-500/15',   'text' => 'text-gray-300',   'ring' => 'ring-gray-500/30',   'dot' => 'bg-gray-400'],
        'sent'     => ['bg' => 'bg-blue-500/15',   'text' => 'text-blue-300',   'ring' => 'ring-blue-500/30',   'dot' => 'bg-blue-400'],
        'revision' => ['bg' => 'bg-amber-500/15',  'text' => 'text-amber-300',  'ring' => 'ring-amber-500/30',  'dot' => 'bg-amber-400'],
        'approved' => ['bg' => 'bg-emerald-500/15','text' => 'text-emerald-300','ring' => 'ring-emerald-500/30','dot' => 'bg-emerald-400'],
        'rejected' => ['bg' => 'bg-rose-500/15',   'text' => 'text-rose-300',   'ring' => 'ring-rose-500/30',   'dot' => 'bg-rose-400'],
      ];
      $sc        = $statusColors[$plan['status']] ?? $statusColors['draft'];
      $statusLabel = \App\Services\ContentPlanService::statusLabel($plan['status']);
      $total    = (int) $plan['total_items'];
      $approved = (int) $plan['approved_items'];
      $pct      = $total > 0 ? round(($approved / $total) * 100) : 0;
    ?>
    <a href="/conteudo/<?= e($plan['id']) ?>"
       class="group relative flex flex-col rounded-2xl border border-white/5 bg-white/[0.03] p-5 transition-all duration-200 hover:border-violet-500/30 hover:bg-white/[0.06] hover:shadow-lg hover:shadow-violet-500/5 hover:-translate-y-0.5">

      <!-- Status badge -->
      <div class="flex items-start justify-between mb-4">
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $sc['bg'] ?> <?= $sc['text'] ?> <?= $sc['ring'] ?>">
          <span class="inline-block w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
          <?= $statusLabel ?>
        </span>
        <svg class="w-4 h-4 text-gray-600 transition-transform group-hover:translate-x-0.5 group-hover:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </div>

      <!-- Title & client -->
      <h3 class="font-semibold text-white line-clamp-2 mb-1 group-hover:text-violet-200 transition-colors">
        <?= e($plan['title']) ?>
      </h3>
      <p class="text-sm text-gray-400 mb-4 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
        <?= e($plan['client_name']) ?>
      </p>

      <!-- Date range -->
      <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-4">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <?= date('d/m', strtotime($plan['week_start'])) ?> – <?= date('d/m/Y', strtotime($plan['week_end'])) ?>
      </div>

      <!-- Progress bar -->
      <?php if ($total > 0): ?>
      <div class="mt-auto">
        <div class="flex justify-between text-xs text-gray-400 mb-1.5">
          <span><?= $approved ?>/<?= $total ?> aprovados</span>
          <span><?= $pct ?>%</span>
        </div>
        <div class="h-1.5 rounded-full bg-white/5 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-violet-400 transition-all duration-700"
               style="width: <?= $pct ?>%"></div>
        </div>
      </div>
      <?php else: ?>
      <div class="mt-auto">
        <p class="text-xs text-gray-600">Sem itens ainda</p>
      </div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function contentIndex() {
  return {}
}
</script>

<?php view_end(); ?>
