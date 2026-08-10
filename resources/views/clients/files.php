<?php view_layout('app'); view_start('content'); ?>
<?php
// CONT-06: a equipe também cria pastas e envia arquivos por aqui — mesma
// máquina do portal (driveManager + UP-01), com endpoints do painel.
$maxBytes = (int) ($maxUploadBytes ?? 0);
$maxLabel = $maxBytes > 0 ? number_format($maxBytes / (1024 * 1024), 0) . ' MB' : '';
$isConnected = (bool) ($connected ?? false);

$jsI18n = [
  'loading'              => t('portal.files.loading'),
  'empty_title'          => t('portal.files.empty_title'),
  'empty_hint'           => t('portal.files.empty_hint'),
  'open'                 => t('portal.files.open'),
  'delete'               => t('portal.files.delete'),
  'status_queued'        => t('portal.files.status_queued'),
  'status_done'          => t('portal.files.status_done'),
  'status_processing'    => t('portal.files.status_processing'),
  'status_canceled'      => t('portal.files.status_canceled'),
  'status_error'         => t('portal.files.status_error'),
  'err_too_large'        => t('portal.files.err_too_large'),
  'err_unreadable'       => t('portal.files.err_unreadable'),
  'err_conn'             => t('portal.files.err_conn'),
  'err_generic'          => t('portal.files.err_generic'),
  'err_invalid_response' => t('portal.files.err_invalid_response'),
  'create_failed'        => t('portal.files.create_failed'),
  'undo'                 => t('portal.files.undo'),
  'eta_seconds'          => t('portal.files.eta_seconds'),
  'eta_minutes'          => t('portal.files.eta_minutes'),
  'link_copied'          => t('portal.files.link_copied'),
  'folder_link_copied'   => t('portal.files.folder_link_copied'),
  'confirm_delete_file'  => t('portal.files.confirm_delete_file'),
  'confirm_delete_folder'=> t('portal.files.confirm_delete_folder'),
  'delete_failed'        => t('portal.files.delete_failed'),
  'home'                 => t('portal.files.home'),
  'selected_count'       => t('portal.files.selected_count'),
  'moved'                => t('portal.files.moved'),
  'move_partial'         => t('portal.files.move_partial'),
  'move_failed'          => t('portal.files.move_failed'),
  'renamed'              => t('portal.files.renamed'),
  'rename_failed'        => t('portal.files.rename_failed'),
  'deleted_file'         => t('portal.files.deleted_file'),
  'deleted_folder'       => t('portal.files.deleted_folder'),
  'restored'             => t('portal.files.restored'),
  'restore_failed'       => t('portal.files.restore_failed'),
  'max_label'            => $maxLabel,
];
$driveOpts = [
  'prefix'  => '/clientes/' . (int) $client['id'] . '/conteudos',
  'syncUrl' => '/clientes/' . (int) $client['id'] . '/conteudos/sync',
];
?>

