<?php
view_layout('app');
view_start('content');

use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;
use App\Support\Auth;

$drive      = app(GoogleDriveService::class);
$canApprove = Auth::can('approvals.approve');
$canComment = Auth::can('approvals.comment');

/**
 * Tipos de feedback que ESTE perfil pode enviar. A guarda de verdade está no
 * controller (`ApprovalController::FEEDBACK_PERMISSION`); aqui só não
 * mostramos botão que responderia 403 — aprovar item exige `approvals.approve`,
 * revisão e comentário exigem `approvals.comment`.
 */
$feedbackTypes = [];
if ($canApprove) {
  $feedbackTypes['approved'] = [
    'label' => t('approval.approve_item'),
    'hover' => 'hover:border-emerald-500/40 hover:bg-emerald-500/10 hover:text-emerald-300',
  ];
}
if ($canComment) {
  $feedbackTypes['changes_requested'] = [
    'label' => t('approval.request_changes'),
    'hover' => 'hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-300',
  ];
  $feedbackTypes['comment'] = [
    'label' => t('approval.comment'),
    'hover' => 'hover:border-white/20 hover:bg-white/5 hover:text-white',
  ];
}
/**
 * Tipo pré-selecionado: 'comment', que não muda nada, sempre que o perfil
 * puder comentar. Pegar o primeiro da lista deixava 'approved' armado para
 * quem aprova — e o botão de enviar não diz "aprovar": um recado para o
 * designer aprovava o post, e aprovar o último post fecha o plano e manda a
 * confirmação de aprovação para o cliente. Perfil que só aprova continua
 * nascendo em 'approved', o único tipo que ele pode enviar.
 */
$defaultType = isset($feedbackTypes['comment'])
  ? 'comment'
  : (array_key_first($feedbackTypes) ?? 'comment');
// Classe completa por contagem (nunca concatenada — o purge do Tailwind só vê
// literal, ver skill yve-frontend).
$typeGrid = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3'][count($feedbackTypes)] ?? 'grid-cols-3';

$statusColors = [
  'draft'    => ['bg' => 'bg-gray-500/15',   'text' => 'text-gray-300',   'ring' => 'ring-gray-500/30',   'dot' => 'bg-gray-400'],
  'sent'     => ['bg' => 'bg-blue-500/15',   'text' => 'text-blue-300',   'ring' => 'ring-blue-500/30',   'dot' => 'bg-blue-400'],
  'revision' => ['bg' => 'bg-amber-500/15',  'text' => 'text-amber-300',  'ring' => 'ring-amber-500/30',  'dot' => 'bg-amber-400'],
  'approved' => ['bg' => 'bg-emerald-500/15','text' => 'text-emerald-300','ring' => 'ring-emerald-500/30','dot' => 'bg-emerald-400'],
  'rejected' => ['bg' => 'bg-rose-500/15',   'text' => 'text-rose-300',   'ring' => 'ring-rose-500/30',   'dot' => 'bg-rose-400'],
];
$sc          = $statusColors[$plan['status']] ?? $statusColors['sent'];
$statusLabel = t('status.' . $plan['status']);
$totalItems  = count($plan['items']);
$approvedCount = count(array_filter($plan['items'], fn($i) => $i['status'] === 'approved'));
$pct         = $totalItems > 0 ? round(($approvedCount / $totalItems) * 100) : 0;

