<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-5xl mx-auto" x-data="driveBrowser(<?= (int) $client['id'] ?>)" x-init="load(null)">
  <div class="mb-6">
    <a href="/clientes/<?= (int) $client['id'] ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= e($client['name']) ?>
    </a>
    <h1 class="text-2xl font-bold text-white">Conteúdos enviados</h1>
    <p class="mt-1 text-sm text-gray-400">Arquivos que o cliente enviou pelo portal, no seu Google Drive.</p>
  </div>

  <!-- Breadcrumb -->
  <div class="flex items-center gap-1.5 text-sm mb-4 flex-wrap">
    <button @click="goTo(null)" class="text-gray-400 hover:text-white transition-colors">Início</button>
    <template x-for="crumb in breadcrumb" :key="crumb.id">
      <span class="flex items-center gap-1.5">
        <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <button @click="goTo(crumb.id)" class="text-gray-400 hover:text-white transition-colors" x-text="crumb.name"></button>
      </span>
    </template>
  </div>

  <div x-show="loading" class="py-10 text-center text-sm text-gray-500">Carregando…</div>

  <template x-if="!loading">
    <div>
      <div x-show="folders.length === 0 && files.length === 0" class="py-16 text-center rounded-2xl border border-white/5 bg-white/[0.02]">
        <p class="text-sm text-gray-500">Nenhum conteúdo nesta pasta ainda.</p>
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

  <!-- Lightbox -->
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
          <a :href="rawUrl(preview.file)" target="_blank" rel="noopener" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white inline-flex">Abrir / baixar</a>
        </div>
      </template>
      <p class="text-xs text-gray-400 mt-3 text-center" x-text="preview.file ? preview.file.name : ''"></p>
    </div>
  </div>
</div>

<script>
function driveBrowser(clientId) {
  return {
    breadcrumb: [],
    folders: [],
    files: [],
    loading: false,
    preview: { open: false, file: null },

    base() { return `/clientes/${clientId}/conteudos`; },
    rawUrl(file) { return `${this.base()}/file/${file.id}/raw`; },

    async load(folderId) {
      this.loading = true;
      try {
        const url = `${this.base()}/folders` + (folderId ? `?folder_id=${folderId}` : '');
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

    goTo(folderId) { this.load(folderId); },

    openPreview(file) { this.preview = { open: true, file }; },
    closePreview() { this.preview = { open: false, file: null }; },

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

<?php view_end(); ?>
