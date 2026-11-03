/**
 * Tela de aprovação interna do plano (FE-02 — extraído da view; BUG-04).
 *
 * Três bugs que esta versão corrige, todos invisíveis no PHP:
 *   1. `fetch` cru sem `r.ok`: 403/404/500 caía no `if (d.success)` sem `else`
 *      e a tela ficava parada, sem explicar nada. Agora tudo passa por `api`
 *      (public/js/api.js), que já injeta o CSRF e vira `ApiError` com a
 *      mensagem do servidor.
 *   2. Estado no escopo errado: o formulário de feedback tinha o seu próprio
 *      `x-data` com `submitting`/`comment`, mas quem escrevia era o método do
 *      componente do item — o botão nunca desabilitava e dava para enviar duas
 *      vezes. O estado do formulário agora mora no componente do item.
 *   3. Toast pela API do Alpine **v2** (`el.__x.$data`), num projeto v3: o
 *      "Feedback enviado!" nunca aparecia. Agora é um evento de janela que o
 *      componente raiz escuta.
 *
 * Os textos chegam prontos do PHP (i18n), porque a tela fala o idioma do
 * cliente (clients.language), não o da sessão.
 */

const STATUS_CLASS = {
  draft:    'bg-gray-500/15 text-gray-300 ring-gray-500/30',
  sent:     'bg-blue-500/15 text-blue-300 ring-blue-500/30',
  revision: 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
  approved: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
  rejected: 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
};
const STATUS_DOT = {
  draft: 'bg-gray-400', sent: 'bg-blue-400', revision: 'bg-amber-400',
  approved: 'bg-emerald-400', rejected: 'bg-rose-400',
};

/** Toast vive no componente raiz; do item chega por evento (Alpine v3). */
function approvalToast(msg, ok) {
  window.dispatchEvent(new CustomEvent('approval-toast', { detail: { msg, ok } }));
}

function approvalShow(planId, i18n) {
  return {
    acting: false,
    showRevisionModal: false,
    revisionNote: '',
    showReopenModal: false,
    reopenReason: '',
    toast: { show: false, msg: '', ok: true },

    async approvePlan() {
      if (this.acting) return;
      if (!confirm(i18n.confirm_approve_all)) return;

      this.acting = true;
      try {
        await api.post(`/aprovacoes/${planId}/aprovar`);
        this.showToast(i18n.plan_approved, true);
        // `acting` segue true: o botão fica desabilitado até o reload.
        setTimeout(() => location.reload(), 800);
      } catch (e) {
        this.showToast(e.message || i18n.action_failed, false);
        this.acting = false;
      }
    },

    async requestRevision() {
      if (this.acting) return;

      this.acting = true;
      try {
        await api.post(`/aprovacoes/${planId}/revisao`, { note: this.revisionNote });
        this.showRevisionModal = false;
        this.showToast(i18n.revision_sent, true);
        setTimeout(() => location.reload(), 800);
      } catch (e) {
        this.showToast(e.message || i18n.action_failed, false);
        this.acting = false;
      }
    },

    async reopenPlan() {
      if (this.acting) return;

      this.acting = true;
      try {
        await api.post(`/aprovacoes/${planId}/reabrir`, { reason: this.reopenReason });
        this.showReopenModal = false;
        this.showToast(i18n.reopened, true);
        setTimeout(() => location.reload(), 800);
      } catch (e) {
        this.showToast(e.message || i18n.action_failed, false);
        this.acting = false;
      }
    },

    showToast(msg, ok) {
      this.toast = { show: true, msg, ok };
      setTimeout(() => this.toast.show = false, 3500);
    },
  };
}

function approvalItem(planId, itemId, initialStatus, i18n, defaultType) {
  return {
    currentStatus: initialStatus,
    // Estado do formulário: aqui, e não num x-data aninhado — era o que
    // deixava o botão sempre habilitado (ver cabeçalho do arquivo).
    open: false,
    // O tipo já selecionado vem do PHP (`$defaultType`): perfil só com
    // `approvals.approve` não renderiza o botão "Comentar", e nascer em
    // 'comment' mandava o POST justamente para o tipo que o controller recusa
    // (403 "Seu perfil não permite esta ação") — a função ignorava o 5º
    // argumento que a view sempre passou.
    type: defaultType || 'comment',
    comment: '',
    submitting: false,

    statusLabel: s => i18n.status[s] ?? s,
    statusClass: s => STATUS_CLASS[s] ?? STATUS_CLASS.draft,
    statusDot:   s => STATUS_DOT[s] ?? 'bg-gray-400',

    async submitFeedback() {
      if (this.submitting) return;

      this.submitting = true;
      try {
        await api.post(`/aprovacoes/${planId}/items/${itemId}`, {
          feedback_type: this.type,
          comment: this.comment,
        });

        const map = { approved: 'approved', changes_requested: 'revision', rejected: 'rejected' };
        if (map[this.type]) this.currentStatus = map[this.type];

        this.open    = false;
        this.comment = '';
        approvalToast(i18n.feedback_sent, true);
        // `submitting` segue true até o reload trazer o estado real do plano.
        setTimeout(() => location.reload(), 1000);
      } catch (e) {
        approvalToast(e.message || i18n.action_failed, false);
        this.submitting = false;
      }
    },
  };
}