// A tela fala o idioma do cliente, então os textos do JS vêm prontos do PHP.
$jsI18n = [
  'confirm_approve_all' => t('approval.confirm_approve_all'),
  'plan_approved'       => t('approval.plan_approved'),
  'revision_sent'       => t('approval.revision_sent'),
  'feedback_sent'       => t('approval.feedback_sent'),
  'action_failed'       => t('approval.action_failed'),
  'reopened'            => t('approval.reopened'),
  'status'              => [
    'draft'    => t('status.draft'),
    'sent'     => t('status.sent'),
    'revision' => t('status.revision'),
    'approved' => t('status.approved'),
    'rejected' => t('status.rejected'),
  ],
];
$jsI18nJson = e(json_encode($jsI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
?>

<div id="approval-show" data-plan-id="<?= (int) $plan['id'] ?>"
     x-data="approvalShow(<?= (int) $plan['id'] ?>, <?= $jsI18nJson ?>)"
     @approval-toast.window="showToast($event.detail.msg, $event.detail.ok)"
     class="max-w-2xl mx-auto pb-20">

  <!-- Back -->
  <div class="mb-6">
    <a href="/aprovacoes" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= t('approval.all_plans') ?>
    </a>
  </div>

  <!-- Plan header card -->
  <div class="card p-5 mb-6">
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $sc['bg'] ?> <?= $sc['text'] ?> <?= $sc['ring'] ?>">
        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?> <?= $plan['status'] === 'sent' ? 'animate-pulse' : '' ?>"></span>
        <?= e($statusLabel) ?>
      </span>
      <span class="text-xs text-gray-400"><?= e($plan['client_name']) ?></span>
    </div>

    <h1 class="text-xl font-bold text-white mb-1"><?= e($plan['title']) ?></h1>
    <p class="text-sm text-gray-400">
      <?= e(t('approval.week_range', [
        'start' => date('d/m', strtotime($plan['week_start'])),
        'end'   => date('d/m/Y', strtotime($plan['week_end'])),
      ])) ?>
    </p>

    <!-- Progress -->
    <div class="mt-4">
      <div class="flex justify-between text-xs text-gray-400 mb-1.5">
        <span><?= e(t('approval.items_approved', ['approved' => $approvedCount, 'total' => $totalItems])) ?></span>
        <span><?= $pct ?>%</span>
      </div>
      <div class="h-2 rounded-full bg-white/5" role="progressbar" aria-valuenow="<?= $pct ?>"
           aria-valuemin="0" aria-valuemax="100" aria-label="<?= e(t('approval.progress')) ?>">
        <div class="h-full rounded-full bg-accent transition-all duration-700" style="width:<?= $pct ?>%"></div>
      </div>
    </div>

    <!-- Plan-level actions -->
    <?php if ($canApprove && $plan['status'] === 'sent'): ?>
    <div class="mt-5 grid grid-cols-2 gap-3">
      <button @click="approvePlan()"
              :disabled="acting"
              class="flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-500 active:scale-95 disabled:opacity-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span x-text="acting ? <?= e(json_encode(t('approval.sending'))) ?> : <?= e(json_encode(t('approval.approve_all'))) ?>"></span>
      </button>
      <button @click="showRevisionModal = true"
              :disabled="acting"
              class="flex items-center justify-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm font-semibold text-amber-300 transition-all hover:bg-amber-500/20 active:scale-95 disabled:opacity-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <?= t('approval.request_revision') ?>
      </button>
    </div>
    <?php elseif ($plan['status'] === 'approved'): ?>
    <div class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3">
      <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="text-sm text-emerald-300 font-medium">
        <?php if (!empty($approvedByName)): ?>
        <?= e(t('approval.approved_by_team', ['name' => $approvedByName, 'date' => date_fmt($plan['approved_at'] ?? null)])) ?>
        <?php else: ?>
        <?= e(t('approval.approved_on', ['date' => date_fmt($plan['approved_at'] ?? null)])) ?>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>

    <?php /* BUG-08: plano fechado volta para a equipe corrigir e reenviar. */ ?>
    <?php if ($canApprove && in_array($plan['status'], ['approved', 'rejected'], true)): ?>
    <div class="mt-3 flex justify-end">
      <button @click="showReopenModal = true" class="btn-secondary"><?= e(t('approval.reopen')) ?></button>
    </div>
    <?php endif; ?>

    <?php /* Plano em revisão com TODOS os posts aprovados pela cliente (BUG-05): a
             aprovação item a item não fecha mais plano em revisão — ele espera a
             equipe corrigir e reenviar. Sem este aviso, a semana ficava parada sem
             ninguém saber que a cliente já tinha decidido. */ ?>
    <?php if ($plan['status'] === 'revision' && $totalItems > 0 && $approvedCount === $totalItems): ?>
    <div class="mt-4 flex items-start gap-2 rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-3">
      <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="text-sm text-amber-300"><?= e(t('approval.revision_all_approved')) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Items -->
  <?php if ($totalItems === 0): ?>
  <div class="card flex flex-col items-center justify-center px-6 py-16 text-center">
    <div class="mb-4 rounded-2xl bg-accent-soft p-5">
      <svg class="w-10 h-10 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1"><?= t('approval.no_items') ?></h3>
    <p class="text-sm text-gray-400 max-w-sm"><?= t('approval.no_items_hint') ?></p>
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($plan['items'] as $loopIdx => $item):
      $isc    = $statusColors[$item['status']] ?? $statusColors['draft'];
      $parsed = $item['drive_parsed'] ?? ($item['drive_url'] ? $drive->parse($item['drive_url']) : null);
      $frameClass = ContentPlanService::previewFrameClass($item['content_type'] ?? null);
      $videoFrame = ContentPlanService::videoFrameClass($item['content_type'] ?? null, $parsed['file_type'] ?? null);
      $imagesList = $item['images_list'] ?? [];
    ?>
    <div class="card overflow-hidden"
         x-data="approvalItem(<?= (int) $plan['id'] ?>, <?= (int) $item['id'] ?>, '<?= e($item['status']) ?>', <?= $jsI18nJson ?>, '<?= e($defaultType) ?>')"
         id="item-<?= (int) $item['id'] ?>">

      <!-- Item header -->
      <div class="p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex-1 min-w-0">
            <?php if (!empty($item['publish_date'])): ?>
            <p class="text-xs text-gray-400 mb-1">
              <?= date('D, d/m', strtotime($item['publish_date'])) ?>
              <?= !empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '' ?>
              <?= !empty($item['content_type']) ? ' · ' . e($item['content_type']) : '' ?>
            </p>
            <?php endif; ?>
            <h3 class="font-semibold text-white"><?= e($item['title'] ?: 'Item ' . ($loopIdx + 1)) ?></h3>
            <?php if (!empty($item['theme'])): ?>
            <p class="text-sm text-gray-400 mt-0.5"><?= e($item['theme']) ?></p>
            <?php endif; ?>
          </div>

          <?php /* Cor só por :class (o Alpine troca a dele quando o status muda);
                    classe estática de status aqui brigaria com a dinâmica. */ ?>
          <span :class="statusClass(currentStatus)"
                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1">
            <span class="w-1.5 h-1.5 rounded-full" :class="statusDot(currentStatus)"></span>
            <span x-text="statusLabel(currentStatus)"><?= e(t('status.' . $item['status'])) ?></span>
          </span>
        </div>

        <!-- Caption, script, cta -->
        <?php if (!empty($item['caption']) || !empty($item['script']) || !empty($item['cta'])): ?>
        <div class="space-y-3 mb-4" x-data="{showFull: false}">
          <?php if (!empty($item['caption'])): ?>
          <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5"><?= t('approval.caption') ?></p>
            <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-line"
               :class="showFull ? '' : 'line-clamp-3'"><?= e($item['caption']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($item['script'])): ?>
          <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5"><?= t('approval.script') ?></p>
            <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-line"
               :class="showFull ? '' : 'line-clamp-3'"><?= e($item['script']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($item['cta'])): ?>
          <div class="rounded-xl bg-accent-soft border border-accent px-3 py-2">
            <p class="text-xs font-medium text-accent mb-0.5"><?= t('approval.cta') ?></p>
            <p class="text-sm text-gray-200"><?= e($item['cta']) ?></p>
          </div>
          <?php endif; ?>
          <button @click="showFull = !showFull" class="text-xs text-accent hover:text-white transition-colors">
            <span x-text="showFull ? <?= e(json_encode(t('approval.see_less'))) ?> : <?= e(json_encode(t('approval.see_more'))) ?>"></span>
          </button>
        </div>
        <?php endif; ?>

        <!-- Capa do criativo -->
        <?php if (!empty($item['cover_url'])): ?>
        <div class="mb-4">
          <div class="relative w-full <?= $frameClass ?> overflow-hidden rounded-xl border border-white/5 bg-black/30">
            <img src="<?= e(GoogleDriveService::imageSrc($item['cover_url'])) ?>" alt="<?= e(t('approval.caption')) ?>"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="lazy"
                 onerror="this.parentElement.style.display='none'">
          </div>
        </div>
        <?php endif; ?>

        <!-- Carrossel -->
        <?php if (!empty($imagesList)): ?>
        <div class="flex gap-3 overflow-x-auto pb-2 mb-4">
          <?php foreach ($imagesList as $slideIdx => $imgUrl): if (empty($imgUrl)) continue; ?>
          <div class="relative flex-shrink-0 w-40 aspect-[3/4] overflow-hidden rounded-xl border border-white/5 bg-black/30">
            <img src="<?= e(GoogleDriveService::imageSrc($imgUrl)) ?>" alt="<?= $slideIdx + 1 ?>"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="lazy"
                 onerror="this.parentElement.style.display='none'">
            <span class="absolute top-1.5 right-1.5 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">
              <?= $slideIdx + 1 ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Drive embed -->
        <?php if (!empty($parsed) && $parsed['valid']): ?>
        <div class="rounded-xl overflow-hidden border border-white/5 mb-4" x-data="{loaded: false}">
          <div class="flex items-center gap-2 px-3 py-2 bg-white/[0.03] border-b border-white/5">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <span class="text-xs text-gray-400 flex-1">
              <?= e($drive->getTypeLabel($parsed['file_type'])) ?> — Google Drive
            </span>
            <a href="<?= e($parsed['original']) ?>" target="_blank" rel="noopener"
               class="text-xs text-accent hover:text-white transition-colors">
              <?= t('approval.open_drive') ?>
            </a>
          </div>
          <div class="bg-black/30">
            <div class="relative <?= $videoFrame ?>">
              <iframe src="<?= e($parsed['embed_url']) ?>"
                      class="absolute inset-0 w-full h-full border-0"
                      loading="lazy" allowfullscreen
                      @load="loaded = true"></iframe>
              <div x-show="!loaded" class="absolute inset-0 flex items-center justify-center text-accent">
                <div class="w-8 h-8 rounded-full border-2 border-white/10 border-t-current animate-spin"></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Existing feedbacks -->
        <?php if (!empty($item['feedbacks'])): ?>
        <div class="space-y-2 mb-4" x-data="{showFbs: false}">
          <button @click="showFbs = !showFbs" class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <?= e(tc('approval.feedbacks', count($item['feedbacks']))) ?>
            <span x-text="showFbs ? '↑' : '↓'" class="text-xs"></span>
          </button>
          <div x-show="showFbs" x-transition class="space-y-2">
            <?php foreach ($item['feedbacks'] as $fb):
              $fbColors = [
                'approved'          => 'border-emerald-500/20 bg-emerald-500/5 text-emerald-300',
                'changes_requested' => 'border-amber-500/20 bg-amber-500/5 text-amber-300',
                'rejected'          => 'border-rose-500/20 bg-rose-500/5 text-rose-300',
                'comment'           => 'border-white/10 bg-white/[0.03] text-gray-300',
              ];
              $fbCls = $fbColors[$fb['feedback_type']] ?? $fbColors['comment'];
              $fbLbl = t('approval.feedback.' . $fb['feedback_type']);
            ?>
            <div class="rounded-xl border p-3 <?= $fbCls ?>">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold"><?= e($fb['user_name'] ?? $fb['client_name'] ?? '') ?></span>
                <span class="text-xs opacity-50"><?= date('d/m H:i', strtotime($fb['created_at'])) ?></span>
              </div>
              <p class="text-xs font-medium mb-1"><?= e($fbLbl) ?></p>
              <?php if (!empty($fb['comment'])): ?>
              <p class="text-sm"><?= e($fb['comment']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Feedback form -->
        <?php if ($feedbackTypes && $plan['status'] !== 'approved'): ?>
        <div>
          <button @click="open = !open"
                  class="w-full flex items-center justify-center gap-2 rounded-xl border border-white/10 py-2.5 text-sm font-medium text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span x-text="open ? <?= e(json_encode(t('approval.close'))) ?> : <?= e(json_encode(t('approval.send_feedback'))) ?>"></span>
          </button>

          <div x-show="open" x-cloak x-transition class="mt-3 space-y-3">
            <!-- Type selector -->
            <div class="grid <?= $typeGrid ?> gap-2">
              <?php foreach ($feedbackTypes as $typeKey => $typeMeta): ?>
              <button type="button" @click="type = '<?= e($typeKey) ?>'"
                      :class="type === '<?= e($typeKey) ?>' ? 'border-accent bg-accent-soft text-accent' : 'border-white/10 text-gray-400'"
                      class="<?= $typeMeta['hover'] ?> rounded-xl border py-2 text-xs font-medium transition-all">
                <?= e($typeMeta['label']) ?>
              </button>
              <?php endforeach; ?>
            </div>

            <textarea x-model="comment" rows="3"
                      :placeholder="type === 'comment' ? <?= e(json_encode(t('approval.comment_placeholder'))) ?> : <?= e(json_encode(t('approval.reason_placeholder'))) ?>"
                      class="input-field resize-none"></textarea>

            <button @click="submitFeedback()"
                    :disabled="submitting"
                    :class="{
                      'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-500/20': type === 'approved',
                      'bg-amber-600 hover:bg-amber-500 text-white shadow-amber-500/20':  type === 'changes_requested',
                      'bg-accent': type === 'comment'
                    }"
                    class="w-full rounded-xl px-4 py-3 text-sm font-semibold shadow-lg transition-all hover:scale-[1.01] active:scale-95 disabled:opacity-50 disabled:hover:scale-100">
              <span x-text="submitting ? <?= e(json_encode(t('approval.sending'))) ?> : <?= e(json_encode(t('approval.submit_feedback'))) ?>"></span>
            </button>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Revision request modal -->
  <?php if ($canApprove): ?>
  <div x-show="showRevisionModal" x-cloak x-transition.opacity
       class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showRevisionModal=false"></div>
    <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-gray-950 p-6 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
      <h3 class="text-base font-semibold text-white mb-4"><?= t('approval.revision_modal_title') ?></h3>
      <textarea x-model="revisionNote" rows="4"
                placeholder="<?= e(t('approval.revision_note_placeholder')) ?>"
                class="input-field resize-none mb-4"></textarea>
      <div class="flex gap-3">
        <button @click="requestRevision()" :disabled="acting"
                class="flex-1 rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-amber-500 disabled:opacity-50">
          <span x-text="acting ? <?= e(json_encode(t('approval.sending'))) ?> : <?= e(json_encode(t('approval.request_revision'))) ?>"></span>
        </button>
        <button @click="showRevisionModal=false" class="btn-secondary">
          <?= t('common.cancel') ?>
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($canApprove && in_array($plan['status'], ['approved', 'rejected'], true)): ?>
  <div x-show="showReopenModal" x-cloak x-transition.opacity
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       @keydown.escape.window="showReopenModal=false" role="dialog" aria-modal="true" aria-labelledby="reopen-title">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showReopenModal=false"></div>
    <div class="relative w-full max-w-md card p-6">
      <h3 id="reopen-title" class="text-base font-semibold text-white mb-2"><?= e(t('approval.reopen_title')) ?></h3>
      <p class="text-sm text-gray-400 mb-4"><?= e(t('approval.reopen_hint')) ?></p>
      <label for="reopen-reason" class="block text-sm font-medium text-gray-300 mb-1.5"><?= e(t('approval.reopen_reason')) ?></label>
      <textarea id="reopen-reason" x-model="reopenReason" rows="3" required maxlength="500" class="input-field resize-none mb-4"></textarea>
      <div class="flex gap-3">
        <button @click="reopenPlan()" :disabled="acting || !reopenReason.trim()"
                class="flex-1 rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-amber-500 disabled:opacity-50">
          <span x-text="acting ? <?= e(json_encode(t('approval.sending'))) ?> : <?= e(json_encode(t('approval.reopen'))) ?>"></span>
        </button>
        <button @click="showReopenModal=false" class="btn-secondary"><?= t('common.cancel') ?></button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Toast (erro ou sucesso — nenhuma ação termina em silêncio) -->
  <div x-show="toast.show" x-cloak x-transition.opacity
       :class="toast.ok ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300'"
       class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-2xl max-w-xs"
       role="status" aria-live="polite">
    <span x-text="toast.msg"></span>
  </div>

</div>

<!-- SEM defer, de propósito: o Alpine (defer, no <head>) executa ANTES de
     qualquer script defer do body e chama Alpine.start() — se este módulo
     ainda não tiver definido a função do x-data, o componente morre com
     "ReferenceError: ... is not defined". Script clássico no body executa
     durante o parse, portanto antes do Alpine. -->
<script src="<?= asset('/js/approvals.js') ?>"></script>

<?php view_end(); ?>
