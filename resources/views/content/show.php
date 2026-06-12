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

$clientTz = $plan['client_timezone'] ?? 'America/Sao_Paulo';

$platforms = [
  ['id' => 'instagram', 'label' => 'Instagram', 'color' => '#E1306C',
   'path' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z'],
  ['id' => 'tiktok', 'label' => 'TikTok', 'color' => '#010101',
   'path' => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z'],
  ['id' => 'youtube', 'label' => 'YouTube', 'color' => '#FF0000',
   'path' => 'M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z'],
  ['id' => 'linkedin', 'label' => 'LinkedIn', 'color' => '#0A66C2',
   'path' => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z'],
  ['id' => 'facebook', 'label' => 'Facebook', 'color' => '#1877F2',
   'path' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z'],
  ['id' => 'pinterest', 'label' => 'Pinterest', 'color' => '#E60023',
   'path' => 'M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z'],
];
$platformColors = array_column($platforms, 'color', 'id');
$postTypes = ['Reels / Vídeo', 'Feed Estático', 'Carrossel', 'Story'];
?>

<div x-data="contentShow(<?= $plan['id'] ?>)"
     @open-edit-post.window="openEditPost($event.detail.item)"
     class="min-h-screen">

  <!-- ── Breadcrumb ──────────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-center gap-2 text-sm text-gray-400">
    <a href="/conteudo" class="hover:text-white transition-colors">Planos</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-300 truncate max-w-xs"><?= e($plan['title']) ?></span>
  </div>

  <!-- ── Plan header ──────────────────────────────────────────────────────────── -->
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

  <!-- ── Posts section ──────────────────────────────────────────────────────── -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4">
    <h2 class="text-lg font-semibold text-white">
      Posts
      <span class="ml-2 rounded-full bg-violet-500/20 px-2.5 py-0.5 text-xs font-medium text-violet-300"><?= count($plan['items']) ?></span>
    </h2>
    <?php if ($canCreate): ?>
    <button @click="openAddPost()"
            class="inline-flex items-center gap-2 rounded-xl bg-white/5 border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Adicionar Post
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($plan['items'])): ?>
  <div class="rounded-2xl border border-dashed border-white/10 py-16 text-center">
    <div class="mx-auto mb-4 w-12 h-12 rounded-2xl bg-violet-500/10 flex items-center justify-center">
      <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <p class="text-gray-400 text-sm mb-3">Nenhum post neste plano.</p>
    <?php if ($canCreate): ?>
    <button @click="openAddPost()" class="text-sm text-violet-400 hover:text-violet-300 transition-colors">
      Adicionar primeiro post →
    </button>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div class="space-y-3" id="items-list">
    <?php foreach ($plan['items'] as $item):
      $isc = $statusColors[$item['status']] ?? $statusColors['draft'];
      $iLabel = ContentPlanService::itemStatusLabel($item['status']);
      $parsedDrive = $item['drive_parsed'];
      $pColor = $platformColors[$item['platform'] ?? ''] ?? null;
      $captionPreview = $item['caption'] ?? $item['title'] ?? '';
      if (mb_strlen($captionPreview) > 90) $captionPreview = mb_substr($captionPreview, 0, 90) . '…';
    ?>
    <div class="item-card group rounded-2xl border border-white/5 bg-white/[0.03] transition-all duration-200 hover:border-violet-500/20 hover:bg-white/[0.05]"
         x-data="itemCard(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)"
         data-id="<?= $item['id'] ?>">

      <!-- Item header -->
      <div class="flex items-start gap-3 p-4 cursor-pointer" @click="expanded = !expanded">

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
              <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                <?php if ($pColor): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold text-white" style="background:<?= $pColor ?>">
                  <?= ucfirst(e($item['platform'])) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($item['publish_date'])): ?>
                <span class="text-xs text-gray-500">
                  <?= date('d/m (D)', strtotime($item['publish_date'])) ?>
                  <?= !empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '' ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($item['content_type'])): ?>
                <span class="text-xs text-violet-400"><?= e($item['content_type']) ?></span>
                <?php endif; ?>
              </div>
              <p class="text-sm font-medium text-white truncate">
                <?= e($captionPreview ?: 'Post sem legenda') ?>
              </p>
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

        <!-- Cover image -->
        <?php if (!empty($item['cover_url'])): ?>
        <div class="rounded-xl overflow-hidden border border-white/5">
          <img src="<?= e($item['cover_url']) ?>" alt="Capa"
               class="w-full object-cover max-h-64"
               onerror="this.parentElement.style.display='none'">
        </div>
        <?php endif; ?>

        <!-- Caption -->
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

  <!-- ── Add / Edit Post Modal ──────────────────────────────────────────────── -->
  <div x-show="itemModal.show" x-transition.opacity
       class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
       style="display:none">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="itemModal.show = false"></div>
    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-white/10 bg-gray-950 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

      <div class="flex items-center justify-between p-5 border-b border-white/5">
        <h3 class="text-base font-semibold text-white"
            x-text="itemModal.mode === 'edit' ? 'Editar Post' : 'Adicionar Post'"></h3>
        <button @click="itemModal.show = false" class="text-gray-400 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form @submit.prevent="submitPost()" class="p-5 space-y-5">

        <!-- Platform -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-2">Plataforma</label>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($platforms as $p): ?>
            <button type="button"
                    @click="itemModal.platform = '<?= $p['id'] ?>'"
                    :class="itemModal.platform === '<?= $p['id'] ?>'
                      ? 'ring-2 ring-violet-400 ring-offset-2 ring-offset-gray-950 border-violet-500/40 bg-white/10'
                      : 'border-white/10 bg-white/[0.04] hover:bg-white/10'"
                    class="flex flex-col items-center gap-1.5 rounded-xl border p-2.5 w-[4.25rem] transition-all">
              <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background:<?= $p['color'] ?>">
                <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px">
                  <path d="<?= htmlspecialchars($p['path']) ?>"/>
                </svg>
              </div>
              <span class="text-[10px] text-gray-400"><?= $p['label'] ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Post type -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-2">Formato</label>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($postTypes as $pt): ?>
            <button type="button"
                    @click="itemModal.content_type = '<?= $pt ?>'"
                    :class="itemModal.content_type === '<?= $pt ?>'
                      ? 'bg-violet-600 border-violet-500 text-white'
                      : 'border-white/10 bg-white/[0.04] text-gray-400 hover:text-white hover:bg-white/10'"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition-all">
              <?= $pt ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Date + Time -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Data de Publicação</label>
            <input type="date" x-model="itemModal.publish_date"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
            <p class="text-xs text-gray-600 mt-1">Fuso: <?= e($clientTz) ?></p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Horário</label>
            <input type="time" x-model="itemModal.publish_time"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          </div>
        </div>

        <!-- Cover / Photo -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">
            <span x-text="itemModal.content_type === 'Reels / Vídeo' || itemModal.content_type === 'Story' ? 'Foto de capa' : 'Foto'"></span>
          </label>
          <input type="url" x-model="itemModal.cover_url"
                 placeholder="https://..."
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          <p class="text-xs text-gray-600 mt-1">URL da imagem (Drive, CDN ou outro host público)</p>
          <!-- Preview -->
          <div x-show="itemModal.cover_url" class="mt-2 rounded-xl overflow-hidden border border-white/10" style="display:none">
            <img :src="itemModal.cover_url" alt="Preview"
                 class="w-full object-cover max-h-52"
                 @error="$el.parentElement.style.display='none'"
                 @load="$el.parentElement.style.display='block'">
          </div>
        </div>

        <!-- Caption -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Legenda</label>
          <textarea x-model="itemModal.caption" rows="4" placeholder="Texto da publicação..."
                    class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50 resize-none"></textarea>
        </div>

        <!-- Drive link -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">
            <span x-text="itemModal.content_type === 'Reels / Vídeo' ? 'Link do Drive (vídeo)' : 'Link do Drive'"></span>
          </label>
          <input type="url" x-model="itemModal.drive_url"
                 placeholder="https://drive.google.com/..."
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
          <p x-show="itemModal.content_type === 'Reels / Vídeo'" class="text-xs text-violet-400 mt-1">
            O vídeo será exibido com player integrado na tela de aprovação do cliente.
          </p>
          <p x-show="itemModal.content_type !== 'Reels / Vídeo'" class="text-xs text-gray-600 mt-1">
            Suporta arquivos, pastas, Docs, Sheets e Slides
          </p>
        </div>

        <!-- Assigned to -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Responsável</label>
          <select x-model="itemModal.assigned_to"
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
            <option value="">Sem responsável</option>
            <?php foreach ($teamMembers as $member): ?>
            <option value="<?= e($member['id']) ?>"><?= e($member['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="submit" :disabled="submitting"
                  class="flex-1 rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-violet-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-text="submitting ? 'Salvando...' : (itemModal.mode === 'edit' ? 'Salvar alterações' : 'Adicionar Post')"></span>
          </button>
          <button type="button" @click="itemModal.show = false"
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

const emptyModal = () => ({
  show: false, mode: 'add', editId: null,
  platform: '', content_type: '', publish_date: '', publish_time: '',
  cover_url: '', caption: '', drive_url: '', assigned_to: ''
});

function contentShow(planId) {
  return {
    itemModal: emptyModal(),
    sending: false,
    submitting: false,
    toast: { show: false, msg: '', ok: true },

    openAddPost() {
      this.itemModal = emptyModal();
      this.itemModal.show = true;
    },

    openEditPost(item) {
      this.itemModal = {
        show: true, mode: 'edit', editId: item.id,
        platform:     item.platform      || '',
        content_type: item.content_type  || '',
        publish_date: item.publish_date  || '',
        publish_time: item.publish_time  ? String(item.publish_time).substring(0, 5) : '',
        cover_url:    item.cover_url     || '',
        caption:      item.caption       || '',
        drive_url:    item.drive_url     || '',
        assigned_to:  item.assigned_to   ? String(item.assigned_to) : '',
      };
    },

    async submitPost() {
      this.submitting = true;
      const isEdit = this.itemModal.mode === 'edit';
      try {
        const url = isEdit
          ? `/conteudo/${planId}/items/${this.itemModal.editId}`
          : `/conteudo/${planId}/items`;
        const r = await fetch(url, {
          method: isEdit ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body: JSON.stringify({
            platform:     this.itemModal.platform,
            content_type: this.itemModal.content_type,
            publish_date: this.itemModal.publish_date,
            publish_time: this.itemModal.publish_time,
            cover_url:    this.itemModal.cover_url,
            caption:      this.itemModal.caption,
            drive_url:    this.itemModal.drive_url,
            assigned_to:  this.itemModal.assigned_to,
          })
        });
        const d = await r.json();
        if (d.success) {
          this.showToast(isEdit ? 'Post atualizado!' : 'Post adicionado!', true);
          this.itemModal.show = false;
          setTimeout(() => location.reload(), 600);
        } else {
          this.showToast(d.error || 'Erro ao salvar.', false);
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
      this.$dispatch('open-edit-post', { item: this.item });
    },

    async deleteItem() {
      if (!confirm('Excluir este post?')) return;
      const r = await fetch(`/conteudo/${PLAN_ID}/items/${item.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': CSRF }
      });
      const d = await r.json();
      if (d.success) this.$el.remove();
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
