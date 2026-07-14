<?php view_layout('portal'); view_start('title'); ?><?= t('portal.files.title') ?><?php view_end(); ?>
<?php view_start('content'); ?>
<?php
$maxBytes = (int) ($maxUploadBytes ?? 0);
$maxLabel = $maxBytes > 0 ? number_format($maxBytes / (1024 * 1024), 0) . ' MB' : '';

// Strings usadas dentro do JS (traduzidas no servidor pelo idioma do cliente).
$jsI18n = [
  'loading'              => t('portal.files.loading'),
  'empty_title'          => t('portal.files.empty_title'),
  'empty_hint'           => t('portal.files.empty_hint'),
  'open'                 => t('portal.files.open'),
  'delete'               => t('portal.files.delete'),
  'deleting'             => t('portal.files.deleting'),
  'confirm_delete_file'  => t('portal.files.confirm_delete_file'),
  'confirm_delete_folder'=> t('portal.files.confirm_delete_folder'),
  'status_queued'        => t('portal.files.status_queued'),
  'status_done'          => t('portal.files.status_done'),
  'status_processing'    => t('portal.files.status_processing'),
  'status_canceled'      => t('portal.files.status_canceled'),
  'status_error'         => t('portal.files.status_error'),
  'err_too_large'        => t('portal.files.err_too_large'),
  'err_bad_type'         => t('portal.files.err_bad_type'),
  'err_conn'             => t('portal.files.err_conn'),
  'err_generic'          => t('portal.files.err_generic'),
  'err_invalid_response' => t('portal.files.err_invalid_response'),
  'create_failed'        => t('portal.files.create_failed'),
  'delete_failed'        => t('portal.files.delete_failed'),
  'deleted_file'         => t('portal.files.deleted_file'),
  'deleted_folder'       => t('portal.files.deleted_folder'),
  'undo'                 => t('portal.files.undo'),
  'restored'             => t('portal.files.restored'),
  'restore_failed'       => t('portal.files.restore_failed'),
  'eta_seconds'          => t('portal.files.eta_seconds'),
  'eta_minutes'          => t('portal.files.eta_minutes'),
  'max_label'            => $maxLabel,
];
?>

<div class="mb-6">
  <h1 class="text-xl font-bold text-white"><?= t('portal.files.title') ?></h1>
  <p class="text-sm text-gray-400 mt-0.5"><?= t('portal.files.subtitle') ?></p>
</div>

<?php if (!$connected): ?>
<div class="card p-6 text-center">
  <p class="text-sm text-gray-300 font-medium mb-1"><?= t('portal.files.unavailable_title') ?></p>
  <p class="text-xs text-gray-500"><?= t('portal.files.unavailable_text') ?></p>
</div>
<?php else: ?>

