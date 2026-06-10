<?php
view_layout('app');
view_start('content');

use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;
use App\Support\Auth;

$drive    = app(GoogleDriveService::class);
$canEdit  = Auth::can('content.edit');
$canSend  = Auth::can('content.send_to_approval');
$canCreate= Auth::can('content.create');

$statusColors = [
  'draft'    => ['bg' => 'bg-gray-500/15',   'text' => 'text-gray-300',   'ring' => 'ring-gray-500/30',   'dot' => 'bg-gray-400'],
  'sent'     => ['bg' => 'bg-blue-500/15',   'text' => 'text-blue-300',   'ring' => 'ring-blue-500/30',   'dot' => 'bg-blue-400'],
  'revision' => ['bg' => 'bg-amber-500/15',  'text' => 'text-amber-300',  'ring' => 'ring-amber-500/30',  'dot' => 'bg-amber-400'],
  'approved' => ['bg' => 'bg-emerald-500/15','text' => 'text-emerald-300','ring' => 'ring-emerald-500/30','dot' => 'bg-emerald-400'],
  'rejected' => ['bg' => 'bg-rose-500/15',   'text' => 'text-rose-300',   'ring' => 'ring-rose-500/30',   'dot' => 'bg-rose-400'],
];
$sc         = $statusColors[$plan['status']] ?? $statusColors['draft'];
$statusLabel= ContentPlanService::statusLabel($plan['status']);
$total      = array_sum($plan['status_summary']);
$approved   = ($plan['status_summary']['approved'] ?? 0);
$pct        = $total > 0 ? round(($approved / $total) * 100) : 0;
?>

