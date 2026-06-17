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
          <a :href="file.web_view_link || '#'" target="_blank" rel="noopener"
             class="group rounded-xl overflow-hidden bg-white/[0.03] border border-white/5 hover:border-violet-500/30 transition-all block">
            <div class="aspect-square bg-black/30 flex items-center justify-center relative">
              <template x-if="file.thumbnail">
                <img :src="file.thumbnail" class="w-full h-full object-cover" @error="$el.style.display='none'">
              </template>
              <template x-if="!file.thumbnail">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path x-show="file.is_video" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                  <path x-show="!file.is_video" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4z"/>
                </svg>
              </template>
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                <svg class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              </div>
            </div>
            <div class="px-2 py-1.5">
              <p class="text-[11px] text-gray-300 truncate" x-text="file.name"></p>
              <p class="text-[10px] text-gray-600" x-text="humanSize(file.size_bytes)"></p>
            </div>
          </a>
        </template>
      </div>
    </div>
  </template>
</div>

<script>
function driveBrowser(clientId) {
  return {
    breadcrumb: [],
    folders: [],
    files: [],
    loading: false,

    async load(folderId) {
      this.loading = true;
      try {
        const url = `/clientes/${clientId}/conteudos/folders` + (folderId ? `?folder_id=${folderId}` : '');
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