<div class="max-w-5xl mx-auto"
     x-data="driveManager(null, <?= e(json_encode($jsI18n, JSON_UNESCAPED_UNICODE)) ?>, <?= $maxBytes ?>, <?= e(json_encode($driveOpts)) ?>)"
     x-init="load(null)">

  <div class="mb-6">
    <a href="/clientes/<?= (int) $client['id'] ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      <?= e($client['name']) ?>
    </a>
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-white">Conteúdos do cliente</h1>
        <p class="mt-1 text-sm text-gray-400">Arquivos na pasta do cliente no Google Drive — enviados pelo portal ou pela equipe.</p>
      </div>
      <button @click="sync()" :disabled="syncing"
              class="btn-secondary text-sm px-3 py-2 gap-2 flex-shrink-0 disabled:opacity-50 inline-flex items-center">
        <svg class="w-4 h-4" :class="{'animate-spin': syncing}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span x-text="syncing ? 'Sincronizando…' : 'Sincronizar'"></span>
      </button>
    </div>
    <p x-show="syncMsg" x-transition x-text="syncMsg"
       :class="syncOk ? 'text-emerald-400' : 'text-rose-400'"
       class="mt-2 text-xs" style="display:none"></p>
  </div>

  <!-- Breadcrumb -->
  <div class="flex items-center gap-1.5 text-sm mb-4 flex-wrap">
    <button @click="goTo(null)" class="text-gray-400 hover:text-white transition-colors">Início</button>
    <template x-for="crumb in breadcrumb" :key="crumb.id">
      <span class="flex items-center gap-1.5">
        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <button @click="goTo(crumb.id)" class="text-gray-400 hover:text-white transition-colors" x-text="crumb.name"></button>
      </span>
    </template>
  </div>

  <!-- Toolbar (CONT-06) -->
  <div class="flex items-center gap-2 mb-3 flex-wrap">
    <?php /* Abrir a pasta no Drive: para quem prefere trabalhar direto lá em
            vez de enviar arquivo por arquivo pela plataforma. Independe da
            integração estar conectada — só do cliente já ter pasta. */ ?>
    <button @click="copyFolderLink(currentDriveId)" x-show="currentDriveId" style="display:none"
            class="btn-secondary text-sm px-3 py-2 inline-flex items-center gap-1.5">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
      <?= t('portal.files.copy_folder_link') ?>
    </button>
    <?php if ($isConnected): ?>
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
    <span class="text-xs text-gray-400">Depois de enviar, use "Copiar link" para colar no post.</span>
    <?php endif; ?>
  </div>

  <?php if ($isConnected): ?>
  <!-- Barra de seleção (mover em lote) -->
  <div x-show="selectionCount() > 0" x-transition class="card p-3 mb-4 flex items-center gap-3 flex-wrap" style="display:none">
    <span class="text-sm text-gray-200 font-medium" x-text="i18n.selected_count.replace(':n', selectionCount())"></span>
    <div class="flex items-center gap-2 ml-auto">
      <button @click="openMove()" class="btn-primary text-sm px-3 py-2 inline-flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        <?= t('portal.files.move_to') ?>
      </button>
      <button @click="clearSelection()" class="btn-secondary text-sm px-3 py-2"><?= t('portal.files.cancel') ?></button>
    </div>
  </div>

  <!-- Create folder inline -->
  <div x-show="creatingFolder" x-transition class="card p-3 mb-4 flex items-center gap-2" style="display:none">
    <input x-ref="folderInput" type="text" x-model="newFolderName" placeholder="<?= e(t('portal.files.folder_name_placeholder')) ?>"
           @keydown.enter="createFolder()" @keydown.escape="creatingFolder=false; newFolderName=''"
           class="flex-1 rounded-lg bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50">
    <button @click="createFolder()" :disabled="!newFolderName.trim() || savingFolder"
            class="btn-primary text-sm px-3 py-2 disabled:opacity-50" x-text="savingFolder ? '<?= e(t('portal.files.creating')) ?>' : '<?= e(t('portal.files.create')) ?>'"></button>
    <button @click="creatingFolder=false; newFolderName=''" class="text-xs text-gray-400 hover:text-gray-300 px-2"><?= t('portal.files.cancel') ?></button>
  </div>
  <?php else: ?>
  <div class="card p-4 mb-4">
    <p class="text-sm text-gray-300">Google Drive não conectado — envio pela plataforma indisponível. Conecte em <a href="/settings" class="text-brand-400 hover:text-brand-300">Configurações → Integrações</a>.</p>
  </div>
  <?php endif; ?>

  <!-- Drop zone + listagem -->
  <div <?php if ($isConnected): ?>@dragover.prevent="dragging=true" @dragleave.prevent="dragging=false"
       @drop.prevent="dragging=false; onFiles($event.dataTransfer.files)"<?php endif; ?>
       class="rounded-2xl border-2 border-dashed transition-colors p-2 sm:p-3"
       :class="dragging ? 'border-brand-500 bg-brand-500/5' : 'border-white/10'">

    <div x-show="loading" class="py-10 text-center text-sm text-gray-400" x-text="i18n.loading"></div>

    <div x-show="!loading && loadError" class="py-10 text-center" style="display:none">
      <p class="text-sm text-rose-400 mb-3" x-text="loadError"></p>
      <button @click="load(folderId)" class="btn-secondary text-sm px-4 py-2"><?= t('portal.files.retry') ?></button>
    </div>

    <template x-if="!loading && !loadError">
      <div>
        <div x-show="folders.length === 0 && files.length === 0 && uploads.length === 0" class="py-12 text-center">
          <p class="text-sm text-gray-400" x-text="i18n.empty_title"></p>
          <p class="text-xs text-gray-400 mt-1" x-text="i18n.empty_hint"></p>
        </div>

        <!-- Uploads em andamento -->
        <ul x-show="uploads.length > 0" class="mb-3 divide-y divide-white/5">
          <template x-for="up in uploads" :key="'u'+up.uid">
            <li class="px-2 py-2.5">
              <div class="flex items-center justify-between gap-2 mb-1.5">
                <span class="text-sm text-gray-300 truncate" x-text="up.name"></span>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <span class="text-[11px]"
                        :class="up.status==='error' ? 'text-rose-400' : (up.status==='done' ? 'text-emerald-400' : 'text-brand-400')"
                        x-text="statusLabel(up)"></span>
                  <button x-show="up.status==='uploading' || up.status==='processing' || up.status==='queued'" @click="cancelUpload(up)"
                          class="text-gray-400 hover:text-rose-400 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                  <button x-show="up.status==='error' || up.status==='canceled'" @click="removeUpload(up)"
                          class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
              <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full transition-all"
                     :class="(up.status==='error'||up.status==='canceled' ? 'bg-rose-500' : (up.status==='done' ? 'bg-emerald-500' : 'bg-brand-500')) + (up.status==='processing' ? ' animate-pulse' : '')"
                     :style="`width: ${(up.status==='done'||up.status==='processing') ? 100 : up.progress}%`"></div>
              </div>
              <p x-show="up.status==='error'" class="text-[11px] text-rose-400 mt-1" x-text="up.error"></p>
              <p x-show="up.status==='uploading' && up.eta" class="text-[10px] text-gray-400 mt-1" x-text="up.eta"></p>
            </li>
          </template>
        </ul>

        <!-- Folders -->
        <div x-show="folders.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-3">
          <template x-for="folder in folders" :key="'f'+folder.id">
            <div class="relative group">
              <button @click="goTo(folder.id)"
                      class="w-full flex items-center gap-2 rounded-xl bg-white/[0.03] border transition-all px-3 py-3 <?= $isConnected ? 'pr-28' : 'pr-10' ?> text-left"
                      :class="isSelected('folder', folder.id) ? 'border-brand-500/50 bg-brand-500/10' : 'border-white/5 hover:border-brand-500/30 hover:bg-white/[0.06]'">
                <svg class="w-5 h-5 text-brand-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                <span class="text-sm text-gray-200 truncate" x-text="folder.name"></span>
              </button>
              <div class="absolute top-1/2 -translate-y-1/2 right-2 flex items-center gap-1">
                <button @click.stop="copyFolderLink(folder.drive_folder_id)" x-show="folder.drive_folder_id"
                        title="<?= e(t('portal.files.copy_folder_link')) ?>" style="display:none"
                        class="w-5 h-5 rounded-md bg-black/40 text-gray-300 hover:text-white flex items-center justify-center transition-all opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </button>
                <?php if ($isConnected): ?>
                <button @click.stop="openRename('folder', folder)" title="<?= e(t('portal.files.rename')) ?>"
                        class="w-5 h-5 rounded-md bg-black/40 text-gray-300 hover:text-white flex items-center justify-center transition-all opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click.stop="deleteFolder(folder)" title="<?= e(t('portal.files.delete')) ?>"
                        class="w-5 h-5 rounded-md bg-black/40 text-gray-300 hover:text-rose-400 flex items-center justify-center transition-all opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <button @click.stop="toggleSelect('folder', folder.id)" aria-label="<?= e(t('portal.files.select')) ?>"
                        class="w-5 h-5 rounded-md border flex items-center justify-center transition-all"
                        :class="isSelected('folder', folder.id) ? 'select-check-on' : 'border-white/30 bg-black/40 text-transparent opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100'">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </button>
                <?php endif; ?>
              </div>
            </div>
          </template>
        </div>

        <!-- Files -->
        <div x-show="files.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <template x-for="file in files" :key="'x'+file.id">
            <div class="group relative rounded-xl overflow-hidden bg-white/[0.03] border transition-all"
                 :class="isSelected('file', file.id) ? 'border-brand-500/50' : 'border-white/5 hover:border-brand-500/30'">
              <?php if ($isConnected): ?>
              <button @click.stop="toggleSelect('file', file.id)" aria-label="<?= e(t('portal.files.select')) ?>"
                      class="absolute top-1.5 left-1.5 z-10 w-6 h-6 rounded-md border flex items-center justify-center transition-all"
                      :class="isSelected('file', file.id) ? 'select-check-on' : 'bg-black/60 border-white/30 text-transparent opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              </button>
              <?php endif; ?>
              <button @click="openPreview(file)" class="block w-full text-left">
                <div class="aspect-square bg-black/30 flex items-center justify-center relative">
                  <template x-if="file.is_image">
                    <img :src="rawUrl(file)" loading="lazy" class="w-full h-full object-cover" @error="$el.style.display='none'">
                  </template>
                  <template x-if="file.is_video">
                    <video :src="rawUrl(file)" preload="metadata" muted class="w-full h-full object-cover"></video>
                  </template>
                  <template x-if="!file.is_image && !file.is_video">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </template>
                  <div x-show="file.is_video" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <span class="w-9 h-9 rounded-full bg-black/50 flex items-center justify-center">
                      <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </span>
                  </div>
                </div>
                <div class="px-2 py-1.5">
                  <p class="text-[11px] text-gray-300 truncate" x-text="file.name"></p>
                  <p class="text-[10px] text-gray-400" x-text="humanSize(file.size_bytes)"></p>
                </div>
              </button>
              <!-- Ações: copiar link, renomear, excluir -->
              <div class="absolute top-1.5 right-1.5 flex items-center gap-1">
                <button @click.stop="copyLink(file)" title="<?= e(t('portal.files.copy_link')) ?>"
                        class="w-7 h-7 rounded-lg bg-black/60 opacity-0 group-hover:opacity-100 focus:opacity-100 flex items-center justify-center text-gray-200 hover:text-white transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </button>
                <?php if ($isConnected): ?>
                <button @click.stop="openRename('file', file)" title="<?= e(t('portal.files.rename')) ?>"
                        class="w-7 h-7 rounded-lg bg-black/60 opacity-0 group-hover:opacity-100 focus:opacity-100 flex items-center justify-center text-gray-200 hover:text-white transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click.stop="deleteFile(file)" title="<?= e(t('portal.files.delete')) ?>"
                        class="w-7 h-7 rounded-lg bg-black/60 opacity-0 group-hover:opacity-100 focus:opacity-100 flex items-center justify-center text-gray-200 hover:text-rose-400 transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <?php endif; ?>
              </div>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>

  <!-- Toast (link copiado / erros) -->
  <div x-show="toast.show" x-transition.opacity
       class="fixed left-1/2 -translate-x-1/2 bottom-6 z-50 w-[calc(100%-2rem)] max-w-md" style="display:none">
    <div class="flex items-center gap-3 rounded-xl bg-[#1d1d29] border border-white/10 shadow-lg px-4 py-3">
      <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      <span class="text-sm text-gray-200 flex-1 truncate" x-text="toast.msg"></span>
      <button x-show="toast.restore" @click="undoDelete()" :disabled="toast.busy"
              class="text-sm font-semibold text-brand-300 hover:text-brand-200 disabled:opacity-50 flex-shrink-0"
              x-text="i18n.undo"></button>
      <button @click="hideToast()" class="text-gray-400 hover:text-white flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
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

  <!-- Modal de renomear -->
  <div x-show="renameBox.open" x-transition.opacity @keydown.escape.window="closeRename()"
       @click.self="closeRename()"
       class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background:rgba(0,0,0,.7); display:none">
    <div class="w-full max-w-sm rounded-2xl bg-[#1d1d29] border border-white/10 shadow-xl p-5">
      <p class="text-sm font-semibold text-white mb-3"><?= t('portal.files.rename_title') ?></p>
      <input x-ref="renameInput" type="text" x-model="renameBox.name"
             @keydown.enter="confirmRename()"
             class="w-full rounded-lg bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 mb-4">
      <div class="flex justify-end gap-2">
        <button @click="closeRename()" class="btn-secondary text-sm px-4 py-2"><?= t('portal.files.cancel') ?></button>
        <button @click="confirmRename()" :disabled="!renameBox.name.trim() || renameBox.busy"
                class="btn-primary text-sm px-4 py-2 disabled:opacity-50"
                x-text="renameBox.busy ? '<?= e(t('portal.files.renaming')) ?>' : '<?= e(t('portal.files.rename_save')) ?>'"></button>
      </div>
    </div>
  </div>

  <!-- Modal "Mover para…" (navega as pastas e escolhe o destino) -->
  <div x-show="movePicker.open" x-transition.opacity @keydown.escape.window="closeMove()"
       @click.self="closeMove()"
       class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background:rgba(0,0,0,.7); display:none">
    <div class="w-full max-w-md rounded-2xl bg-[#1d1d29] border border-white/10 shadow-xl p-5">
      <p class="text-sm font-semibold text-white mb-1"><?= t('portal.files.move_title') ?></p>
      <p class="text-xs text-gray-400 mb-3" x-text="i18n.selected_count.replace(':n', selectionCount())"></p>

      <div class="flex items-center gap-1.5 text-xs mb-2 flex-wrap">
        <button @click="loadPicker(null)" class="text-gray-400 hover:text-white transition-colors"><?= t('portal.files.home') ?></button>
        <template x-for="crumb in movePicker.breadcrumb" :key="'m'+crumb.id">
          <span class="flex items-center gap-1.5">
            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <button @click="loadPicker(crumb.id)" class="text-gray-400 hover:text-white transition-colors" x-text="crumb.name"></button>
          </span>
        </template>
      </div>

      <div class="rounded-xl border border-white/10 max-h-60 overflow-y-auto divide-y divide-white/5 mb-4">
        <div x-show="movePicker.loading" class="py-6 text-center text-xs text-gray-400" x-text="i18n.loading"></div>
        <div x-show="!movePicker.loading && movePicker.error" class="py-6 px-3 text-center" style="display:none">
          <p class="text-xs text-rose-400 mb-2" x-text="movePicker.error"></p>
          <button @click="loadPicker(movePicker.folderId)" class="btn-secondary text-xs px-3 py-1.5"><?= t('portal.files.retry') ?></button>
        </div>
        <template x-if="!movePicker.loading && !movePicker.error">
          <div>
            <p x-show="movePicker.folders.length === 0" class="py-6 px-3 text-center text-xs text-gray-400"><?= t('portal.files.move_no_subfolders') ?></p>
            <template x-for="f in movePicker.folders" :key="'p'+f.id">
              <button @click="loadPicker(f.id)" class="w-full flex items-center gap-2 px-3 py-2.5 text-left hover:bg-white/[0.04] transition-colors">
                <svg class="w-4 h-4 text-brand-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                <span class="text-sm text-gray-200 truncate flex-1" x-text="f.name"></span>
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </template>
          </div>
        </template>
      </div>

      <div class="flex items-center justify-between gap-2 flex-wrap">
        <p class="text-xs text-gray-400 min-w-0 truncate"><?= t('portal.files.move_dest') ?> <span class="text-gray-200 font-medium" x-text="pickerTargetName()"></span></p>
        <div class="flex gap-2">
          <button @click="closeMove()" class="btn-secondary text-sm px-4 py-2"><?= t('portal.files.cancel') ?></button>
          <button @click="confirmMove()" :disabled="movePicker.busy || movePicker.loading"
                  class="btn-primary text-sm px-4 py-2 disabled:opacity-50"
                  x-text="movePicker.busy ? '<?= e(t('portal.files.moving')) ?>' : '<?= e(t('portal.files.move_here')) ?>'"></button>
        </div>
      </div>
    </div>
  </div>

  <!-- Lightbox -->
  <div x-show="preview.open" x-transition.opacity @keydown.escape.window="closePreview()"
       @click.self="closePreview()"
       class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.88); display:none">
    <button @click="closePreview()" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="max-w-4xl w-full max-h-[85vh] flex flex-col items-center">
      <template x-if="preview.file && preview.file.is_image">
        <img :src="rawUrl(preview.file)" class="max-h-[75vh] max-w-full object-contain rounded-lg">
      </template>
      <template x-if="preview.file && preview.file.is_video">
        <iframe :src="`https://drive.google.com/file/d/${preview.file.drive_file_id}/preview`"
                class="w-full max-w-3xl aspect-video rounded-lg bg-black" allow="autoplay; fullscreen" allowfullscreen></iframe>
      </template>
      <template x-if="preview.file && !preview.file.is_image && !preview.file.is_video">
        <div class="text-center">
          <p class="text-sm text-gray-300 mb-3" x-text="preview.file.name"></p>
          <a :href="rawUrl(preview.file)" target="_blank" rel="noopener" class="btn-primary px-4 py-2 text-sm inline-flex"><?= t('portal.files.open') ?></a>
        </div>
      </template>
      <div class="flex items-center gap-3 mt-3">
        <p class="text-xs text-gray-400" x-text="preview.file ? preview.file.name : ''"></p>
        <button @click="copyLink(preview.file)" class="text-xs text-brand-400 hover:text-brand-300 inline-flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
          <?= t('portal.files.copy_link') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- SEM defer, de propósito: o Alpine (defer, no <head>) executa ANTES de
     qualquer script defer do body — o módulo precisa definir driveManager()
     durante o parse (ver ScriptLoadOrderTest). -->
<script src="<?= asset('/js/drive-manager.js') ?>"></script>

<?php view_end(); ?>
