/**
 * Gerenciador de envio de conteúdos do portal (Drive) — extraído da view (FE-02).
 *
 * Dados do PHP entram como parâmetros do componente Alpine (x-data), nunca
 * interpolados aqui dentro.
 */
// Registry de XHRs fora do estado reativo do Alpine (evita o Alpine "proxyar" o XMLHttpRequest).
const _driveXhrs = {};
let _driveUploadSeq = 0;
const _DRIVE_MAX_CONCURRENT = 2;
// Chunk do upload direto browser→Drive: o Google exige múltiplo de 256KB.
const _DRIVE_CHUNK = 16 * 1024 * 1024;
// Wake lock (fora do estado reativo): iOS congela o JS quando a tela apaga,
// matando uploads longos — segurar a tela acesa enquanto houver envio ativo.
let _driveWakeLock = null;

/**
 * @param token    token do portal (modo portal) — ignorado quando opts.prefix vem preenchido
 * @param opts     {prefix, syncUrl} — o painel interno (CONT-06) passa
 *                 prefix='/clientes/{id}/conteudos' e reusa o componente inteiro;
 *                 syncUrl habilita o botão "Sincronizar" (só existe no painel).
 */
function driveManager(token, i18n, maxBytes, opts = {}) {
  const prefix  = opts.prefix || `/portal/${token}/drive`;
  const syncUrl = opts.syncUrl || null;

  return {
    i18n,
    maxBytes: maxBytes || 0,
    canSync: !!syncUrl,
    syncing: false,
    syncMsg: '',
    syncOk: false,
    folderId: null,
    breadcrumb: [],
    folders: [],
    files: [],
    uploads: [],
    queue: [],
    activeCount: 0,
    loading: false,
    loadError: null,
    dragging: false,
    creatingFolder: false,
    newFolderName: '',
    savingFolder: false,
    preview: { open: false, file: null },
    iosTip: false,
    toast: { show: false, msg: '', restore: null, busy: false },
    _toastTimer: null,
    confirmBox: { open: false, message: '' },
    _confirmAction: null,

    base() { return prefix; },
    rawUrl(file) { return `${this.base()}/file/${file.id}/raw`; },

    // Alpine chama init() automaticamente ao montar o componente.
    init() {
      // Dica de iCloud só em iPhone/iPad (iPadOS se identifica como Mac + touch).
      try {
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
          || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        this.iosTip = isIos && localStorage.getItem('yve_ios_tip') !== '1';
      } catch {}
      // iOS: ao voltar pra página com envio ativo, readquire o wake lock
      // (ele é liberado pelo sistema quando a aba sai de foco).
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && this.activeCount > 0) this.acquireWakeLock();
      });
      // Sair da página mata os envios — avisa antes.
      window.addEventListener('beforeunload', (e) => {
        if (this.activeCount > 0 || this.queue.length > 0) { e.preventDefault(); e.returnValue = ''; }
      });
    },

    async acquireWakeLock() {
      try {
        if ('wakeLock' in navigator && !_driveWakeLock) {
          _driveWakeLock = await navigator.wakeLock.request('screen');
          _driveWakeLock.addEventListener('release', () => { _driveWakeLock = null; });
        }
      } catch {}
    },
    releaseWakeLock() {
      try { _driveWakeLock && _driveWakeLock.release(); } catch {}
      _driveWakeLock = null;
    },

    dismissIosTip() {
      this.iosTip = false;
      try { localStorage.setItem('yve_ios_tip', '1'); } catch {}
    },

    /**
     * Confere que o arquivo é legível ANTES de abrir a sessão. No iOS, vídeo
     * "otimizado" no iCloud vira um temporário que o WebKit pode invalidar —
     * a leitura falha/trava e o upload morreria em silêncio.
     */
    fileReadable(file) {
      const read = file.slice(0, 1024).arrayBuffer().then(() => true, () => false);
      const timeout = new Promise(r => setTimeout(() => r(false), 15000));
      return Promise.race([read, timeout]);
    },

    async load(folderId) {
      this.loading = true;
      this.loadError = null;
      this.folderId = folderId;
      try {
        const url = `${this.base()}/folders` + (folderId ? `?folder_id=${folderId}` : '');
        const d = await api.get(url);
        this.breadcrumb = d.breadcrumb || [];
        this.folders = d.folders || [];
        this.files = d.files || [];
      } catch (e) {
        // Antes: catch vazio — a lista ficava em branco sem dizer nada.
        this.loadError = e.message;
      }
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
        const d = await api.post(`${this.base()}/folders`, { parent_id: this.folderId, name });
        this.folders.push(d.folder);
        this.folders.sort((a, b) => a.name.localeCompare(b.name));
        this.creatingFolder = false;
        this.newFolderName = '';
      } catch (e) {
        this.showToast(e.message || this.i18n.create_failed, null);
      }
      this.savingFolder = false;
    },

    /** Reconcilia com o Drive (só painel interno — syncUrl vem das opts). */
    async sync() {
      if (!this.canSync || this.syncing) return;
      this.syncing = true;
      this.syncMsg = '';
      try {
        const d = await api.post(syncUrl);
        const parts = [];
        if (d.added)   parts.push(`${d.added} novo(s)`);
        if (d.removed) parts.push(`${d.removed} removido(s)`);
        if (d.renamed) parts.push(`${d.renamed} renomeado(s)`);
        this.syncOk = true;
        this.syncMsg = parts.length ? `Sincronizado: ${parts.join(', ')}.` : 'Tudo já estava atualizado.';
        await this.load(this.folderId);
      } catch (e) {
        this.syncOk = false;
        this.syncMsg = e.message || 'Não foi possível sincronizar.';
      }
      this.syncing = false;
      setTimeout(() => { this.syncMsg = ''; }, 6000);
    },

    /** Copia o link do arquivo no Drive — pra colar no post (CONT-06). */
    async copyLink(file) {
      const url = file.web_view_link
        || (file.drive_file_id ? `https://drive.google.com/file/d/${file.drive_file_id}/view` : '');
      if (!url) return;
      try {
        await navigator.clipboard.writeText(url);
        this.showToast(this.i18n.link_copied || 'Link copiado!', null);
      } catch {
        this.showToast(url, null); // clipboard bloqueado: mostra o link pra copiar na mão
      }
    },

    deleteFile(file) {
      this.askConfirm(this.i18n.confirm_delete_file.replace(':name', file.name), () => this.doDeleteFile(file));
    },

    async doDeleteFile(file) {
      try {
        const d = await api.post(`${this.base()}/file/${file.id}/delete`);
        this.files = this.files.filter(f => f.id !== file.id);
        // Toast com "Desfazer" (o arquivo foi pra lixeira do Drive).
        this.showToast(this.i18n.deleted_file, d.restore || null);
      } catch (e) {
        this.showToast(e.message || this.i18n.delete_failed, null);
      }
    },

    deleteFolder(folder) {
      this.askConfirm(this.i18n.confirm_delete_folder.replace(':name', folder.name), () => this.doDeleteFolder(folder));
    },

    async doDeleteFolder(folder) {
      try {
        await api.post(`${this.base()}/folder/${folder.id}/delete`);
        this.folders = this.folders.filter(f => f.id !== folder.id);
        this.showToast(this.i18n.deleted_folder, null);
      } catch (e) {
        this.showToast(e.message || this.i18n.delete_failed, null);
      }
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
        const d = await api.post(`${this.base()}/file/restore`, r);
        // Reaparece na lista se ainda estamos na mesma pasta de origem.
        const sameFolder = (r.folder_id ?? null) === (this.folderId ?? null);
        if (sameFolder && d.file) this.files.unshift(d.file);
        this.showToast(this.i18n.restored, null);
      } catch (e) {
        this.showToast(e.message || this.i18n.restore_failed, null);
        this.toast.busy = false;
      }
    },

    onFiles(fileList) {
      for (const file of fileList) this.enqueue(file);
    },

    enqueue(file) {
      const uid = ++_driveUploadSeq;
      // Sem trava de tipo (qualquer arquivo é aceito; o proxy /raw força
      // download do que não for mídia) e sem trava de tamanho — o caminho
      // direto browser→Drive não tem o teto do servidor; o limite (maxBytes)
      // só vale se cairmos no fallback via relay PHP.
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
      this.acquireWakeLock();

      try {
        // iOS/iCloud: valida a leitura antes de abrir sessão — falha vira
        // mensagem clara em vez de upload morto no meio.
        if (!(await this.fileReadable(entry.file))) {
          entry.status = 'error';
          entry.error = this.i18n.err_unreadable;
          return;
        }

        const sess = await this.createUploadSession(entry.file);
        let outcome = 'failed';
        let diag = sess.diag || '';
        if (sess.url) {
          const res = await this.uploadDirect(entry, sess.url);
          outcome = res.outcome;
          diag = res.diag || diag;
        }
        if (outcome === 'failed') {
          if (this.maxBytes === 0 || entry.file.size <= this.maxBytes) {
            // Transporte direto indisponível (rede/proxy): tenta o relay PHP.
            console.warn('[drive] upload direto indisponível (' + diag + '); usando o relay');
            entry.status = 'uploading';
            entry.progress = 0;
            entry.error = null;
            entry.startedAt = Date.now();
            await this.uploadRelay(entry);
          } else {
            // Sem fallback possível (arquivo maior que o limite do relay):
            // mostra o passo que falhou pra diagnosticar sem DevTools.
            entry.status = 'error';
            entry.error = this.i18n.err_conn + (diag ? ' [' + diag + ']' : '');
          }
        }
      } catch (e) {
        if (entry.status !== 'canceled') { entry.status = 'error'; entry.error = this.i18n.err_conn + ' [inesperado]'; }
      } finally {
        delete _driveXhrs[entry.uid];
        this.activeCount = Math.max(0, this.activeCount - 1);
        this.pumpQueue();
        if (this.activeCount === 0 && this.queue.length === 0) this.releaseWakeLock();
      }
    },

    /**
     * Pede ao servidor a session URI do upload resumável.
     * Retorna {url} no sucesso ou {diag} explicando a falha (pro fallback/erro).
     * Timeout de 20s: uma sessão que não abre não pode segurar a fila em 0%.
     */
    async createUploadSession(file) {
      try {
        const d = await api.post(`${this.base()}/upload/session`, {
          name: file.name,
          mime: file.type || 'application/octet-stream',
          size: file.size,
          folder_id: this.folderId,
        }, { timeout: 20000 });

        return d.upload_url ? { url: d.upload_url } : { diag: 'sessao:sem-url' };
      } catch (e) {
        return { diag: 'sessao:' + (e.isNetwork ? 'rede' : 'HTTP' + e.status) };
      }
    },

    /**
     * Envia os bytes em chunks direto pra session URI do Google (com retomada).
     * Retorna {outcome, diag}: 'done' (terminou — sucesso ou erro já mostrado),
     * 'canceled', ou 'failed' + diag (transporte indisponível — chamador decide).
     */
    async uploadDirect(entry, uploadUrl) {
      const file = entry.file;
      const total = file.size;
      let offset = 0;
      let attempts = 0;
      let diag = '';

      while (offset < total) {
        if (entry.status === 'canceled') return { outcome: 'canceled' };

        const end = Math.min(offset + _DRIVE_CHUNK, total);
        const res = await this.putChunk(entry, uploadUrl, file.slice(offset, end), offset, end, total);

        if (entry.status === 'canceled') return { outcome: 'canceled' };

        if (res.type === 'progress') { offset = res.next; attempts = 0; continue; }

        if (res.type === 'done') {
          entry.status = 'processing';
          entry.eta = '';
          await this.completeDirect(entry, res.file);
          return { outcome: 'done' };
        }

        // Chunk falhou: espera, pergunta ao Google quanto já foi gravado e retoma.
        diag = res.diag || 'put:?';
        attempts++;
        if (attempts > 3) return { outcome: 'failed', diag };
        await new Promise(r => setTimeout(r, 1000 * attempts));
        const committed = await this.probeOffset(uploadUrl, total);
        if (committed !== null) offset = committed;
      }
      return { outcome: 'failed', diag: diag || 'put:fim-inesperado' };
    },

    /** PUT de um chunk com Content-Range. 308 = continuar; 200/201 = terminou. */
    putChunk(entry, url, blob, start, end, total) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        _driveXhrs[entry.uid] = xhr;
        xhr.open('PUT', url, true);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${total}`);
        // Nenhuma etapa pode pendurar o upload em silêncio (bug do "0% eterno").
        xhr.timeout = 180000;

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
            resolve(file && file.id ? { type: 'done', file } : { type: 'error', diag: 'put:resposta-invalida' });
          } else {
            resolve({ type: 'error', diag: 'put:HTTP' + xhr.status });
          }
        };
        xhr.ontimeout = () => resolve({ type: 'error', diag: 'put:timeout' });
        xhr.onerror = () => resolve({ type: 'error', diag: 'put:rede' });
        xhr.onabort = () => resolve({ type: 'error', diag: 'put:cancelado' });

        xhr.send(blob);
      });
    },

    /** Pergunta ao Google quantos bytes a sessão já tem (retomada pós-queda). */
    probeOffset(url, total) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url, true);
        xhr.setRequestHeader('Content-Range', `bytes */${total}`);
        xhr.timeout = 20000;
        xhr.onload = () => {
          if (xhr.status === 308) {
            const m = /-(\d+)$/.exec(xhr.getResponseHeader('Range') || '');
            resolve(m ? (parseInt(m[1], 10) + 1) : 0);
          } else {
            resolve(null);
          }
        };
        xhr.ontimeout = () => resolve(null);
        xhr.onerror = () => resolve(null);
        xhr.send();
      });
    },

    /** Registra no sistema o arquivo que o Drive confirmou (valida a pasta no servidor). */
    async completeDirect(entry, driveFile) {
      try {
        const d = await api.post(`${this.base()}/upload/complete`, {
          drive_file_id: driveFile.id,
          folder_id: this.folderId,
        }, { timeout: 30000 });

        entry.status = 'done';
        entry.progress = 100;
        this.files.unshift(d.file);
        setTimeout(() => { this.uploads = this.uploads.filter(u => u.uid !== entry.uid); }, 1500);
      } catch (e) {
        entry.status = 'error';
        entry.error = e.message || this.i18n.err_generic;
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
        xhr.open('POST', `${this.base()}/upload`, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        // CSRF (SEC-08): este XHR não passa pelo api.js. Os PUTs da sessão
        // resumável NÃO levam este header — vão pro Google (cross-origin).
        xhr.setRequestHeader('X-CSRF-Token', document.querySelector('meta[name="csrf-token"]')?.content || '');

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
