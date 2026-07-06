<?php view_layout('portal'); view_start('title'); ?><?= e($plan['title']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels = ['draft' => t('portal.pstatus.draft'), 'pending_approval' => t('portal.pstatus.pending_approval'), 'approved' => t('portal.pstatus.approved'), 'in_revision' => t('portal.pstatus.in_revision'), 'sent' => t('portal.pstatus.sent'), 'revision' => t('portal.pstatus.revision'), 'published' => t('portal.pstatus.published')];
$statusColors = [
  'draft'            => 'text-gray-400 bg-gray-500/10',
  'pending_approval' => 'text-yellow-300 bg-yellow-500/10',
  'sent'             => 'text-yellow-300 bg-yellow-500/10',
  'approved'         => 'text-green-300 bg-green-500/10',
  'in_revision'      => 'text-blue-300 bg-blue-500/10',
  'revision'         => 'text-blue-300 bg-blue-500/10',
  'published'        => 'text-violet-300 bg-violet-500/10',
];
$itemStatusColors = ['draft' => 'text-gray-400 bg-gray-500/10', 'approved' => 'text-green-300 bg-green-500/10', 'revision' => 'text-yellow-300 bg-yellow-500/10', 'rejected' => 'text-red-300 bg-red-500/10'];
$itemStatusLabels = ['draft' => t('portal.istatus.draft'), 'approved' => t('portal.istatus.approved'), 'revision' => t('portal.istatus.revision'), 'rejected' => t('portal.istatus.rejected')];
$platformColors   = ['instagram' => '#E1306C', 'tiktok' => '#010101', 'youtube' => '#FF0000', 'linkedin' => '#0A66C2', 'facebook' => '#1877F2', 'pinterest' => '#E60023'];
$videoTypes       = ['Reels / Vídeo', 'reels', 'Story'];
$planStatus       = $plan['status'];
$canFeedback      = in_array($planStatus, ['sent', 'pending_approval', 'revision', 'in_revision'], true);
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="/portal/<?= $token ?>/planos" class="hover:text-gray-300"><?= t('portal.plan.breadcrumb') ?></a>
  <span>/</span>
  <span class="text-gray-300"><?= e($plan['title']) ?></span>
</nav>

<!-- Header do plano -->
<div class="card p-5 mb-6">
  <div class="flex items-start justify-between gap-4 mb-4">
    <div>
      <h1 class="text-xl font-semibold text-white"><?= e($plan['title']) ?></h1>
      <?php if ($plan['period_label'] ?? null): ?>
      <p class="text-sm text-gray-400 mt-0.5"><?= e($plan['period_label']) ?></p>
      <?php endif; ?>
    </div>
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0 <?= $statusColors[$planStatus] ?? '' ?>">
      <?= $statusLabels[$planStatus] ?? $planStatus ?>
    </span>
  </div>

  <?php if ($plan['description'] ?? null): ?>
  <p class="text-sm text-gray-400 mb-4 leading-relaxed"><?= e($plan['description']) ?></p>
  <?php endif; ?>

  <!-- Ações de aprovação do plano -->
  <?php if (in_array($planStatus, ['sent', 'pending_approval'], true)): ?>
  <div class="flex flex-wrap gap-3 pt-4 border-t border-white/[0.06]" x-data="{showRevision: false}">
    <form method="POST" action="/portal/<?= $token ?>/planos/<?= $plan['id'] ?>/aprovar">
      <?= csrf_field() ?>
      <button type="submit" class="btn-primary text-sm px-5 py-2.5 gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <?= t('portal.plan.approve_full') ?>
      </button>
    </form>
    <button @click="showRevision = !showRevision" class="btn-secondary text-sm px-4 py-2.5">
      <?= t('portal.plan.request_revision') ?>
    </button>

    <div x-show="showRevision" x-transition class="w-full mt-2">
      <form method="POST" action="/portal/<?= $token ?>/planos/<?= $plan['id'] ?>/revisao">
        <?= csrf_field() ?>
        <textarea name="comment" rows="3" placeholder="<?= e(t('portal.plan.revision_placeholder')) ?>"
                  class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 resize-none"
                  style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);"></textarea>
        <div class="flex justify-end mt-2">
          <button type="submit" class="btn-primary text-sm px-4 py-2"><?= t('portal.plan.send_revision') ?></button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Posts -->