<div x-data="driveManager('<?= e($token) ?>', <?= e(json_encode($jsI18n, JSON_UNESCAPED_UNICODE)) ?>, <?= $maxBytes ?>)" x-init="load(null)">

  <!-- Breadcrumb -->
  <div class="flex items-center gap-1.5 text-sm mb-4 flex-wrap">
    <button @click="goTo(null)" class="text-gray-400 hover:text-white transition-colors flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <?= t('portal.files.home') ?>
    </button>
    <template x-for="crumb in breadcrumb" :key="crumb.id">
      <span class="flex items-center gap-1.5">
        <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <button @click="goTo(crumb.id)" class="text-gray-400 hover:text-white transition-colors" x-text="crumb.name"></button>
      </span>
    </template>
  </div>

  <!-- Toolbar -->
  <div class="flex items-center gap-2 mb-3 flex-wrap">
    <button @click="creatingFolder = true; $nextTick(() => $refs.folderInput?.focus())"
            class="btn-secondary text-sm px-3 py-2 inline-flex items-center gap-1.5">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v4m-2-2h4"/></svg>
      <?= t('portal.files.new_folder') ?>
    </button>

    <label class="btn-primary text-sm px-3 py-2 inline-flex items-center gap-1.5 cursor-pointer">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
      <?= t('portal.files.upload_files') ?>
      <input type="file" multiple accept="image/*,video/*" class="hidden" @change="onFiles($event.target.files); $event.target.value=''">
    </label>
  </div>

  <!-- Dica do que pode enviar. O teto do servidor só vale no fallback via relay
       (UP-01: o caminho normal envia direto pro Drive, sem limite prático). -->
  <p class="text-xs text-gray-500 mb-4">
    <?= t('portal.files.accepted_hint') ?>
  </p>

  <!-- Create folder inline -->
  <div x-show="creatingFolder" x-transition class="card p-3 mb-4 flex items-center gap-2" style="display:none">
    <input x-ref="folderInput" type="text" x-model="newFolderName" placeholder="<?= e(t('portal.files.folder_name_placeholder')) ?>"
           @keydown.enter="createFolder()" @keydown.escape="creatingFolder=false; newFolderName=''"
           class="flex-1 rounded-lg bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
    <button @click="createFolder()" :disabled="!newFolderName.trim() || savingFolder"
            class="btn-primary text-sm px-3 py-2 disabled:opacity-50" x-text="savingFolder ? '<?= e(t('portal.files.creating')) ?>' : '<?= e(t('portal.files.create')) ?>'"></button>
    <button @click="creatingFolder=false; newFolderName=''" class="text-xs text-gray-500 hover:text-gray-300 px-2"><?= t('portal.files.cancel') ?></button>
  </div>

  <!-- Drop zone + listagem -->
  <div @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false"
       @drop.prevent="dragging=false; onFiles($event.dataTransfer.files)"
       class="rounded-2xl border-2 border-dashed transition-colors p-2 sm:p-3"
       :class="dragging ? 'border-violet-500 bg-violet-500/5' : 'border-white/10'">

    <!-- Loading -->
    <div x-show="loading" class="py-10 text-center text-sm text-gray-500" x-text="i18n.loading"></div>

    <template x-if="!loading">
      <div>
        <!-- Empty -->
        <div x-show="folders.length === 0 && files.length === 0 && uploads.length === 0" class="py-12 text-center">
          <svg class="w-10 h-10 text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          <p class="text-sm text-gray-400" x-text="i18n.empty_title"></p>
          <p class="text-xs text-gray-600 mt-1" x-text="i18n.empty_hint"></p>
        </div>

        <!-- Lista -->
        <ul class="divide-y divide-white/5">

          <!-- Pastas -->
          <template x-for="folder in folders" :key="'f'+folder.id">
            <li class="flex items-center gap-3 px-2 py-2.5 hover:bg-white/[0.03] rounded-lg group">
              <button @click="goTo(folder.id)" class="flex items-center gap-3 flex-1 min-w-0 text-left">
                <svg class="w-5 h-5 text-violet-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                <span class="text-sm text-gray-200 truncate" x-text="folder.name"></span>
              </button>
              <button @click="deleteFolder(folder)" :title="i18n.delete"
                      class="opacity-0 group-hover:opacity-100 focus:opacity-100 text-gray-500 hover:text-rose-400 transition-all p-1.5 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </li>
          </template>

          <!-- Uploads em andamento -->
          <template x-for="up in uploads" :key="'u'+up.uid">
            <li class="px-2 py-2.5">
              <div class="flex items-center justify-between gap-2 mb-1.5">
                <span class="text-sm text-gray-300 truncate" x-text="up.name"></span>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <span class="text-[11px]"
                        :class="up.status==='error' ? 'text-rose-400' : (up.status==='done' ? 'text-emerald-400' : 'text-violet-400')"
                        x-text="statusLabel(up)"></span>
                  <button x-show="up.status==='uploading' || up.status==='processing' || up.status==='queued'" @click="cancelUpload(up)"
                          class="text-gray-500 hover:text-rose-400 transition-colors" :title="i18n.delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                  <button x-show="up.status==='error' || up.status==='canceled'" @click="removeUpload(up)"
                          class="text-gray-500 hover:text-white transition-colors" :title="i18n.delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
              <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full transition-all"
                     :class="(up.status==='error'||up.status==='canceled' ? 'bg-rose-500' : (up.status==='done' ? 'bg-emerald-500' : 'bg-violet-500')) + (up.status==='processing' ? ' animate-pulse' : '')"
                     :style="`width: ${(up.status==='done'||up.status==='processing') ? 100 : up.progress}%`"></div>
              </div>
              <p x-show="up.status==='error'" class="text-[11px] text-rose-400 mt-1" x-text="up.error"></p>
              <p x-show="up.status==='uploading' && up.eta" class="text-[10px] text-gray-500 mt-1" x-text="up.eta"></p>
            </li>
          </template>

          <!-- Arquivos -->
          <template x-for="file in files" :key="'x'+file.id">
            <li class="flex items-center gap-3 px-2 py-2.5 hover:bg-white/[0.03] rounded-lg group">
              <button @click="openPreview(file)" class="flex items-center gap-3 flex-1 min-w-0 text-left">
                <span class="w-9 h-9 rounded-lg bg-black/30 flex items-center justify-center flex-shrink-0 overflow-hidden">
                  <template x-if="file.is_image">
                    <img :src="rawUrl(file)" loading="lazy" class="w-full h-full object-cover" @error="$el.style.display='none'">
                  </template>
                  <template x-if="!file.is_image">
                    <svg x-show="file.is_video" class="w-4 h-4 text-violet-300" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  </template>
                  <template x-if="!file.is_image && !file.is_video">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </template>
                </span>
                <span class="min-w-0">
                  <span class="block text-sm text-gray-200 truncate" x-text="file.name"></span>
                  <span class="block text-[11px] text-gray-600" x-text="humanSize(file.size_bytes)"></span>
                </span>
              </button>
              <button @click="deleteFile(file)" :title="i18n.delete"
                      class="opacity-0 group-hover:opacity-100 focus:opacity-100 text-gray-500 hover:text-rose-400 transition-all p-1.5 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </li>
          </template>

        </ul>
      </div>
    </template>
  </div>

  <!-- Confirmação de exclusão (modal nativo do app) -->
  <div x-show="confirmBox.open" x-transition.opacity @keydown.escape.window="confirmCancel()"
       @click.self="confirmCancel()"
       class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background:rgba(0,0,0,.7); display:none">
    <div class="w-full max-w-sm rounded-2xl bg-[#1d1d29] border border-white/10 shadow-xl p-5">
      <div class="flex items-start gap-3 mb-5">
        <span class="w-9 h-9 rounded-full bg-rose-500/15 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </span>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-white mb-1"><?= t('portal.files.confirm_title') ?></p>
          <p class="text-sm text-gray-400 break-words" x-text="confirmBox.message"></p>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button @click="confirmCancel()" class="btn-secondary text-sm px-4 py-2"><?= t('portal.files.cancel') ?></button>
        <button @click="confirmYes()" class="text-sm px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-medium"><?= t('portal.files.delete') ?></button>
      </div>
    </div>
  </div>

  <!-- Toast de exclusão com Desfazer -->
  <div x-show="toast.show" x-transition.opacity
       class="fixed left-1/2 -translate-x-1/2 bottom-20 sm:bottom-6 z-50 w-[calc(100%-2rem)] max-w-md"
       style="display:none">
    <div class="flex items-center gap-3 rounded-xl bg-[#1d1d29] border border-white/10 shadow-lg px-4 py-3">
      <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      <span class="text-sm text-gray-200 flex-1 truncate" x-text="toast.msg"></span>
      <button x-show="toast.restore" @click="undoDelete()" :disabled="toast.busy"
              class="text-sm font-semibold text-violet-300 hover:text-violet-200 disabled:opacity-50 flex-shrink-0"
              x-text="i18n.undo"></button>
      <button @click="hideToast()" class="text-gray-500 hover:text-white flex-shrink-0" title="">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <!-- Lightbox de preview -->
  <div x-show="preview.open" x-transition.opacity @keydown.escape.window="closePreview()"
       @click.self="closePreview()"
       class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.88); display:none">
    <button @click="closePreview()" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="max-w-4xl w-full max-h-[85vh] flex flex-col items-center">
      <template x-if="preview.file && preview.file.is_image">
        <img :src="rawUrl(preview.file)" class="max-h-[80vh] max-w-full object-contain rounded-lg">
      </template>
      <template x-if="preview.file && preview.file.is_video">
        <iframe :src="`https://drive.google.com/file/d/${preview.file.drive_file_id}/preview`"
                class="w-full max-w-3xl aspect-video rounded-lg bg-black" allow="autoplay; fullscreen" allowfullscreen></iframe>
      </template>
      <template x-if="preview.file && !preview.file.is_image && !preview.file.is_video">
        <div class="text-center">
          <p class="text-sm text-gray-300 mb-3" x-text="preview.file.name"></p>
          <a :href="rawUrl(preview.file)" target="_blank" rel="noopener" class="btn-primary px-4 py-2 text-sm inline-flex" x-text="i18n.open"></a>
        </div>
      </template>
      <p class="text-xs text-gray-400 mt-3 text-center" x-text="preview.file ? preview.file.name : ''"></p>
    </div>
  </div>
