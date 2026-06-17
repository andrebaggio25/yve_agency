<?php view_layout('portal'); view_start('title'); ?>Enviar conteúdos<?php view_end(); ?>
<?php view_start('content'); ?>

<div class="mb-6">
  <h1 class="text-xl font-bold text-white">Enviar conteúdos</h1>
  <p class="text-sm text-gray-400 mt-0.5">Organize em pastas e envie seus vídeos e fotos. Tudo vai direto para o nosso acervo.</p>
</div>

<?php if (!$connected): ?>
<div class="card p-6 text-center">
  <p class="text-sm text-gray-300 font-medium mb-1">Envio indisponível no momento</p>
  <p class="text-xs text-gray-500">A agência ainda não habilitou o envio de arquivos. Fale com a gente.</p>
</div>
<?php else: ?>

<div x-data="driveManager('<?= e($token) ?>')" x-init="load(null)">

  <!-- Breadcrumb -->
  <div class="flex items-center gap-1.5 text-sm mb-4 flex-wrap">
    <button @click="goTo(null)" class="text-gray-400 hover:text-white transition-colors flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Início
    </button>
    <template x-for="crumb in breadcrumb" :key="crumb.id">
      <span class="flex items-center gap-1.5">
        <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <button @click="goTo(crumb.id)" class="text-gray-400 hover:text-white transition-colors" x-text="crumb.name"></button>
      </span>
    </template>
  </div>

  <!-- Toolbar -->
  <div class="flex items-center gap-2 mb-4 flex-wrap">
    <button @click="creatingFolder = true; $nextTick(() => $refs.folderInput?.focus())"
            class="btn-secondary text-sm px-3 py-2 inline-flex items-center gap-1.5">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v4m-2-2h4"/></svg>
      Nova pasta
    </button>

    <label class="btn-primary text-sm px-3 py-2 inline-flex items-center gap-1.5 cursor-pointer">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
      Enviar arquivos
      <input type="file" multiple class="hidden" @change="onFiles($event.target.files); $event.target.value=''">
    </label>
  </div>

  <!-- Create folder inline -->
  <div x-show="creatingFolder" x-transition class="card p-3 mb-4 flex items-center gap-2" style="display:none">
    <input x-ref="folderInput" type="text" x-model="newFolderName" placeholder="Nome da pasta (ex: Dia 15, Modelo Ana...)"
           @keydown.enter="createFolder()" @keydown.escape="creatingFolder=false; newFolderName=''"
           class="flex-1 rounded-lg bg-white/5 border border-white/10 text-white placeholder-gray-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
    <button @click="createFolder()" :disabled="!newFolderName.trim() || savingFolder"
            class="btn-primary text-sm px-3 py-2 disabled:opacity-50" x-text="savingFolder ? 'Criando...' : 'Criar'"></button>
    <button @click="creatingFolder=false; newFolderName=''" class="text-xs text-gray-500 hover:text-gray-300 px-2">Cancelar</button>
  </div>

  <!-- Drop zone + listing -->
  <div @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false"
       @drop.prevent="dragging=false; onFiles($event.dataTransfer.files)"
       class="rounded-2xl border-2 border-dashed transition-colors p-4"
       :class="dragging ? 'border-violet-500 bg-violet-500/5' : 'border-white/10'">

    <!-- Loading -->
    <div x-show="loading" class="py-10 text-center text-sm text-gray-500">Carregando…</div>

    <template x-if="!loading">
      <div>
        <!-- Empty -->
        <div x-show="folders.length === 0 && files.length === 0 && uploads.length === 0" class="py-12 text-center">
          <svg class="w-10 h-10 text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          <p class="text-sm text-gray-500">Arraste arquivos aqui ou use “Enviar arquivos”.</p>
        </div>

        <!-- Folders -->
        <div x-show="folders.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-3">
          <template x-for="folder in folders" :key="folder.id">
            <button @click="goTo(folder.id)"
                    class="flex items-center gap-2 rounded-xl bg-white/[0.03] border border-white/5 hover:border-violet-500/30 hover:bg-white/[0.06] transition-all px-3 py-3 text-left">
              <svg class="w-5 h-5 text-violet-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
              <span class="text-sm text-gray-200 truncate" x-text="folder.name"></span>
            </button>
          </template>
        </div>

        <!-- Uploads in progress -->
        <template x-for="up in uploads" :key="up.uid">
          <div class="rounded-xl bg-white/[0.03] border border-white/5 px-3 py-2.5 mb-2"
               :class="up.status==='error' ? 'border-rose-500/30' : ''">
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <span class="text-xs text-gray-300 truncate" x-text="up.name"></span>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-[10px]"
                      :class="up.status==='error' ? 'text-rose-400' : (up.status==='done' ? 'text-emerald-400' : 'text-violet-400')"
                      x-text="up.status==='error' ? (up.error||'Erro') : (up.status==='done' ? 'Concluído ✓' : (up.status==='processing' ? 'Salvando no Drive…' : (up.status==='canceled' ? 'Cancelado' : up.progress+'%')))"></span>
                <button x-show="up.status==='uploading' || up.status==='processing'" @click="cancelUpload(up)"
                        class="text-gray-500 hover:text-rose-400 transition-colors" title="Cancelar">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button x-show="up.status==='error' || up.status==='canceled'" @click="removeUpload(up)"
                        class="text-gray-500 hover:text-white transition-colors" title="Remover">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
            <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
              <div class="h-full rounded-full transition-all"
                   :class="(up.status==='error'||up.status==='canceled' ? 'bg-rose-500' : (up.status==='done' ? 'bg-emerald-500' : 'bg-violet-500')) + (up.status==='processing' ? ' animate-pulse' : '')"
                   :style="`width: ${(up.status==='done'||up.status==='processing') ? 100 : up.progress}%`"></div>
            </div>
            <p x-show="up.status==='uploading' && up.eta" class="text-[10px] text-gray-500 mt-1" x-text="up.eta"></p>
          </div>
        </template>

        <!-- Files -->
        <div x-show="files.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <template x-for="file in files" :key="file.id">
            <button @click="openPreview(file)"
                    class="group rounded-xl overflow-hidden bg-white/[0.03] border border-white/5 hover:border-violet-500/30 transition-all text-left">
              <div class="aspect-square bg-black/30 flex items-center justify-center relative">
                <template x-if="file.is_image">
                  <img :src="rawUrl(file)" loading="lazy" class="w-full h-full object-cover" @error="$el.style.display='none'">
                </template>
                <template x-if="file.is_video">
                  <video :src="rawUrl(file)" preload="metadata" muted class="w-full h-full object-cover"></video>
                </template>
                <template x-if="!file.is_image && !file.is_video">
                  <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </template>
                <div x-show="file.is_video" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                  <span class="w-9 h-9 rounded-full bg-black/50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  </span>
                </div>
              </div>
              <div class="px-2 py-1.5">
                <p class="text-[11px] text-gray-300 truncate" x-text="file.name"></p>
                <p class="text-[10px] text-gray-600" x-text="humanSize(file.size_bytes)"></p>
              </div>
            </button>
          </template>
        </div>
      </div>
    </template>
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
        <video :src="rawUrl(preview.file)" controls autoplay playsinline class="max-h-[80vh] max-w-full rounded-lg bg-black"></video>
      </template>
      <template x-if="preview.file && !preview.file.is_image && !preview.file.is_video">
        <div class="text-center">
          <p class="text-sm text-gray-300 mb-3" x-text="preview.file.name"></p>
          <a :href="rawUrl(preview.file)" target="_blank" rel="noopener" class="btn-primary px-4 py-2 text-sm inline-flex">Abrir / baixar</a>
        </div>
      </template>
      <p class="text-xs text-gray-400 mt-3 text-center" x-text="preview.file ? preview.file.name : ''"></p>
    </div>
  </div>