<?php if (!empty($items)): ?>
<h2 class="text-sm font-semibold text-gray-300 mb-3"><?= t('portal.plan.posts') ?> (<?= count($items) ?>)</h2>
<div class="space-y-4">
  <?php foreach ($items as $idx => $item):
    $parsedDrive = $item['drive_parsed'] ?? null;
    $isVideo     = in_array($item['content_type'] ?? '', $videoTypes);
    $pColor      = $platformColors[$item['platform'] ?? ''] ?? null;
    $feedbacks   = $item['feedbacks'] ?? [];
    $itemStatus  = $item['status'] ?? 'draft';
    $imagesList  = $item['images_list'] ?? [];
    // Detect YouTube URL for timecode auto-capture
    $youtubeId = null;
    if (!empty($item['drive_url'])) {
        preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $item['drive_url'], $ytm);
        $youtubeId = $ytm[1] ?? null;
    }
  ?>
  <div class="card overflow-hidden"
       x-data="portalItem(<?= $idx ?>, '<?= $token ?>', <?= $plan['id'] ?>, <?= $item['id'] ?>, <?= htmlspecialchars(json_encode($feedbacks), ENT_QUOTES) ?>, '<?= htmlspecialchars($itemStatus, ENT_QUOTES) ?>', <?= $youtubeId ? "'" . e($youtubeId) . "'" : 'null' ?>)"
       x-init="initYt()"

    <!-- Card header — sempre visível -->
    <div class="flex items-start gap-3 p-4 cursor-pointer" @click="expanded = !expanded">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <?php if ($pColor): ?>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold text-white" style="background:<?= $pColor ?>">
            <?= ucfirst(e($item['platform'])) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($item['content_type'])): ?>
          <span class="text-xs text-violet-300 bg-violet-500/10 px-2 py-0.5 rounded-full">
            <?= e($item['content_type']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($item['publish_date'])): ?>
          <span class="text-xs text-gray-500">
            <?= date('d/m/Y', strtotime($item['publish_date'])) ?>
            <?= !empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '' ?>
          </span>
          <?php endif; ?>
        </div>
        <?php $preview = mb_substr($item['caption'] ?? '', 0, 80); ?>
        <p class="text-sm text-gray-300 truncate"><?= $preview ? e($preview) . (mb_strlen($item['caption'] ?? '') > 80 ? '…' : '') : t('portal.plan.no_caption') ?></p>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <span :class="statusClass" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" x-text="statusLabel"></span>
        <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{'rotate-90': expanded}"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </div>
    </div>

    <!-- Conteúdo expandido -->
    <div x-show="expanded" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="border-t border-white/[0.06]">

      <div class="p-4 space-y-4">
        <!-- Capa -->
        <?php if (!empty($item['cover_url'])): ?>
        <div class="rounded-xl overflow-hidden">
          <img src="<?= e(\App\Services\GoogleDriveService::imageSrc($item['cover_url'])) ?>" alt="Capa"
               class="w-full object-cover max-h-80"
               onerror="this.parentElement.style.display='none'">
        </div>
        <?php endif; ?>

        <!-- Vídeo YouTube -->
        <?php if ($youtubeId): ?>
        <div class="rounded-xl overflow-hidden border border-white/10 bg-black/30">
          <div class="flex items-center gap-2 px-3 py-2 border-b border-white/5">
            <svg class="w-4 h-4 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M21.582 7.18a2.721 2.721 0 00-1.917-1.93C18.005 5 12 5 12 5s-6.004 0-7.665.25A2.721 2.721 0 002.418 7.18C2.17 8.847 2 10.423 2 12s.17 3.153.418 4.82a2.721 2.721 0 001.917 1.93C6 19 12 19 12 19s6.005 0 7.665-.25a2.721 2.721 0 001.917-1.93C21.83 15.153 22 13.577 22 12s-.17-3.153-.418-4.82zM9.954 15.22V8.78L15.477 12 9.954 15.22z"/></svg>
            <span class="text-xs text-gray-400">YouTube</span>
          </div>
          <div class="aspect-video">
            <iframe id="yt-<?= $item['id'] ?>"
                    src="https://www.youtube.com/embed/<?= e($youtubeId) ?>?enablejsapi=1&origin=<?= urlencode(rtrim(env('APP_URL',''), '/')) ?>"
                    class="w-full h-full border-0" loading="lazy" allowfullscreen></iframe>
          </div>
        </div>
        <!-- Vídeo Drive -->
        <?php elseif ($parsedDrive && $parsedDrive['valid'] && ($isVideo || $parsedDrive['file_type'] === 'video')): ?>
        <div class="rounded-xl overflow-hidden border border-white/10 bg-black/30">
          <div class="flex items-center gap-2 px-3 py-2 border-b border-white/5">
            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-xs text-gray-400"><?= t('portal.plan.video') ?></span>
            <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
               class="ml-auto text-xs text-violet-400 hover:text-violet-300"><?= t('portal.plan.open_drive') ?></a>
          </div>
          <div class="aspect-video">
            <iframe src="<?= e($parsedDrive['embed_url']) ?>"
                    class="w-full h-full border-0" loading="lazy" allowfullscreen></iframe>
          </div>
        </div>
        <?php elseif ($parsedDrive && $parsedDrive['valid'] && $parsedDrive['file_type'] === 'image'): ?>
        <!-- Imagem do Drive — preview inline (antes só mostrava link) -->
        <div class="rounded-xl overflow-hidden">
          <img src="<?= e(\App\Services\GoogleDriveService::imageSrc($parsedDrive['original'])) ?>" alt="Imagem"
               class="w-full object-cover max-h-80"
               onerror="this.parentElement.style.display='none'">
        </div>
        <?php elseif ($parsedDrive && $parsedDrive['valid']): ?>
        <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 text-xs text-violet-400 hover:text-violet-300">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
          <?= t('portal.plan.see_file_drive') ?>
        </a>
        <?php endif; ?>

        <!-- Carrossel -->
        <?php if (!empty($imagesList)): ?>
        <div class="space-y-2">
          <?php foreach ($imagesList as $imgUrl): if (empty($imgUrl)) continue; ?>
          <div class="rounded-xl overflow-hidden">
            <img src="<?= e(\App\Services\GoogleDriveService::imageSrc($imgUrl)) ?>" alt="Slide" class="w-full object-cover max-h-80"
                 onerror="this.parentElement.style.display='none'">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Legenda -->
        <?php if (!empty($item['caption'])): ?>
        <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-line"><?= e($item['caption']) ?></p>
        <?php endif; ?>

        <!-- ── Feedbacks existentes ───────────────────────────────────────── -->
        <div class="pt-2 border-t border-white/[0.06]">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2"><?= t('portal.plan.comments') ?></p>
          <div class="space-y-2">
            <template x-if="feedbacks.length === 0">
              <p class="text-xs text-gray-600 italic"><?= t('portal.plan.no_comments') ?></p>
            </template>
            <template x-for="fb in feedbacks" :key="fb.id ?? fb.created_at">
              <div :class="feedbackBgClass(fb)"
                   class="rounded-xl border border-white/[0.06] p-3">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-semibold text-white"
                          x-text="fb.client_name || fb.user_name || <?= e(json_encode(t('portal.plan.you'), JSON_UNESCAPED_UNICODE)) ?>"></span>
                    <span :class="feedbackTypeClass(fb)"
                          class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                          x-text="feedbackTypeLabel(fb)"></span>
                    <template x-if="fb.timecode">
                      <span class="inline-flex items-center gap-1 text-[10px] text-violet-400 bg-violet-500/10 px-1.5 py-0.5 rounded-full font-mono">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="fb.timecode"></span>
                      </span>
                    </template>
                  </div>
                  <span class="text-[10px] text-gray-600 flex-shrink-0" x-text="formatDate(fb.created_at)"></span>
                </div>
                <p x-show="fb.comment" class="text-sm text-gray-300 leading-relaxed" x-text="fb.comment"></p>
              </div>
            </template>
          </div>
        </div>

        <!-- ── Deixar feedback ────────────────────────────────────────────── -->
        <?php if ($canFeedback): ?>
        <div class="pt-2 border-t border-white/[0.06]" x-show="!submitted">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3"><?= t('portal.plan.leave_feedback') ?></p>

          <!-- Tipo -->
          <div class="flex flex-wrap gap-2 mb-3">
            <button type="button"
                    @click="selectedType = 'approved'"
                    :class="selectedType === 'approved' ? 'bg-emerald-600 border-emerald-500 text-white' : 'border-white/10 text-gray-400 hover:text-white'"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              <?= t('portal.plan.fb_approve') ?>
            </button>
            <button type="button"
                    @click="selectedType = 'changes_requested'"
                    :class="selectedType === 'changes_requested' ? 'bg-amber-600 border-amber-500 text-white' : 'border-white/10 text-gray-400 hover:text-white'"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <?= t('portal.plan.fb_request_change') ?>
            </button>
            <button type="button"
                    @click="selectedType = 'comment'"
                    :class="selectedType === 'comment' ? 'bg-violet-600 border-violet-500 text-white' : 'border-white/10 text-gray-400 hover:text-white'"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <?= t('portal.plan.fb_comment') ?>
            </button>
          </div>

          <div x-show="selectedType !== null">
            <!-- Textarea -->
            <textarea x-model="comment" rows="2"
                      :placeholder="selectedType === 'approved' ? <?= e(json_encode(t('portal.plan.note_optional'), JSON_UNESCAPED_UNICODE)) ?> : <?= e(json_encode(t('portal.plan.describe_change'), JSON_UNESCAPED_UNICODE)) ?>"
                      class="w-full rounded-xl text-sm text-white placeholder-gray-600 px-3 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-violet-500/50"
                      style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);"></textarea>

            <!-- Timecode (só para vídeos) -->
            <?php if ($isVideo): ?>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
              <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <input type="text" x-model="timecode" placeholder="<?= e(t('portal.plan.timecode_placeholder')) ?>"
                     class="w-24 rounded-lg text-xs text-white placeholder-gray-600 px-2.5 py-1.5 font-mono focus:outline-none focus:ring-1 focus:ring-violet-500/50"
                     style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);">
              <?php if ($youtubeId): ?>
              <button type="button" @click="captureYtTime()"
                      title="<?= e(t('portal.plan.capture_title')) ?>"
                      class="inline-flex items-center gap-1 rounded-lg bg-red-600/20 border border-red-500/30 px-2.5 py-1.5 text-xs text-red-300 hover:bg-red-600/30 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <?= t('portal.plan.capture') ?>
              </button>
              <?php else: ?>
              <span class="text-xs text-gray-600"><?= t('portal.plan.video_excerpt') ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-2 mt-3">
              <button type="button" @click="submitFeedback()"
                      :disabled="sending"
                      class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white transition-all hover:bg-violet-500 disabled:opacity-50">
                <svg x-show="sending" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="sending ? <?= e(json_encode(t('portal.plan.sending'), JSON_UNESCAPED_UNICODE)) ?> : <?= e(json_encode(t('portal.plan.send'), JSON_UNESCAPED_UNICODE)) ?>"></span>
              </button>
              <button type="button" @click="selectedType = null; comment = ''; timecode = ''"
                      class="text-xs text-gray-500 hover:text-gray-300 transition-colors"><?= t('portal.plan.cancel') ?></button>
              <span x-show="errorMsg" class="text-xs text-rose-400" x-text="errorMsg"></span>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /p-4 -->
    </div><!-- /expanded -->
  </div><!-- /card -->
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card p-8 text-center text-gray-500 text-sm"><?= t('portal.plan.no_posts') ?></div>
<?php endif; ?>

