/**
 * Editor de plano de conteúdo (FE-02 — extraído de content/show.php, que tinha
 * 1.183 linhas com este JS inline: impossível de testar, reusar ou proteger com
 * CSP estrita).
 *
 * Os dados que vinham do PHP agora chegam por data-* no <div id="content-show">
 * — nada de interpolar PHP dentro de JS.
 */
const _cfg = document.getElementById('content-show')?.dataset ?? {};
const PLAN_ID     = Number(_cfg.planId || 0);
const CLIENT_NAME = _cfg.clientName || 'agencia';
const CLIENT_USER = _cfg.clientUsername || 'usuario';
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const emptyModal = () => ({
  show: false, mode: 'add', editId: null,
  platform: '', content_type: '', publish_date: '', publish_time: '',
  cover_url: '', caption: '', drive_url: '', assigned_to: '', images: [],
  title: '', theme: '', script: '', cta: ''
});

// Converte URLs de compartilhamento do Drive numa URL que funciona em <img>.
// O endpoint uc?export=view foi descontinuado pelo Google para imagens; o
// endpoint thumbnail serve a imagem (arquivo precisa ter link público).
function driveImageUrl(url) {
  if (!url) return url;
  // /file/d/FILE_ID/... ou qualquer ?id=FILE_ID / &id=FILE_ID (open, uc, etc.)
  const m = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/) || url.match(/[?&]id=([a-zA-Z0-9_-]+)/);
  if (m) return `https://drive.google.com/thumbnail?id=${m[1]}&sz=w1600`;
  return url;
}

// Espelha ContentPlanService::previewRatio() — capa de Reels/Story é 9:16, o resto 3:4.
const VERTICAL_TYPES = ['Reels / Vídeo', 'Story'];
function isVerticalType(contentType) {
  return VERTICAL_TYPES.includes(contentType);
}
function previewFrameClass(contentType) {
  return isVerticalType(contentType)
    ? 'aspect-[9/16] max-w-[15rem]'
    : 'aspect-[3/4] max-w-[20rem]';
}

function contentShow(planId) {
  return {
    itemModal: emptyModal(),
    imgErr: false,
    sending: false,
    submitting: false,
    view: 'week',   // 'week' (grade seg–dom) | 'list' (detalhe por post)
    toast: { show: false, msg: '', ok: true },

    init() {
      this.view = localStorage.getItem('yve_plan_view') === 'list' ? 'list' : 'week';
      // Deep-link do calendário (#item-N): o detalhe vive na Lista.
      if (location.hash.startsWith('#item-')) {
        this.view = 'list';
        this.$nextTick(() => this.scrollToItem(location.hash.slice(1)));
      }
    },

    setView(v) {
      this.view = v;
      localStorage.setItem('yve_plan_view', v);
    },

    // Sem permissão de edição, o clique no chip da semana leva ao detalhe.
    goToItem(id) {
      this.setView('list');
      this.$nextTick(() => this.scrollToItem(`item-${id}`));
    },

    scrollToItem(domId) {
      const el = document.getElementById(domId);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-2', 'ring-brand-500/60');
        setTimeout(() => el.classList.remove('ring-2', 'ring-brand-500/60'), 2500);
      }
    },

    isVertical() { return isVerticalType(this.itemModal.content_type); },
    frameClass() { return previewFrameClass(this.itemModal.content_type); },

    openAddPost(date = '') {
      this.itemModal = emptyModal();
      this.imgErr = false;
      this.itemModal.publish_date = date;
      this.itemModal.show = true;
    },

    openEditPost(item) {
      this.imgErr = false;
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
        images:       Array.isArray(item.images_list) ? [...item.images_list] : [],
        title:        item.title         || '',
        theme:        item.theme         || '',
        script:       item.script        || '',
        cta:          item.cta           || '',
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
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            platform:     this.itemModal.platform,
            content_type: this.itemModal.content_type,
            publish_date: this.itemModal.publish_date,
            publish_time: this.itemModal.publish_time,
            cover_url:    this.itemModal.cover_url,
            caption:      this.itemModal.caption,
            drive_url:    this.itemModal.drive_url,
            assigned_to:  this.itemModal.assigned_to,
            images:       this.itemModal.images.filter(u => u.trim()),
            title:        this.itemModal.title,
            theme:        this.itemModal.theme,
            script:       this.itemModal.script,
            cta:          this.itemModal.cta,
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
          headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
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

function planChat(planId) {
  return {
    comments:   [],
    newMessage: '',
    loading:    false,
    sending:    false,
    _interval:  null,

    async loadComments() {
      this.loading = true;
      try {
        const r = await fetch(`/api/comentarios/content_plan/${planId}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const d = await r.json();
        if (d.comments) {
          this.comments = d.comments;
          this.$nextTick(() => {
            const el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
          });
        }
      } catch {}
      this.loading = false;
    },

    async sendComment() {
      const msg = this.newMessage.trim();
      if (!msg) return;
      this.sending = true;
      try {
        const r = await fetch(`/api/comentarios/content_plan/${planId}`, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
          body:    JSON.stringify({ message: msg }),
        });
        const d = await r.json();
        if (d.success && d.comment) {
          this.comments.push(d.comment);
          this.newMessage = '';
          this.$nextTick(() => {
            const el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
          });
        }
      } catch {}
      this.sending = false;
    },

    chatDate(dt) {
      if (!dt) return '';
      const d = new Date(dt.replace(' ', 'T'));
      return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
           + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    },

    init() {
      this._interval = setInterval(() => this.loadComments(), 30000);
    },
    destroy() { clearInterval(this._interval); },
  };
}

function itemNote(itemId) {
  return {
    writing: false,
    note:    '',
    sending: false,
    saved:   false,

    async submitNote() {
      const msg = this.note.trim();
      if (!msg) return;
      this.sending = true;
      try {
        const r = await fetch(`/api/comentarios/content_plan_item/${itemId}`, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
          body:    JSON.stringify({ message: msg }),
        });
        const d = await r.json();
        if (d.success) {
          this.writing = false;
          this.note    = '';
          this.saved   = true;
          setTimeout(() => this.saved = false, 3000);
        }
      } catch {}
      this.sending = false;
    },
  };
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
        headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
      });
      const d = await r.json();
      if (d.success) this.$el.remove();
    },

    async changeStatus(status) {
      const r = await fetch(`/conteudo/${PLAN_ID}/items/${item.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ status })
      });
      const d = await r.json();
      if (d.success) location.reload();
    }
  }
}

function instaPreview() {
  return {
    show: false,
    mode: 'feed',   // 'feed' | 'profile'
    item: null,
    agencyName: CLIENT_NAME,
    username: CLIENT_USER,

    open(mode, item) {
      this.mode = mode;
      this.item = item;
      this.item._img = driveImageUrl(item.cover_url || '');
      this.show = true;
    },
    close() { this.show = false; this.item = null; },

    // Reels e Story ocupam a tela inteira (9:16); post de feed é 3:4.
    feedAspect() {
      return isVerticalType(this.item?.content_type) ? 'aspect-[9/16]' : 'aspect-[3/4]';
    },

    // Generate 8 filler grid images (grey squares + 1 real)
    gridImages() {
      const imgs = [];
      for (let i = 0; i < 9; i++) imgs.push(null);
      imgs[4] = this.item ? driveImageUrl(this.item.cover_url || '') : null;
      return imgs;
    },
  };
}