<div x-data="contentShow(<?= $plan['id'] ?>)" class="min-h-screen">

  <!-- ── Breadcrumb ──────────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-center gap-2 text-sm text-gray-400">
    <a href="/conteudo" class="hover:text-white transition-colors">Planos</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-300 truncate max-w-xs"><?= e($plan['title']) ?></span>
  </div>

  <!-- ── Plan header ──────────────────────────────────────────────────────── -->
  <div class="mb-6 rounded-2xl border border-white/5 bg-white/[0.03] p-5 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $sc['bg'] ?> <?= $sc['text'] ?> <?= $sc['ring'] ?>">
            <span class="inline-block w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
            <?= $statusLabel ?>
          </span>
          <span class="text-xs text-gray-500"><?= e($plan['client_name']) ?></span>
        </div>
        <h1 class="text-xl font-bold text-white sm:text-2xl"><?= e($plan['title']) ?></h1>
        <p class="mt-1 text-sm text-gray-400">
          <?= date('d/m', strtotime($plan['week_start'])) ?> – <?= date('d/m/Y', strtotime($plan['week_end'])) ?>
          · criado por <?= e($plan['created_by_name']) ?>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ($canSend && in_array($plan['status'], ['draft', 'revision'])): ?>
        <button @click="sendPlan()"
                :disabled="sending"
                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 transition-all hover:bg-violet-500 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" :class="{'animate-spin': sending}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
          <span x-text="sending ? 'Enviando...' : 'Enviar para Aprovação'"></span>
        </button>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <a href="/conteudo/<?= e($plan['id']) ?>/editar"
           class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:border-white/20 transition-all">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          Editar
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats row -->
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
      <?php
      $statuses = [
        ['key' => 'draft',    'label' => 'Rascunho',   'color' => 'text-gray-300'],
        ['key' => 'revision', 'label' => 'Revisão',    'color' => 'text-amber-300'],
        ['key' => 'approved', 'label' => 'Aprovados',  'color' => 'text-emerald-300'],
        ['key' => 'rejected', 'label' => 'Rejeitados', 'color' => 'text-rose-300'],
      ];
      foreach ($statuses as $st):
        $count = $plan['status_summary'][$st['key']] ?? 0;
      ?>
      <div class="rounded-xl bg-white/[0.03] border border-white/5 p-3 text-center">
        <div class="text-xl font-bold <?= $st['color'] ?>"><?= $count ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= $st['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Progress bar -->
    <?php if ($total > 0): ?>
    <div class="mt-4">
      <div class="flex justify-between text-xs text-gray-400 mb-1.5">
        <span>Progresso de aprovação</span>
        <span><?= $approved ?>/<?= $total ?> (<?= $pct ?>%)</span>
      </div>
      <div class="h-2 rounded-full bg-white/5 overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-violet-600 via-violet-400 to-emerald-400 transition-all duration-700"
             style="width: <?= $pct ?>%"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Flash messages ──────────────────────────────────────────────────────── -->
  <?php if ($msg = flash('success')): ?>
  <div x-data="{show:true}" x-show="show" x-transition
       class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <?= e($msg) ?>
    <button @click="show=false" class="ml-auto text-emerald-400 hover:text-emerald-200">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <?php endif; ?>

  <!-- ── API toast ──────────────────────────────────────────────────────────── -->
  <div x-show="toast.show" x-transition.opacity
       :class="toast.ok ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300'"
       class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-2xl max-w-xs"
       style="display:none">
    <span x-text="toast.msg"></span>
  </div>

  <!-- ── Items section ──────────────────────────────────────────────────────── -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4">
    <h2 class="text-lg font-semibold text-white">
      Itens do Plano
      <span class="ml-2 rounded-full bg-violet-500/20 px-2.5 py-0.5 text-xs font-medium text-violet-300"><?= count($plan['items']) ?></span>
    </h2>
    <?php if ($canCreate): ?>
    <button @click="openAddItem()"
            class="inline-flex items-center gap-2 rounded-xl bg-white/5 border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Adicionar Item
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($plan['items'])): ?>
  <div class="rounded-2xl border border-dashed border-white/10 py-16 text-center">
    <div class="mx-auto mb-4 w-12 h-12 rounded-2xl bg-violet-500/10 flex items-center justify-center">
      <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
      </svg>
    </div>
    <p class="text-gray-400 text-sm mb-3">Nenhum item neste plano.</p>
    <?php if ($canCreate): ?>
    <button @click="openAddItem()" class="text-sm text-violet-400 hover:text-violet-300 transition-colors">
      Adicionar primeiro item →
    </button>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <!-- Items list -->
  <div class="space-y-3" id="items-list">
    <?php foreach ($plan['items'] as $idx => $item):
      $isc = $statusColors[$item['status']] ?? $statusColors['draft'];
      $iLabel = ContentPlanService::itemStatusLabel($item['status']);
      $parsedDrive = $item['drive_parsed'];
    ?>
    <div class="item-card group rounded-2xl border border-white/5 bg-white/[0.03] transition-all duration-200 hover:border-violet-500/20 hover:bg-white/[0.05]"
         x-data="itemCard(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)"
         data-id="<?= $item['id'] ?>">

      <!-- Item header -->
      <div class="flex items-start gap-3 p-4 cursor-pointer" @click="expanded = !expanded">

        <!-- Drag handle (hidden on mobile, shown on hover desktop) -->
        <div class="hidden sm:flex flex-shrink-0 items-center justify-center w-6 h-6 mt-0.5 cursor-grab opacity-0 group-hover:opacity-40 hover:!opacity-70 transition-opacity text-gray-400">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm8 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM8 13.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm8 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM8 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm8 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/>
          </svg>
        </div>

        <!-- Status indicator -->
        <div class="flex-shrink-0 mt-0.5">
          <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 <?= $isc['bg'] ?> <?= $isc['text'] ?> <?= $isc['ring'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $isc['dot'] ?>"></span>
            <?= $iLabel ?>
          </span>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <?php if (!empty($item['publish_date'])): ?>
              <p class="text-xs text-gray-500 mb-0.5">
                <?= date('d/m (D)', strtotime($item['publish_date'])) ?>
                <?= !empty($item['publish_time']) ? ' às ' . substr($item['publish_time'], 0, 5) : '' ?>
                <?php if (!empty($item['content_type'])): ?>
                · <span class="text-violet-400"><?= e($item['content_type']) ?></span>
                <?php endif; ?>
              </p>
              <?php endif; ?>
              <h3 class="font-semibold text-white text-sm sm:text-base truncate">
                <?= e($item['title'] ?: 'Item sem título') ?>
              </h3>
              <?php if (!empty($item['theme'])): ?>
              <p class="text-xs text-gray-400 mt-0.5 truncate"><?= e($item['theme']) ?></p>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
              <?php if ($parsedDrive && $parsedDrive['valid']): ?>
              <span class="flex-shrink-0 rounded-full p-1 bg-violet-500/10">
                <?php
                $iconMap = ['video'=>'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z','image'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'];
                $iconPath = $iconMap[$parsedDrive['file_type']] ?? 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                ?>
                <svg class="w-3.5 h-3.5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"/>
                </svg>
              </span>
              <?php endif; ?>

              <?php if ((int) $item['feedback_count'] > 0): ?>
              <span class="flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-400">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <?= $item['feedback_count'] ?>
              </span>
              <?php endif; ?>

              <svg class="w-4 h-4 text-gray-600 transition-transform duration-200"
                   :class="{'rotate-90': expanded}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Expanded content -->
      <div x-show="expanded" x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
           class="border-t border-white/5 p-4 space-y-4">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <?php if (!empty($item['caption'])): ?>
          <div class="rounded-xl bg-white/[0.03] border border-white/5 p-3">
            <p class="text-xs font-medium text-gray-400 mb-1">Legenda</p>
            <p class="text-sm text-gray-200 whitespace-pre-line leading-relaxed"><?= e($item['caption']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($item['script'])): ?>
          <div class="rounded-xl bg-white/[0.03] border border-white/5 p-3">
            <p class="text-xs font-medium text-gray-400 mb-1">Roteiro</p>
            <p class="text-sm text-gray-200 whitespace-pre-line leading-relaxed"><?= e($item['script']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($item['cta'])): ?>
          <div class="rounded-xl bg-white/[0.03] border border-white/5 p-3">
            <p class="text-xs font-medium text-gray-400 mb-1">CTA</p>
            <p class="text-sm text-gray-200"><?= e($item['cta']) ?></p>
          </div>
          <?php endif; ?>
        </div>

        <!-- Drive preview -->
        <?php if ($parsedDrive && $parsedDrive['valid']): ?>
        <div class="rounded-xl overflow-hidden border border-white/5 bg-black/20">
          <div class="flex items-center gap-2 px-3 py-2 border-b border-white/5">
            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <span class="text-xs text-gray-400">Google Drive —
              <?= e(app(GoogleDriveService::class)->getTypeLabel($parsedDrive['file_type'])) ?>
            </span>
            <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
               class="ml-auto text-xs text-violet-400 hover:text-violet-300 transition-colors">
              Abrir →
            </a>
          </div>
          <div class="aspect-video sm:aspect-[4/3] lg:aspect-[16/9]">
            <iframe src="<?= e($parsedDrive['embed_url']) ?>"
                    class="w-full h-full border-0" loading="lazy" allowfullscreen></iframe>
          </div>
        </div>
        <?php endif; ?>

        <!-- Feedbacks -->
        <?php if (!empty($item['feedbacks'])): ?>
        <div class="space-y-2">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Feedbacks</p>
          <?php foreach ($item['feedbacks'] as $fb):
            $fbColors = [
              'approved'          => 'text-emerald-300 bg-emerald-500/10',
              'changes_requested' => 'text-amber-300 bg-amber-500/10',
              'rejected'          => 'text-rose-300 bg-rose-500/10',
              'comment'           => 'text-gray-300 bg-white/5',
            ];
            $fbClass = $fbColors[$fb['feedback_type']] ?? $fbColors['comment'];
            $fbLabel = ['approved'=>'Aprovado','changes_requested'=>'Revisão solicitada','rejected'=>'Rejeitado','comment'=>'Comentário'][$fb['feedback_type']] ?? $fb['feedback_type'];
          ?>
          <div class="rounded-xl <?= $fbClass ?> border border-white/5 p-3">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-semibold"><?= e($fb['user_name']) ?></span>
              <span class="text-xs opacity-60"><?= date('d/m H:i', strtotime($fb['created_at'])) ?></span>
            </div>
            <p class="text-xs font-medium mb-1"><?= $fbLabel ?></p>
            <?php if (!empty($fb['comment'])): ?>
            <p class="text-sm opacity-80"><?= e($fb['comment']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Item actions -->
        <?php if ($canEdit): ?>
        <div class="flex items-center gap-2 pt-1">
          <button @click="openEdit()"
                  class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/10 transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
          </button>
          <button @click="deleteItem()"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-rose-500/20 px-3 py-1.5 text-xs font-medium text-rose-400 hover:bg-rose-500/10 transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Excluir
          </button>
          <!-- Quick status change -->
          <div class="relative ml-auto" x-data="{open:false}">
            <button @click="open = !open"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/10 transition-all">
              Status
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.away="open=false" x-transition
                 class="absolute right-0 bottom-full mb-1 w-40 rounded-xl border border-white/10 bg-gray-900 shadow-2xl py-1 z-10">
              <?php foreach (['draft'=>'Rascunho','revision'=>'Revisão','approved'=>'Aprovado','rejected'=>'Rejeitado'] as $s => $l): ?>
              <button @click="changeStatus('<?= $s ?>'); open=false"
                      class="w-full px-3 py-2 text-xs text-left text-gray-300 hover:bg-white/5 hover:text-white transition-colors">
                <?= $l ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Add Item Modal ──────────────────────────────────────────────────────── -->
  <div x-show="showAddItem" x-transition.opacity
       class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
       style="display:none">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showAddItem=false"></div>
    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-white/10 bg-gray-950 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

      <div class="flex items-center justify-between p-5 border-b border-white/5">
        <h3 class="text-base font-semibold text-white">Adicionar Item</h3>
        <button @click="showAddItem=false" class="text-gray-400 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form @submit.prevent="submitAddItem()" class="p-5 space-y-4">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Data de Publicação</label>
            <input type="date" x-model="newItem.publish_date"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Horário</label>
            <input type="time" x-model="newItem.publish_time"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Tipo de Conteúdo</label>
          <select x-model="newItem.content_type"
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
            <option value="">Selecione...</option>
            <option>Reels</option><option>Story</option><option>Feed Estático</option>
            <option>Carrossel</option><option>Post TikTok</option><option>YouTube</option>
            <option>Blog</option><option>LinkedIn</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Título</label>
          <input type="text" x-model="newItem.title" placeholder="Título do conteúdo"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Tema / Assunto</label>
          <input type="text" x-model="newItem.theme" placeholder="Sobre o que é o conteúdo"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Legenda</label>
          <textarea x-model="newItem.caption" rows="3" placeholder="Texto da publicação..."
                    class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 resize-none"></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Roteiro</label>
          <textarea x-model="newItem.script" rows="2" placeholder="Roteiro ou briefing..."
                    class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 resize-none"></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">CTA</label>
          <input type="text" x-model="newItem.cta" placeholder="Chamada para ação"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Link Google Drive</label>
          <input type="url" x-model="newItem.drive_url" placeholder="https://drive.google.com/..."
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          <p class="text-xs text-gray-500 mt-1">Suporta arquivos, pastas, Docs, Sheets e Slides</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Responsável</label>
          <select x-model="newItem.assigned_to"
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
            <option value="">Sem responsável</option>
            <?php foreach ($teamMembers as $member): ?>
            <option value="<?= e($member['id']) ?>"><?= e($member['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit" :disabled="submitting"
                  class="flex-1 rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-violet-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-text="submitting ? 'Salvando...' : 'Adicionar Item'"></span>
          </button>
          <button type="button" @click="showAddItem=false"
                  class="rounded-xl border border-white/10 px-4 py-3 text-sm text-gray-400 hover:text-white transition-all">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /x-data -->

<script>
const PLAN_ID = <?= $plan['id'] ?>;
const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function contentShow(planId) {
  return {
    showAddItem: false,
    sending: false,
    submitting: false,
    toast: { show: false, msg: '', ok: true },

    newItem: {
      publish_date: '', publish_time: '', content_type: '', title: '',
      theme: '', caption: '', script: '', cta: '', drive_url: '', assigned_to: ''
    },

    openAddItem() {
      this.newItem = { publish_date:'', publish_time:'', content_type:'', title:'', theme:'', caption:'', script:'', cta:'', drive_url:'', assigned_to:'' };
      this.showAddItem = true;
    },

    async submitAddItem() {
      this.submitting = true;
      try {
        const r = await fetch(`/conteudo/${planId}/items`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body: JSON.stringify(this.newItem)
        });
        const d = await r.json();
        if (d.success) {
          this.showToast('Item adicionado!', true);
          this.showAddItem = false;
          setTimeout(() => location.reload(), 600);
        } else {
          this.showToast(d.error || 'Erro ao adicionar.', false);
        }
      } catch { this.showToast('Erro de conexão.', false); }
      this.submitting = false;
    },

    async sendPlan() {
      if (!confirm('Enviar este plano para aprovação do cliente?')) return;
      this.sending = true;
      try {
        const r = await fetch(`/conteudo/${planId}/enviar`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF }
        });
        const d = await r.json();
        if (d.success) {
          this.showToast('Plano enviado!', true);
          setTimeout(() => location.reload(), 800);
        } else {
          this.showToast('Não foi possível enviar.', false);
        }
      } catch { this.showToast('Erro de conexão.', false); }
      this.sending = false;
    },

    showToast(msg, ok) {
      this.toast = { show: true, msg, ok };
      setTimeout(() => this.toast.show = false, 3500);
    }
  }
}

function itemCard(item) {
  return {
    expanded: false,
    item,

    openEdit() {
      // TODO: implement edit modal (Phase 2b)
      alert('Em breve: edição inline do item #' + item.id);
    },

    async deleteItem() {
      if (!confirm('Excluir este item?')) return;
      const r = await fetch(`/conteudo/${PLAN_ID}/items/${item.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': CSRF }
      });
      const d = await r.json();
      if (d.success) {
        this.$el.remove();
      }
    },

    async changeStatus(status) {
      const r = await fetch(`/conteudo/${PLAN_ID}/items/${item.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ status })
      });
      const d = await r.json();
      if (d.success) location.reload();
    }
  }
}
</script>

<?php view_end(); ?>