<script>
const PORTAL_TOKEN = '<?= e($token) ?>';
const PLAN_ID      = <?= $plan['id'] ?>;

const STATUS_LABELS = {
  draft:    <?= json_encode(t('portal.istatus.draft'), JSON_UNESCAPED_UNICODE) ?>,
  approved: <?= json_encode(t('portal.istatus.approved'), JSON_UNESCAPED_UNICODE) ?>,
  revision: <?= json_encode(t('portal.istatus.revision'), JSON_UNESCAPED_UNICODE) ?>,
  rejected: <?= json_encode(t('portal.istatus.rejected'), JSON_UNESCAPED_UNICODE) ?>,
};
const STATUS_CLASSES = {
  draft:    'text-gray-400 bg-gray-500/10',
  approved: 'text-green-300 bg-green-500/10',
  revision: 'text-yellow-300 bg-yellow-500/10',
  rejected: 'text-red-300 bg-red-500/10',
};

// YouTube IFrame API bootstrap — loads once, resolves when ready
let _ytApiReady = false;
let _ytApiCallbacks = [];
function ensureYtApi() {
  return new Promise(resolve => {
    if (_ytApiReady) return resolve();
    _ytApiCallbacks.push(resolve);
    if (!document.getElementById('yt-api-script')) {
      const s = document.createElement('script');
      s.id  = 'yt-api-script';
      s.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(s);
    }
  });
}
window.onYouTubeIframeAPIReady = () => {
  _ytApiReady = true;
  _ytApiCallbacks.forEach(fn => fn());
  _ytApiCallbacks = [];
};

