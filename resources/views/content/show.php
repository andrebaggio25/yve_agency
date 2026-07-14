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

$clientTz      = $plan['client_timezone'] ?? 'America/Sao_Paulo';
$portalToken   = $plan['client_portal_token'] ?? null;
$approvalUrl   = $portalToken
    ? rtrim(env('APP_URL', ''), '/') . '/portal/' . $portalToken . '/planos/' . $plan['id']
    : null;

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

// ── Grade semanal (seg→dom) ─────────────────────────────────────────────────
// A semana do plano vira a superfície de organização: cada post cai no seu
// dia, quantos couberem por dia. Itens legados fora do intervalo (planos
// antigos) e itens sem data ganham faixas próprias — nada some da tela.
$weekDays = [];
$cursor   = strtotime((string) $plan['week_start']);
$endTs    = strtotime((string) $plan['week_end']);
while ($cursor !== false && $endTs !== false && $cursor <= $endTs && count($weekDays) < 14) {
    $weekDays[] = date('Y-m-d', $cursor);
    $cursor     = strtotime('+1 day', $cursor);
}

$dowShort = [1 => 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

$itemsByDay = [];
$noDate     = [];
$outOfWeek  = [];
foreach ($plan['items'] as $it) {
    $d = $it['publish_date'] ?? null;
    if (!$d) {
        $noDate[] = $it;
    } elseif (in_array($d, $weekDays, true)) {
        $itemsByDay[$d][] = $it;
    } else {
        $outOfWeek[] = $it;
    }
}
$daysWithPost = count(array_intersect($weekDays, array_keys($itemsByDay)));

// Payload mínimo que o modal de edição precisa (o item completo carrega
// feedbacks inteiros — peso desnecessário num atributo).
$modalPayload = static fn(array $it): string => htmlspecialchars(json_encode([
    'id'           => $it['id'],
    'platform'     => $it['platform'] ?? '',
    'content_type' => $it['content_type'] ?? '',
    'publish_date' => $it['publish_date'] ?? '',
    'publish_time' => $it['publish_time'] ?? '',
    'cover_url'    => $it['cover_url'] ?? '',
    'caption'      => $it['caption'] ?? '',
    'drive_url'    => $it['drive_url'] ?? '',
    'assigned_to'  => $it['assigned_to'] ?? '',
    'images_list'  => $it['images_list'] ?? [],
    'title'        => $it['title'] ?? '',
    'theme'        => $it['theme'] ?? '',
    'script'       => $it['script'] ?? '',
    'cta'          => $it['cta'] ?? '',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
?>

<div id="content-show"
     data-plan-id="<?= (int) $plan['id'] ?>"
     data-client-name="<?= e($plan['client_name'] ?? 'agencia') ?>"
     data-client-username="<?= e(strtolower(preg_replace('/[^a-z0-9_.]/i', '', $plan['client_name'] ?? 'usuario'))) ?>"
     x-data="contentShow(<?= (int) $plan['id'] ?>)"
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
          <span class="text-xs text-gray-400"><?= e($plan['client_name']) ?></span>
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
                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-gray-950 shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-500 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" :class="{'animate-spin': sending}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
          <span x-text="sending ? 'Enviando...' : 'Enviar para Aprovação'"></span>
        </button>
        <?php endif; ?>
        <?php if ($approvalUrl): ?>
        <button x-data="{copied:false}"
                @click="navigator.clipboard.writeText('<?= e($approvalUrl) ?>').then(() => { copied=true; setTimeout(()=>copied=false,2000) })"
                :class="copied ? 'border-emerald-500/40 text-emerald-300' : 'border-white/10 text-gray-300 hover:text-white hover:border-white/20'"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-all">
          <!-- Um <path> com `d` reativo. <template x-if> DENTRO de <svg> é HTML
               inválido: o parser tira o template do namespace SVG e o Alpine
               estoura com "cloneNode of undefined" — o ícone nunca alternava. -->
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  :d="copied
                        ? 'M5 13l4 4L19 7'
                        : 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z'"/>
          </svg>
          <span x-text="copied ? 'Copiado!' : 'Link de Aprovação'"></span>
        </button>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <a href="/conteudo/<?= e($plan['id']) ?>/editar"
           class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:border-white/20 transition-all">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          Editar
        </a>
        <?php endif; ?>

        <?php if (\App\Support\Auth::can('content.delete') && ($plan['status'] ?? '') !== 'approved'): ?>
        <!-- Excluir: só antes da aprovação. Plano aprovado é registro do que a
             cliente autorizou — o backend recusa, e a UI nem oferece. -->
        <form method="POST" action="/conteudo/<?= e($plan['id']) ?>" class="inline"
              onsubmit="return confirm('Excluir a planificação &quot;<?= e(addslashes($plan['title'] ?? '')) ?>&quot; e todos os seus itens? Esta ação não pode ser desfeita.')">
          <?= csrf_field() ?>
          <?= method_field('DELETE') ?>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-rose-500/20 px-4 py-2 text-sm font-medium text-rose-300 hover:text-rose-200 hover:border-rose-500/40 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Excluir
          </button>
        </form>
        <?php endif; ?>

        <?php if (\App\Support\Auth::can('content.create')):
          // PROD-05: a cópia leva só a ESTRUTURA e nasce em rascunho na
          // segunda-feira seguinte — o atalho para não refazer a semana do zero.
          $nextFrom = date('d/m', strtotime($plan['week_start'] . ' +7 days'));
          $nextTo   = date('d/m', strtotime($plan['week_end'] . ' +7 days'));
        ?>
        <form method="POST" action="/conteudo/<?= e($plan['id']) ?>/duplicar" class="inline"
              onsubmit="return confirm('Planejar a semana de <?= $nextFrom ?> a <?= $nextTo ?> a partir deste plano? A cópia leva a grade (dias, horários, plataformas, formatos e responsáveis) — mas NÃO o conteúdo dos posts (legenda, roteiro, mídia), que você escreve do zero. Nasce como rascunho.')">
          <?= csrf_field() ?>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:border-white/20 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Planejar próxima semana
          </button>
        </form>
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
        <div class="text-xs text-gray-400 mt-0.5"><?= $st['label'] ?></div>
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
        <div class="h-full rounded-full bg-gradient-to-r from-brand-600 via-brand-400 to-emerald-400 transition-all duration-700"
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
    <div class="flex items-center gap-3 flex-wrap">
      <h2 class="text-lg font-semibold text-white">
        Posts
        <span class="ml-2 rounded-full bg-brand-500/20 px-2.5 py-0.5 text-xs font-medium text-brand-300"><?= count($plan['items']) ?></span>
      </h2>
      <!-- O termômetro da semana: dia sem post é pauta faltando. -->
      <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium <?= $daysWithPost >= 7 ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' ?>">
        <span class="inline-block w-1.5 h-1.5 rounded-full <?= $daysWithPost >= 7 ? 'bg-emerald-400' : 'bg-amber-400' ?>"></span>
        <?= $daysWithPost ?> de 7 dias com post
      </span>
    </div>
    <div class="flex items-center gap-2">
      <!-- Toggle Semana | Lista (default Semana; persiste em localStorage) -->
      <div class="flex items-center rounded-xl border border-white/10 bg-white/[0.03] p-0.5" role="group" aria-label="Modo de visualização">
        <button type="button" @click="setView('week')"
                :class="view === 'week' ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white'"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all">Semana</button>
        <button type="button" @click="setView('list')"
                :class="view === 'list' ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white'"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all">Lista</button>
      </div>
      <?php if ($canCreate): ?>
      <button @click="openAddPost()"
              class="inline-flex items-center gap-2 rounded-xl bg-white/5 border border-white/10 px-4 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Adicionar Post
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Visão Semana: 7 colunas seg→dom, N posts por dia ───────────────────── -->
  <div x-show="view === 'week'" x-cloak>
    <div class="overflow-x-auto pb-2">
      <div class="grid grid-cols-7 gap-2 min-w-[56rem]">
        <?php foreach ($weekDays as $day):
          $dayItems = $itemsByDay[$day] ?? [];
          $isToday  = $day === date('Y-m-d');
          $dow      = $dowShort[(int) date('N', strtotime($day))] ?? '';
        ?>
        <div class="rounded-2xl border <?= $isToday ? 'border-brand-500/40 bg-brand-500/[0.04]' : 'border-white/5 bg-white/[0.02]' ?> p-2 flex flex-col min-h-[9rem]"
             data-day="<?= e($day) ?>">
          <div class="flex items-baseline justify-between px-1 pb-2">
            <span class="text-xs font-semibold <?= $isToday ? 'text-brand-300' : 'text-gray-300' ?>"><?= $dow ?></span>
            <span class="text-[11px] <?= $isToday ? 'text-brand-400' : 'text-gray-400' ?>"><?= date('d/m', strtotime($day)) ?></span>
          </div>

          <div class="space-y-1.5 flex-1">
            <?php foreach ($dayItems as $item):
              $isc    = $statusColors[$item['status']] ?? $statusColors['draft'];
              $pColor = $platformColors[$item['platform'] ?? ''] ?? '#6b7280';
              $chipTitle = trim((string) ($item['title'] ?: ($item['content_type'] ?? 'Post')));
            ?>
            <button type="button"
                    <?php if ($canEdit): ?>@click="openEditPost(<?= $modalPayload($item) ?>)"<?php else: ?>@click="goToItem(<?= (int) $item['id'] ?>)"<?php endif; ?>
                    class="w-full rounded-xl border border-white/5 bg-white/[0.04] p-2 text-left transition-all hover:border-brand-500/30 hover:bg-white/[0.08]"
                    aria-label="<?= e($chipTitle . ' — ' . $dow . ' ' . date('d/m', strtotime($day))) ?>">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0 <?= $isc['dot'] ?>" title="<?= ContentPlanService::itemStatusLabel($item['status']) ?>"></span>
                <?php if (!empty($item['publish_time'])): ?>
                <span class="text-[10px] font-semibold text-gray-300"><?= substr($item['publish_time'], 0, 5) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['platform'])): ?>
                <span class="ml-auto inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold text-white" style="background:<?= $pColor ?>"><?= ucfirst(e($item['platform'])) ?></span>
                <?php endif; ?>
              </div>
              <p class="text-[11px] text-gray-200 leading-snug line-clamp-2"><?= e($chipTitle ?: 'Post') ?></p>
              <?php if (!empty($item['content_type'])): ?>
              <p class="text-[10px] text-brand-400 mt-0.5"><?= e($item['content_type']) ?></p>
              <?php endif; ?>
            </button>
            <?php endforeach; ?>
          </div>

          <?php if ($canCreate): ?>
          <button type="button" @click="openAddPost('<?= e($day) ?>')"
                  class="mt-1.5 w-full rounded-xl border border-dashed <?= empty($dayItems) ? 'border-white/15 py-4' : 'border-white/10 py-1.5' ?> text-xs text-gray-400 hover:text-brand-300 hover:border-brand-500/40 transition-all"
                  aria-label="Adicionar post em <?= $dow ?> <?= date('d/m', strtotime($day)) ?>">
            + Post
          </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($noDate) || !empty($outOfWeek)): ?>
    <div class="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-3">
      <p class="text-xs font-medium text-amber-300 mb-2">
        <?= !empty($outOfWeek) ? 'Posts sem data ou fora da semana do plano — clique para reagendar:' : 'Posts ainda sem dia definido — clique para encaixar na semana:' ?>
      </p>
      <div class="flex flex-wrap gap-2">
        <?php foreach (array_merge($noDate, $outOfWeek) as $item):
          $chipTitle = trim((string) ($item['title'] ?: ($item['content_type'] ?? 'Post')));
        ?>
        <button type="button"
                <?php if ($canEdit): ?>@click="openEditPost(<?= $modalPayload($item) ?>)"<?php else: ?>@click="goToItem(<?= (int) $item['id'] ?>)"<?php endif; ?>
                class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs text-gray-300 hover:text-white hover:border-brand-500/30 transition-all">
          <?= e($chipTitle ?: 'Post') ?>
          <?php if (!empty($item['publish_date'])): ?>
          <span class="text-gray-400 ml-1"><?= date('d/m', strtotime($item['publish_date'])) ?></span>
          <?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Visão Lista (detalhe completo por post) ─────────────────────────────── -->
  <div x-show="view === 'list'" x-cloak>
  <?php if (empty($plan['items'])): ?>
  <div class="rounded-2xl border border-dashed border-white/10 py-16 text-center">
    <div class="mx-auto mb-4 w-12 h-12 rounded-2xl bg-brand-500/10 flex items-center justify-center">
      <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <p class="text-gray-400 text-sm mb-3">Nenhum post neste plano.</p>
    <?php if ($canCreate): ?>
    <button @click="openAddPost()" class="text-sm text-brand-400 hover:text-brand-300 transition-colors">
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
      $frameClass = ContentPlanService::previewFrameClass($item['content_type'] ?? null);
      $isVertical = ContentPlanService::previewRatio($item['content_type'] ?? null) === '9/16';
    ?>
    <div class="item-card group rounded-2xl border border-white/5 bg-white/[0.03] transition-all duration-200 hover:border-brand-500/20 hover:bg-white/[0.05]"
         x-data="itemCard(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)"
         id="item-<?= (int) $item['id'] ?>"
         data-id="<?= $item['id'] ?>">

      <!-- Item header -->
      <div class="flex items-start gap-3 p-4 cursor-pointer" @click="expanded = !expanded">

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
                <span class="text-xs text-gray-400">
                  <?= date('d/m (D)', strtotime($item['publish_date'])) ?>
                  <?= !empty($item['publish_time']) ? ' · ' . substr($item['publish_time'], 0, 5) : '' ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($item['content_type'])): ?>
                <span class="text-xs text-brand-400"><?= e($item['content_type']) ?></span>
                <?php endif; ?>
              </div>
              <p class="text-sm font-medium text-white truncate">
                <?= e($captionPreview ?: 'Post sem legenda') ?>
              </p>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
              <?php if ($canEdit): ?>
              <button @click.stop="openEdit()"
                      class="opacity-0 group-hover:opacity-100 rounded-lg p-1.5 text-gray-400 hover:text-white hover:bg-white/10 transition-all"
                      title="Editar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button @click.stop="deleteItem()"
                      class="opacity-0 group-hover:opacity-100 rounded-lg p-1.5 text-gray-400 hover:text-rose-400 hover:bg-rose-500/10 transition-all"
                      title="Excluir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
              <?php endif; ?>
              <?php if ($parsedDrive && $parsedDrive['valid']): ?>
              <span class="flex-shrink-0 rounded-full p-1 bg-brand-500/10">
                <?php
                $iconMap = ['video'=>'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z','image'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'];
                $iconPath = $iconMap[$parsedDrive['file_type']] ?? 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                ?>
                <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

              <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
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
        <div>
          <p class="text-xs font-medium text-gray-400 mb-1.5">
            <?= $isVertical ? 'Capa (9:16)' : 'Foto (3:4)' ?>
          </p>
          <div class="relative overflow-hidden rounded-xl border border-white/5 bg-black/30 w-full <?= $frameClass ?>">
            <img src="<?= e(GoogleDriveService::imageSrc($item['cover_url'])) ?>" alt="Capa"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="lazy"
                 onerror="this.closest('div').parentElement.style.display='none'">
          </div>
          <a href="<?= e($item['cover_url']) ?>" target="_blank" rel="noopener"
             class="mt-1.5 inline-block text-xs text-brand-400 hover:text-brand-300 transition-colors">
            Ver em tamanho real ↗
          </a>
        </div>
        <?php endif; ?>

        <!-- Carousel slides -->
        <?php if (!empty($item['images_list'])): ?>
        <div>
          <p class="text-xs font-medium text-gray-400 mb-1.5">
            Carrossel (<?= count($item['images_list']) ?> <?= count($item['images_list']) === 1 ? 'foto' : 'fotos' ?>)
          </p>
          <div class="flex gap-3 overflow-x-auto pb-2">
            <?php foreach ($item['images_list'] as $slideIdx => $imgUrl): if (empty($imgUrl)) continue; ?>
            <div class="relative flex-shrink-0 w-40 aspect-[3/4] overflow-hidden rounded-xl border border-white/5 bg-black/30">
              <img src="<?= e(GoogleDriveService::imageSrc($imgUrl)) ?>" alt="Slide <?= $slideIdx + 1 ?>"
                   class="absolute inset-0 w-full h-full object-cover"
                   loading="lazy"
                   onerror="this.parentElement.style.display='none'">
              <span class="absolute top-1.5 right-1.5 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">
                <?= $slideIdx + 1 ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
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
            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <span class="text-xs text-gray-400">Google Drive —
              <?= e(app(GoogleDriveService::class)->getTypeLabel($parsedDrive['file_type'])) ?>
            </span>
            <a href="<?= e($parsedDrive['original']) ?>" target="_blank" rel="noopener"
               class="ml-auto text-xs text-brand-400 hover:text-brand-300 transition-colors">
              Abrir →
            </a>
          </div>
          <div class="aspect-video">
            <iframe src="<?= e($parsedDrive['embed_url']) ?>"
                    class="w-full h-full border-0" loading="lazy" allowfullscreen></iframe>
          </div>
        </div>
        <?php endif; ?>

        <!-- Instagram preview buttons -->
        <?php if (!empty($item['cover_url'])): ?>
        <div class="flex items-center gap-2 flex-wrap" x-data>
          <span class="text-xs text-gray-400">Simular:</span>
          <button type="button"
                  @click="$dispatch('open-insta-feed', {item: <?= htmlspecialchars(json_encode(['id'=>$item['id'],'cover_url'=>$item['cover_url'],'caption'=>$item['caption']??'','platform'=>$item['platform']??'','content_type'=>$item['content_type']??'']), ENT_QUOTES) ?>})"
                  class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-purple-500/20 to-pink-500/20 border border-pink-500/20 px-3 py-1.5 text-xs font-medium text-pink-300 hover:from-purple-500/30 hover:to-pink-500/30 transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm0 8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm8-8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zm0 8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            Feed
          </button>
          <button type="button"
                  @click="$dispatch('open-insta-profile', {item: <?= htmlspecialchars(json_encode(['id'=>$item['id'],'cover_url'=>$item['cover_url'],'caption'=>$item['caption']??'','platform'=>$item['platform']??'','content_type'=>$item['content_type']??'']), ENT_QUOTES) ?>})"
                  class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-purple-500/20 to-pink-500/20 border border-pink-500/20 px-3 py-1.5 text-xs font-medium text-pink-300 hover:from-purple-500/30 hover:to-pink-500/30 transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Perfil
          </button>
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
            <div class="flex items-center justify-between gap-2 mb-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-semibold">
                  <?= e($fb['source'] === 'client' ? ($fb['client_name'] ?? 'Cliente') : ($fb['user_name'] ?? 'Equipe')) ?>
                </span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold
                  <?= $fb['source'] === 'client' ? 'text-brand-300 bg-brand-500/20' : 'text-gray-400 bg-white/10' ?>">
                  <?= $fb['source'] === 'client' ? 'Cliente' : 'Equipe' ?>
                </span>
                <?php if (!empty($fb['timecode_seconds'])): ?>
                <span class="inline-flex items-center gap-1 text-[10px] text-brand-400 bg-brand-500/10 px-1.5 py-0.5 rounded-full font-mono">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?= sprintf('%d:%02d', intdiv((int)$fb['timecode_seconds'], 60), (int)$fb['timecode_seconds'] % 60) ?>
                </span>
                <?php endif; ?>
              </div>
              <span class="text-xs opacity-60 flex-shrink-0"><?= date('d/m H:i', strtotime($fb['created_at'])) ?></span>
            </div>
            <p class="text-xs font-medium mb-1"><?= $fbLabel ?></p>
            <?php if (!empty($fb['comment'])): ?>
            <p class="text-sm opacity-80"><?= e($fb['comment']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Nota interna da equipe por item -->
        <?php if ($canEdit): ?>
        <div x-data="itemNote(<?= $item['id'] ?>)" class="pt-1">
          <div x-show="!writing">
            <button @click="writing = true"
                    class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-brand-400 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Adicionar nota interna
            </button>
          </div>
          <div x-show="writing" x-transition>
            <textarea x-model="note" rows="2" placeholder="Nota visível apenas para a equipe..."
                      @keydown.escape="writing = false; note = ''"
                      class="w-full rounded-xl bg-white/[0.03] border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 resize-none mt-1"></textarea>
            <div class="flex items-center gap-2 mt-1.5">
              <button @click="submitNote()" :disabled="sending"
                      class="rounded-lg bg-white/10 border border-white/10 px-3 py-1 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/20 transition-all disabled:opacity-50">
                <span x-text="sending ? 'Salvando...' : 'Salvar nota'"></span>
              </button>
              <button @click="writing = false; note = ''" class="text-xs text-gray-400 hover:text-gray-400 transition-colors">Cancelar</button>
              <span x-show="saved" class="text-xs text-emerald-400">✓ Salvo</span>
            </div>
          </div>
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
  </div><!-- /visão lista -->

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
                      ? 'ring-2 ring-brand-400 ring-offset-2 ring-offset-gray-950 border-brand-500/40 bg-white/10'
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
                      ? 'bg-brand-600 border-brand-500 text-gray-950'
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
            <!-- min/max = semana do plano; a guarda real é o 422 do backend. -->
            <input type="date" aria-label="Data de publicação" x-model="itemModal.publish_date"
                   min="<?= e($plan['week_start']) ?>" max="<?= e($plan['week_end']) ?>"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
            <p class="text-xs text-gray-400 mt-1">Semana: <?= date('d/m', strtotime($plan['week_start'])) ?>–<?= date('d/m', strtotime($plan['week_end'])) ?> · Fuso: <?= e($clientTz) ?></p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Horário</label>
            <input type="time" aria-label="Horário de publicação" x-model="itemModal.publish_time"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
          </div>
        </div>

        <!-- Título + Tema (existiam no banco, mas a tela não capturava) -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Título do Post</label>
            <input type="text" aria-label="Título do post" x-model="itemModal.title" placeholder="Ex: Bastidores do atendimento"
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1.5">Tema</label>
            <input type="text" aria-label="Tema do post" x-model="itemModal.theme" placeholder="Ex: autoridade, engajamento..."
                   class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
          </div>
        </div>

        <!-- Cover / Photo -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">
            <span x-text="isVertical() ? 'Foto de capa (9:16)' : 'Foto (3:4)'"></span>
          </label>
          <input type="url" x-model="itemModal.cover_url"
                 @input="imgErr = false"
                 placeholder="https://..."
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
          <p class="text-xs text-gray-400 mt-1">URL da imagem (Drive, CDN ou outro host público)</p>
          <!-- Preview: enquadrado na proporção real do Instagram -->
          <template x-if="itemModal.cover_url && !imgErr">
            <div class="relative mt-2 w-full overflow-hidden rounded-xl border border-white/10 bg-black/30"
                 :class="frameClass()">
              <img :src="driveImageUrl(itemModal.cover_url)" alt="Preview"
                   class="absolute inset-0 w-full h-full object-cover"
                   @error="imgErr = true">
            </div>
          </template>
          <template x-if="itemModal.cover_url && imgErr">
            <p class="text-xs text-amber-400 mt-1">Prévia indisponível — verifique se o link é público e direto (não uma página do Drive).</p>
          </template>
        </div>

        <!-- Carousel images -->
        <div x-show="itemModal.content_type === 'Carrossel'" style="display:none">
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Fotos do Carrossel</label>
          <div class="space-y-2">
            <template x-for="(url, idx) in itemModal.images" :key="idx">
              <div class="flex items-center gap-2">
                <input type="url" x-model="itemModal.images[idx]"
                       placeholder="https://..."
                       class="flex-1 rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                <button type="button" @click="itemModal.images.splice(idx, 1)"
                        class="p-2 rounded-lg text-gray-400 hover:text-rose-400 hover:bg-rose-500/10 transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </template>
          </div>
          <button type="button" @click="itemModal.images.push('')"
                  class="mt-2 inline-flex items-center gap-1.5 text-xs text-brand-400 hover:text-brand-300 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Adicionar foto
          </button>
        </div>

        <!-- Caption -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Legenda</label>
          <textarea x-model="itemModal.caption" rows="4" placeholder="Texto da publicação..."
                    class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 resize-none"></textarea>
        </div>

        <!-- Roteiro (vídeo) + CTA -->
        <div x-show="itemModal.content_type === 'Reels / Vídeo' || itemModal.script" style="display:none">
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Roteiro</label>
          <textarea x-model="itemModal.script" rows="3" placeholder="Roteiro do vídeo: ganchos, cenas, falas..."
                    class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 resize-none"></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">CTA (chamada para ação)</label>
          <input type="text" aria-label="CTA" x-model="itemModal.cta" placeholder="Ex: Agende pelo link da bio"
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
        </div>

        <!-- Drive link -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">
            <span x-text="itemModal.content_type === 'Reels / Vídeo' ? 'Link do Drive (vídeo)' : 'Link do Drive'"></span>
          </label>
          <input type="url" x-model="itemModal.drive_url"
                 placeholder="https://drive.google.com/..."
                 class="w-full rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
          <p x-show="itemModal.content_type === 'Reels / Vídeo'" class="text-xs text-brand-400 mt-1">
            O vídeo será exibido com player integrado na tela de aprovação do cliente.
          </p>
          <p x-show="itemModal.content_type !== 'Reels / Vídeo'" class="text-xs text-gray-400 mt-1">
            Suporta arquivos, pastas, Docs, Sheets e Slides
          </p>
        </div>

        <!-- Assigned to -->
        <div>
          <label class="block text-xs font-medium text-gray-400 mb-1.5">Responsável</label>
          <select aria-label="Responsável" x-model="itemModal.assigned_to"
                  class="w-full rounded-xl bg-white/5 border border-white/10 text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
            <option value="">Sem responsável</option>
            <?php foreach ($teamMembers as $member): ?>
            <option value="<?= e($member['id']) ?>"><?= e($member['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="submit" :disabled="submitting"
                  class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-gray-950 transition-all hover:bg-brand-500 disabled:opacity-50 disabled:cursor-not-allowed">
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

  <!-- ── Chat do Plano ──────────────────────────────────────────────────────── -->
  <div class="mt-8 rounded-2xl border border-white/5 bg-white/[0.03]"
       x-data="planChat(<?= $plan['id'] ?>)"
       x-init="loadComments()">
    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
      <h2 class="text-base font-semibold text-white flex items-center gap-2">
        <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        Chat do Plano
        <span class="text-xs font-normal text-gray-400">· visível apenas para a equipe</span>
      </h2>
      <button @click="loadComments()" class="text-xs text-gray-400 hover:text-gray-400 transition-colors">
        <svg class="w-3.5 h-3.5" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
      </button>
    </div>

    <!-- Mensagens -->
    <div class="px-5 py-4 space-y-3 max-h-80 overflow-y-auto" x-ref="chatMessages">
      <template x-if="comments.length === 0 && !loading">
        <p class="text-xs text-gray-400 text-center py-4">Nenhuma mensagem ainda. Inicie a conversa!</p>
      </template>
      <template x-for="c in comments" :key="c.id">
        <div class="flex items-start gap-2.5">
          <div class="w-7 h-7 rounded-full bg-brand-500/20 flex items-center justify-center flex-shrink-0 text-xs font-bold text-brand-300"
               x-text="(c.user_name || '?').charAt(0).toUpperCase()"></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2 mb-0.5">
              <span class="text-xs font-semibold text-white" x-text="c.user_name"></span>
              <span class="text-[10px] text-gray-400" x-text="chatDate(c.created_at)"></span>
            </div>
            <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap" x-text="c.message"></p>
          </div>
        </div>
      </template>
    </div>

    <!-- Input -->
    <div class="px-5 pb-4 pt-2 border-t border-white/5">
      <div class="flex gap-2">
        <textarea x-model="newMessage" rows="2"
                  placeholder="Escreva uma mensagem para a equipe..."
                  @keydown.ctrl.enter.prevent="sendComment()"
                  class="flex-1 rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 resize-none"></textarea>
        <button @click="sendComment()" :disabled="sending || !newMessage.trim()"
                class="self-end rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-gray-950 transition-all hover:bg-brand-500 disabled:opacity-40 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" :class="{'animate-pulse': sending}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </button>
      </div>
      <p class="text-[10px] text-gray-400 mt-1">Ctrl+Enter para enviar</p>
    </div>
  </div>

</div><!-- /x-data -->

<!-- SEM defer, de propósito: o Alpine (defer, no <head>) executa ANTES de
     qualquer script defer do body e chama Alpine.start() — se este módulo
     ainda não tiver definido a função do x-data, o componente morre com
     "ReferenceError: ... is not defined". Script clássico no body executa
     durante o parse, portanto antes do Alpine. -->
<script src="<?= asset('/js/content-editor.js') ?>"></script>

<!-- Instagram preview modal -->
<div x-data="instaPreview()"
     @open-insta-feed.window="open('feed', $event.detail.item)"
     @open-insta-profile.window="open('profile', $event.detail.item)">

  <div x-show="show" x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
       @click.self="close()"
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       style="background:rgba(0,0,0,0.85); display:none">

    <!-- Phone shell -->
    <!-- Simulador do Instagram: é uma RÉPLICA visual (logotipo, ícones de curtir/
         comentar são enfeite). aria-hidden para o leitor de tela não anunciar
         controles que não existem; o conteúdo real do post está na tela por trás. -->
    <div aria-hidden="true" class="relative w-[360px] max-h-[85vh] overflow-hidden rounded-[2.5rem] border-2 border-gray-700 bg-white shadow-2xl flex flex-col"
         style="min-height:600px">

      <!-- Status bar -->
      <div class="flex items-center justify-between px-6 pt-3 pb-1 bg-white" style="font-size:11px;color:#1a1a1a">
        <span class="font-semibold">9:41</span>
        <div class="flex items-center gap-1.5">
          <svg class="w-4 h-3" viewBox="0 0 24 16" fill="#1a1a1a"><rect x="0" y="4" width="4" height="12" rx="1"/><rect x="6" y="2" width="4" height="14" rx="1"/><rect x="12" y="0" width="4" height="16" rx="1"/><rect x="18" y="3" width="4" height="10" rx="1" opacity=".3"/></svg>
          <svg class="w-4 h-3" viewBox="0 0 24 16" fill="#1a1a1a"><path d="M12 2C7.03 2 2.5 4.1 0 7.5l2.5 2.5C4.3 7.1 7.9 5 12 5s7.7 2.1 9.5 5L24 7.5C21.5 4.1 17 2 12 2zm0 4c-3.1 0-5.8 1.3-7.7 3.4L7 12c1.2-1.4 2.9-2.3 5-2.3s3.8.9 5 2.3l2.7-2.6C17.8 7.3 15.1 6 12 6z"/></svg>
          <svg class="w-6 h-3" viewBox="0 0 32 16" fill="#1a1a1a"><rect x="0" y="1" width="27" height="14" rx="3" stroke="#1a1a1a" stroke-width="1.5" fill="none"/><rect x="1.5" y="2.5" width="22" height="11" rx="2" fill="#1a1a1a"/><rect x="28" y="5" width="4" height="6" rx="2" fill="#1a1a1a" opacity=".4"/></svg>
        </div>
      </div>

      <!-- IG Top bar -->
      <div class="flex items-center justify-between px-4 py-2 bg-white border-b border-gray-100">
        <template x-if="mode === 'feed'">
          <div class="flex items-center gap-3">
            <svg class="w-24" viewBox="0 0 100 30"><text y="24" font-family="Billabong,cursive" font-size="28" fill="#1a1a1a">Instagram</text></svg>
          </div>
        </template>
        <template x-if="mode === 'profile'">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span class="font-semibold text-sm text-gray-800" x-text="username"></span>
          </div>
        </template>
        <div class="flex items-center gap-3 ml-auto">
          <svg class="w-5 h-5 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
          <svg class="w-5 h-5 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </div>
      </div>

      <!-- Feed mode -->
      <div x-show="mode === 'feed'" class="flex-1 overflow-y-auto bg-white">
        <!-- Post -->
        <div>
          <!-- Post header -->
          <div class="flex items-center gap-3 px-3 py-2.5">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-yellow-400 p-0.5">
              <div class="w-full h-full rounded-full bg-white flex items-center justify-center overflow-hidden">
                <span class="text-xs font-bold text-gray-500" x-text="username.charAt(0).toUpperCase()"></span>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-gray-800" x-text="username"></p>
              <p class="text-[10px] text-gray-500">Publicidade</p>
            </div>
            <svg class="w-5 h-5 text-gray-500 ml-auto" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
          </div>
          <!-- Image -->
          <div class="w-full bg-gray-200 relative overflow-hidden" :class="feedAspect()">
            <img x-show="item && item._img" :src="item && item._img"
                 class="w-full h-full object-cover"
                 @error="this.style.display='none'">
            <div x-show="!item || !item._img" class="absolute inset-0 flex items-center justify-center bg-gray-100">
              <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
          </div>
          <!-- Actions -->
          <div class="px-3 py-2">
            <div class="flex items-center gap-4 mb-2">
              <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
              <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
              <svg class="w-6 h-6 text-gray-800 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </div>
            <p class="text-xs font-semibold text-gray-800 mb-0.5">2.847 curtidas</p>
            <div class="text-xs text-gray-500">
              <span class="font-semibold" x-text="username"></span>
              <span x-text="' ' + (item && item.caption ? item.caption.substring(0,80) : 'Legenda do post...')"></span>
            </div>
            <p class="text-[10px] text-gray-500 mt-1 uppercase tracking-wide">Há 1 hora</p>
          </div>
        </div>
      </div>

      <!-- Profile mode -->
      <div x-show="mode === 'profile'" class="flex-1 overflow-y-auto bg-white">
        <!-- Profile header -->
        <div class="px-4 pt-3 pb-4">
          <div class="flex items-center gap-5 mb-4">
            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-yellow-400 p-0.5 flex-shrink-0">
              <div class="w-full h-full rounded-full bg-white flex items-center justify-center overflow-hidden">
                <span class="text-xl font-bold text-gray-500" x-text="username.charAt(0).toUpperCase()"></span>
              </div>
            </div>
            <div class="flex gap-5 text-center flex-1">
              <div><p class="text-sm font-bold text-gray-900">24</p><p class="text-[10px] text-gray-500">posts</p></div>
              <div><p class="text-sm font-bold text-gray-900">1,8k</p><p class="text-[10px] text-gray-500">seguidores</p></div>
              <div><p class="text-sm font-bold text-gray-900">312</p><p class="text-[10px] text-gray-500">seguindo</p></div>
            </div>
          </div>
          <p class="text-xs font-semibold text-gray-800" x-text="username"></p>
          <p class="text-[11px] text-gray-500 mt-0.5">✨ Link na bio</p>
          <div class="mt-2 flex gap-2">
            <button class="flex-1 rounded-lg bg-gray-100 text-xs font-semibold text-gray-800 py-1.5">Seguir</button>
            <button class="flex-1 rounded-lg bg-gray-100 text-xs font-semibold text-gray-800 py-1.5">Mensagem</button>
            <button class="w-8 rounded-lg bg-gray-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
          </div>
        </div>
        <!-- Stories -->
        <div class="flex gap-3 px-4 pb-4 overflow-x-auto">
          <template x-for="i in [1,2,3,4,5]" :key="i">
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
              <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 p-0.5">
                <div class="w-full h-full rounded-full bg-gray-200"></div>
              </div>
              <span class="text-[9px] text-gray-500">destaques</span>
            </div>
          </template>
        </div>
        <!-- Grid tab -->
        <div class="flex border-t border-gray-200">
          <div class="flex-1 flex justify-center py-2 border-b-2 border-gray-800">
            <svg class="w-5 h-5 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm0 8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm8-8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zm0 8a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
          </div>
          <div class="flex-1 flex justify-center py-2">
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
        </div>
        <!-- 3x3 grid -->
        <div class="grid grid-cols-3 gap-0.5">
          <template x-for="(img, idx) in gridImages()" :key="idx">
            <div class="relative aspect-square overflow-hidden"
                 :class="idx === 4 ? 'ring-2 ring-brand-500 ring-inset' : ''">
              <img x-show="img" :src="img" class="w-full h-full object-cover" @error="this.style.display='none'">
              <div x-show="!img" class="w-full h-full bg-gray-100"></div>
              <div x-show="idx === 4" class="absolute top-1 right-1 bg-brand-500 text-gray-950 text-[8px] font-bold px-1 rounded">NOVO</div>
            </div>
          </template>
        </div>
      </div>

      <!-- Close button -->
      <button @click="close()" aria-label="Fechar"
              class="absolute top-4 right-4 w-8 h-8 rounded-full bg-black/50 flex items-center justify-center text-white hover:bg-black/70 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <!-- Label -->
      <div class="absolute bottom-0 left-0 right-0 text-center py-2 bg-white border-t border-gray-100">
        <p class="text-[10px] text-gray-500">Simulação — não representa o Instagram real</p>
      </div>
    </div>
  </div>
</div>

<?php view_end(); ?>