</div>

<script>
// Registry de XHRs fora do estado reativo do Alpine (evita o Alpine "proxyar" o XMLHttpRequest).
const _driveXhrs = {};
let _driveUploadSeq = 0;
const _DRIVE_MAX_CONCURRENT = 2;
// Chunk do upload direto browser→Drive: o Google exige múltiplo de 256KB.
const _DRIVE_CHUNK = 16 * 1024 * 1024;

function driveManager(token, i18n, maxBytes) {
  return {
    i18n,
    maxBytes: maxBytes || 0,
    folderId: null,
    breadcrumb: [],
    folders: [],
    files: [],
    uploads: [],
    queue: [],
    activeCount: 0,
    loading: false,
    dragging: false,
    creatingFolder: false,
    newFolderName: '',
    savingFolder: false,
    preview: { open: false, file: null },
    toast: { show: false, msg: '', restore: null, busy: false },
    _toastTimer: null,
    confirmBox: { open: false, message: '' },
    _confirmAction: null,

    base() { return `/portal/${token}`; },
    rawUrl(file) { return `${this.base()}/drive/file/${file.id}/raw`; },

    async load(folderId) {
      this.loading = true;
      this.folderId = folderId;
      try {
        const url = `${this.base()}/drive/folders` + (folderId ? `?folder_id=${folderId}` : '');
        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        if (d.success) {
          this.breadcrumb = d.breadcrumb || [];
          this.folders = d.folders || [];
          this.files = d.files || [];
        }
      } catch (e) {}
      this.loading = false;
    },

    goTo(folderId) {
      this.uploads = [];
      this.queue = [];
      this.creatingFolder = false;
      this.load(folderId);
    },

    openPreview(file) { this.preview = { open: true, file }; },
    closePreview() { this.preview = { open: false, file: null }; },

    statusLabel(up) {
      switch (up.status) {
        case 'queued':     return this.i18n.status_queued;
        case 'processing': return this.i18n.status_processing;
        case 'done':       return this.i18n.status_done;
        case 'canceled':   return this.i18n.status_canceled;
        case 'error':      return this.i18n.status_error;
        default:           return up.progress + '%';
      }
    },

    async createFolder() {
      const name = this.newFolderName.trim();
      if (!name || this.savingFolder) return;
      this.savingFolder = true;
      try {
        const r = await fetch(`${this.base()}/drive/folders`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ parent_id: this.folderId, name }),
        });
        const d = await r.json();
        if (d.success) {
          this.folders.push(d.folder);
          this.folders.sort((a, b) => a.name.localeCompare(b.name));
          this.creatingFolder = false;
          this.newFolderName = '';
        } else {
          alert(d.error || this.i18n.create_failed);
        }
      } catch (e) { alert(this.i18n.err_conn); }
      this.savingFolder = false;
    },

    deleteFile(file) {
      this.askConfirm(this.i18n.confirm_delete_file.replace(':name', file.name), () => this.doDeleteFile(file));
    },

    async doDeleteFile(file) {
      try {
        const r = await fetch(`${this.base()}/drive/file/${file.id}/delete`, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const d = await r.json();
        if (d.success) {
          this.files = this.files.filter(f => f.id !== file.id);
          // Toast com "Desfazer" (o arquivo foi pra lixeira do Drive).
          this.showToast(this.i18n.deleted_file, d.restore || null);
        } else {
          alert(d.error || this.i18n.delete_failed);
        }
      } catch (e) { alert(this.i18n.err_conn); }
    },

    deleteFolder(folder) {
      this.askConfirm(this.i18n.confirm_delete_folder.replace(':name', folder.name), () => this.doDeleteFolder(folder));
    },

    async doDeleteFolder(folder) {
      try {
        const r = await fetch(`${this.base()}/drive/folder/${folder.id}/delete`, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const d = await r.json();
        if (d.success) {
          this.folders = this.folders.filter(f => f.id !== folder.id);
          this.showToast(this.i18n.deleted_folder, null);
        } else {
          alert(d.error || this.i18n.delete_failed);
        }
      } catch (e) { alert(this.i18n.err_conn); }
    },

    // Confirmação nativa do app (substitui o confirm() do navegador).
    askConfirm(message, action) {
      this._confirmAction = action;
      this.confirmBox = { open: true, message };
    },
    confirmCancel() {
      this._confirmAction = null;
      this.confirmBox.open = false;
    },
    async confirmYes() {
      const action = this._confirmAction;
      this._confirmAction = null;
      this.confirmBox.open = false;
      if (action) await action();
    },

    showToast(msg, restore) {
      if (this._toastTimer) clearTimeout(this._toastTimer);
      this.toast = { show: true, msg, restore, busy: false };
      this._toastTimer = setTimeout(() => { this.toast.show = false; }, 8000);
    },

    hideToast() {
      if (this._toastTimer) clearTimeout(this._toastTimer);
      this.toast.show = false;
    },

    async undoDelete() {
      const r = this.toast.restore;
      if (!r || this.toast.busy) return;
      this.toast.busy = true;
      try {
        const resp = await fetch(`${this.base()}/drive/file/restore`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify(r),
        });
        const d = await resp.json();
        if (d.success) {
          // Reaparece na lista se ainda estamos na mesma pasta de origem.
          const sameFolder = (r.folder_id ?? null) === (this.folderId ?? null);
          if (sameFolder && d.file) this.files.unshift(d.file);
          this.showToast(this.i18n.restored, null);
        } else {
          alert(d.error || this.i18n.restore_failed);
          this.toast.busy = false;
        }
      } catch (e) { alert(this.i18n.err_conn); this.toast.busy = false; }
    },

    onFiles(fileList) {
      for (const file of fileList) this.enqueue(file);
    },

    enqueue(file) {
      const uid = ++_driveUploadSeq;
      // Validação client-side: só o tipo. Tamanho não bloqueia mais aqui — o
      // caminho direto browser→Drive não tem o teto do servidor; o limite
      // (maxBytes) só vale se cairmos no fallback via relay PHP.
      const typeOk = !file.type || file.type.startsWith('image/') || file.type.startsWith('video/');
      if (!typeOk) {
        this.uploads.push({ uid, name: file.name, progress: 0, status: 'error', error: this.i18n.err_bad_type, eta: '' });
        return;
      }
      this.uploads.push({ uid, name: file.name, progress: 0, status: 'queued', error: null, eta: '', startedAt: 0, file });
      this.queue.push(uid);
      this.pumpQueue();
    },

    pumpQueue() {
      while (this.activeCount < _DRIVE_MAX_CONCURRENT && this.queue.length > 0) {
        const uid = this.queue.shift();
        const entry = this.uploads.find(u => u.uid === uid);
        if (!entry || entry.status !== 'queued') continue;
        this.startUpload(entry);
      }
    },

    /**
     * Orquestra um upload (UP-01): tenta o caminho DIRETO browser→Drive
     * (sessão resumável, sem teto do servidor); se a sessão não puder ser
     * criada, cai no relay PHP — que respeita maxBytes (limite do hosting).
     */
    async startUpload(entry) {
      entry.status = 'uploading';
      entry.startedAt = Date.now();
      this.activeCount++;

      try {
        const uploadUrl = await this.createUploadSession(entry.file);
        if (uploadUrl) {
          await this.uploadDirect(entry, uploadUrl);
        } else if (this.maxBytes > 0 && entry.file.size > this.maxBytes) {
          entry.status = 'error';
          entry.error = this.i18n.err_too_large.replace(':max', this.i18n.max_label || this.humanSize(this.maxBytes));
        } else {
          await this.uploadRelay(entry);
        }
      } catch (e) {
        if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = this.i18n.err_conn; }
      } finally {
        delete _driveXhrs[entry.uid];
        this.activeCount = Math.max(0, this.activeCount - 1);
        this.pumpQueue();
      }
    },

    /** Pede ao servidor a session URI do upload resumável. Null = usar fallback relay. */
    async createUploadSession(file) {
      try {
        const r = await fetch(`${this.base()}/drive/upload/session`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            name: file.name,
            mime: file.type || 'application/octet-stream',
            size: file.size,
            folder_id: this.folderId,
          }),
        });
        if (!r.ok) return null;
        const d = await r.json();
        return (d.success && d.upload_url) ? d.upload_url : null;
      } catch { return null; }
    },

    /** Envia os bytes em chunks direto pra session URI do Google (com retomada). */
    async uploadDirect(entry, uploadUrl) {
      const file = entry.file;
      const total = file.size;
      let offset = 0;
      let attempts = 0;

      while (offset < total) {
        if (entry.status === 'canceled') return;

        const end = Math.min(offset + _DRIVE_CHUNK, total);
        const res = await this.putChunk(entry, uploadUrl, file.slice(offset, end), offset, end, total);

        if (entry.status === 'canceled') return;

        if (res.type === 'progress') { offset = res.next; attempts = 0; continue; }

        if (res.type === 'done') {
          entry.status = 'processing';
          entry.eta = '';
          await this.completeDirect(entry, res.file);
          return;
        }

        // Chunk falhou: espera, pergunta ao Google quanto já foi gravado e retoma.
        attempts++;
        if (attempts > 3) { entry.status = 'error'; entry.error = this.i18n.err_conn; return; }
        await new Promise(r => setTimeout(r, 1000 * attempts));
        const committed = await this.probeOffset(uploadUrl, total);
        if (committed !== null) offset = committed;
      }
    },

    /** PUT de um chunk com Content-Range. 308 = continuar; 200/201 = terminou. */
    putChunk(entry, url, blob, start, end, total) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        _driveXhrs[entry.uid] = xhr;
        xhr.open('PUT', url, true);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${total}`);

        xhr.upload.onprogress = (e) => {
          if (!e.lengthComputable) return;
          const sent = start + e.loaded;
          entry.progress = Math.min(99, Math.round((sent / total) * 100));
          const elapsed = (Date.now() - entry.startedAt) / 1000;
          const rate = sent / Math.max(elapsed, 0.1);
          entry.eta = this.formatEta((total - sent) / Math.max(rate, 1));
        };

        xhr.onload = () => {
          if (xhr.status === 308) {
            // Range: bytes=0-N → próximo byte é N+1. Header ilegível → assume o chunk inteiro.
            const m = /-(\d+)$/.exec(xhr.getResponseHeader('Range') || '');
            resolve({ type: 'progress', next: m ? (parseInt(m[1], 10) + 1) : end });
          } else if (xhr.status === 200 || xhr.status === 201) {
            let file = null;
            try { file = JSON.parse(xhr.responseText); } catch {}
            resolve(file && file.id ? { type: 'done', file } : { type: 'error' });
          } else {
            resolve({ type: 'error' });
          }
        };
        xhr.onerror = () => resolve({ type: 'error' });
        xhr.onabort = () => resolve({ type: 'error' });

        xhr.send(blob);
      });
    },

    /** Pergunta ao Google quantos bytes a sessão já tem (retomada pós-queda). */
    probeOffset(url, total) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url, true);
        xhr.setRequestHeader('Content-Range', `bytes */${total}`);
        xhr.onload = () => {
          if (xhr.status === 308) {
            const m = /-(\d+)$/.exec(xhr.getResponseHeader('Range') || '');
            resolve(m ? (parseInt(m[1], 10) + 1) : 0);
          } else {
            resolve(null);
          }
        };
        xhr.onerror = () => resolve(null);
        xhr.send();
      });
    },

    /** Registra no sistema o arquivo que o Drive confirmou (valida a pasta no servidor). */
    async completeDirect(entry, driveFile) {
      try {
        const r = await fetch(`${this.base()}/drive/upload/complete`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ drive_file_id: driveFile.id, folder_id: this.folderId }),
        });
        const d = await r.json();
        if (r.ok && d.success) {
          entry.status = 'done';
          entry.progress = 100;
          this.files.unshift(d.file);
          setTimeout(() => { this.uploads = this.uploads.filter(u => u.uid !== entry.uid); }, 1500);
        } else {
          entry.status = 'error';
          entry.error = d.error || this.i18n.err_generic;
        }
      } catch {
        entry.status = 'error';
        entry.error = this.i18n.err_conn;
      }
    },

    /** Fallback: multipart via relay PHP (sujeito ao limite do servidor). */
    uploadRelay(entry) {
      return new Promise((resolve) => {
        const uid = entry.uid;
        const file = entry.file;

        const form = new FormData();
        form.append('folder_id', this.folderId ?? '');
        form.append('file', file);

        const xhr = new XMLHttpRequest();
        _driveXhrs[uid] = xhr;
        xhr.open('POST', `${this.base()}/drive/upload`, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
          if (!e.lengthComputable) return;
          entry.progress = Math.round((e.loaded / e.total) * 100);
          const elapsed = (Date.now() - entry.startedAt) / 1000;
          const rate = e.loaded / Math.max(elapsed, 0.1);
          const remain = (e.total - e.loaded) / Math.max(rate, 1);
          entry.eta = this.formatEta(remain);
        };
        xhr.upload.onload = () => { if (entry.status === 'uploading') { entry.status = 'processing'; entry.eta = ''; } };

        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              const d = JSON.parse(xhr.responseText);
              if (d.success) {
                entry.status = 'done';
                entry.progress = 100;
                this.files.unshift(d.file);
                setTimeout(() => { this.uploads = this.uploads.filter(u => u.uid !== uid); }, 1500);
              } else {
                entry.status = 'error';
                entry.error = d.error || this.i18n.err_generic;
              }
            } catch { entry.status = 'error'; entry.error = this.i18n.err_invalid_response; }
          } else if (xhr.status === 0) {
            if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = this.i18n.err_conn; }
          } else {
            entry.status = 'error';
            entry.error = this.i18n.err_generic;
            try { const d = JSON.parse(xhr.responseText); if (d.error) entry.error = d.error; } catch {}
          }
          resolve();
        };
        xhr.onerror = () => { if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = this.i18n.err_conn; } resolve(); };

        xhr.send(form);
      });
    },

    cancelUpload(entry) {
      // Ainda na fila: só tira da fila.
      if (entry.status === 'queued') {
        this.queue = this.queue.filter(id => id !== entry.uid);
        entry.status = 'canceled';
        return;
      }
      const xhr = _driveXhrs[entry.uid];
      if (xhr && (entry.status === 'uploading' || entry.status === 'processing')) {
        entry.status = 'canceled';
        xhr.abort();
        delete _driveXhrs[entry.uid];
      }
    },

    removeUpload(entry) {
      this.uploads = this.uploads.filter(u => u.uid !== entry.uid);
    },

    formatEta(sec) {
      if (!isFinite(sec) || sec < 0) return '';
      if (sec < 60) return this.i18n.eta_seconds.replace(':s', Math.ceil(sec));
      const m = Math.floor(sec / 60);
      return this.i18n.eta_minutes.replace(':m', m);
    },

    humanSize(bytes) {
      if (!bytes) return '';
      const u = ['B', 'KB', 'MB', 'GB'];
      let i = 0, n = bytes;
      while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
      return n.toFixed(n < 10 && i > 0 ? 1 : 0) + ' ' + u[i];
    },
  };
}
</script>

<?php endif; ?>

<?php view_end(); ?>