function portalItem(idx, token, planId, itemId, initialFeedbacks, initialStatus, youtubeId = null) {
  return {
    expanded:     false,
    feedbacks:    initialFeedbacks,
    itemStatus:   initialStatus,
    selectedType: null,
    comment:      '',
    timecode:     '',
    sending:      false,
    submitted:    false,
    errorMsg:     '',
    _ytPlayer:    null,

    initYt() {
      if (!youtubeId) return;
      ensureYtApi().then(() => {
        this._ytPlayer = new YT.Player(`yt-${itemId}`, {
          events: { onReady: () => {} }
        });
      });
    },

    captureYtTime() {
      if (!this._ytPlayer || typeof this._ytPlayer.getCurrentTime !== 'function') {
        this.errorMsg = <?= json_encode(t('portal.plan.play_before_capture'), JSON_UNESCAPED_UNICODE) ?>;
        setTimeout(() => this.errorMsg = '', 3000);
        return;
      }
      const sec = Math.floor(this._ytPlayer.getCurrentTime());
      this.timecode = Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
    },

    get statusLabel() { return STATUS_LABELS[this.itemStatus] ?? this.itemStatus; },
    get statusClass()  { return STATUS_CLASSES[this.itemStatus] ?? ''; },

    feedbackBgClass(fb) {
      const map = {
        approved:          'bg-green-500/[0.07]',
        changes_requested: 'bg-amber-500/[0.07]',
        rejected:          'bg-red-500/[0.07]',
        comment:           'bg-white/[0.03]',
      };
      return map[fb.feedback_type] ?? map.comment;
    },
    feedbackTypeLabel(fb) {
      const map = { approved: <?= json_encode(t('portal.ftype.approved'), JSON_UNESCAPED_UNICODE) ?>, changes_requested: <?= json_encode(t('portal.ftype.changes_requested'), JSON_UNESCAPED_UNICODE) ?>, rejected: <?= json_encode(t('portal.ftype.rejected'), JSON_UNESCAPED_UNICODE) ?>, comment: <?= json_encode(t('portal.ftype.comment'), JSON_UNESCAPED_UNICODE) ?> };
      return map[fb.feedback_type] ?? fb.feedback_type;
    },
    feedbackTypeClass(fb) {
      const map = {
        approved:          'text-green-300 bg-green-500/20',
        changes_requested: 'text-amber-300 bg-amber-500/20',
        rejected:          'text-red-300 bg-red-500/20',
        comment:           'text-gray-400 bg-white/10',
      };
      return map[fb.feedback_type] ?? map.comment;
    },
    formatDate(dt) {
      if (!dt) return '';
      const d = new Date(dt.replace(' ', 'T'));
      return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
           + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    },

    async submitFeedback() {
      this.errorMsg = '';
      if (!this.selectedType) return;
      this.sending = true;
      try {
        const r = await fetch(
          `/portal/${PORTAL_TOKEN}/planos/${PLAN_ID}/items/${itemId}/feedback`,
          {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
              feedback_type: this.selectedType,
              comment:       this.comment,
              timecode:      this.timecode,
            }),
          }
        );
        const d = await r.json();
        if (d.success) {
          this.feedbacks.push(d.feedback);
          const statusMap = { approved: 'approved', changes_requested: 'revision', rejected: 'rejected' };
          if (statusMap[this.selectedType]) this.itemStatus = statusMap[this.selectedType];
          this.selectedType = null;
          this.comment      = '';
          this.timecode     = '';
        } else {
          this.errorMsg = d.error ?? <?= json_encode(t('portal.plan.send_error'), JSON_UNESCAPED_UNICODE) ?>;
        }
      } catch { this.errorMsg = <?= json_encode(t('portal.plan.conn_error'), JSON_UNESCAPED_UNICODE) ?>; }
      this.sending = false;
    },
  };
}
</script>

<?php view_end(); ?>