</div>

<script>
// Registry de XHRs fora do estado reativo do Alpine (evita o Alpine tentar
// "proxyar" o objeto XMLHttpRequest e quebrar).
const _driveXhrs = {};
let _driveUploadSeq = 0;

function driveManager(token) {
  return {
    folderId: null,
    breadcrumb: [],
    folders: [],
    files: [],
    uploads: [],
    loading: false,
    dragging: false,
    creatingFolder: false,
    newFolderName: '',
    savingFolder: false,
    preview: { open: false, file: null },

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
      this.creatingFolder = false;
      this.load(folderId);
    },

    openPreview(file) { this.preview = { open: true, file }; },
    closePreview() { this.preview = { open: false, file: null }; },

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
          alert(d.error || 'Falha ao criar pasta.');
        }
      } catch (e) { alert('Erro de conexão.'); }
      this.savingFolder = false;
    },

    onFiles(fileList) {
      for (const file of fileList) this.uploadOne(file);
    },

    uploadOne(file) {
      const uid = ++_driveUploadSeq;
      this.uploads.push({ uid, name: file.name, progress: 0, status: 'uploading', error: null, eta: '', startedAt: Date.now() });
      // Referência REATIVA (o elemento dentro do array proxyado), não o objeto cru.
      const entry = this.uploads.find(u => u.uid === uid);

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
        delete _driveXhrs[uid];
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
              entry.error = d.error || 'Erro ao enviar';
            }
          } catch { entry.status = 'error'; entry.error = 'Resposta inválida do servidor'; }
        } else if (xhr.status === 0) {
          if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = 'Conexão interrompida'; }
        } else {
          entry.status = 'error';
          entry.error = 'Erro (' + xhr.status + ')';
          try { const d = JSON.parse(xhr.responseText); if (d.error) entry.error = d.error; } catch {}
        }
      };
      xhr.onerror = () => { delete _driveXhrs[uid]; if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = 'Falha de conexão'; } };

      xhr.send(form);
    },

    cancelUpload(entry) {
      const xhr = _driveXhrs[entry.uid];
      if (xhr && (entry.status === 'uploading' || entry.status === 'processing')) {
        entry.status = 'canceled';
        entry.error = 'Cancelado';
        xhr.abort();
        delete _driveXhrs[entry.uid];
      }
    },

    removeUpload(entry) {
      this.uploads = this.uploads.filter(u => u.uid !== entry.uid);
    },

    formatEta(sec) {
      if (!isFinite(sec) || sec < 0) return '';
      if (sec < 60) return Math.ceil(sec) + 's restantes';
      const m = Math.floor(sec / 60);
      const s = Math.ceil(sec % 60);
      return `${m}min${s ? ' ' + s + 's' : ''} restantes`;
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
