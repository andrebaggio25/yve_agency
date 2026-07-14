/**
 * Tela de aprovação interna do plano (FE-02 — extraído da view).
 * O ID do plano vem por data-* no container, não interpolado no JS.
 */
const PLAN_ID = Number(document.getElementById('approval-show')?.dataset.planId || 0);
const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const STATUS_LABELS = {
  draft: 'Rascunho', sent: 'Enviado', revision: 'Revisão',
  approved: 'Aprovado', rejected: 'Rejeitado'
};
const STATUS_CLASS = {
  draft:    'bg-gray-500/15 text-gray-300 ring-gray-500/30',
  sent:     'bg-blue-500/15 text-blue-300 ring-blue-500/30',
  revision: 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
  approved: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
  rejected: 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
};
const STATUS_DOT = {
  draft:'bg-gray-400', sent:'bg-blue-400', revision:'bg-amber-400',
  approved:'bg-emerald-400', rejected:'bg-rose-400'
};

function approvalShow(planId) {
  return {
    acting: false,
    showRevisionModal: false,
    revisionNote: '',
    toast: { show: false, msg: '', ok: true },

    async approvePlan() {
      if (!confirm('Confirmar aprovação de todo o plano?')) return;
      this.acting = true;
      const r = await fetch(`/aprovacoes/${planId}/aprovar`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF }
      });
      const d = await r.json();
      if (d.success) {
        this.showToast('Plano aprovado!', true);
        setTimeout(() => location.reload(), 800);
      } else {
        this.showToast(d.error || 'Erro ao aprovar.', false);
        this.acting = false;
      }
    },

    async requestRevision() {
      this.acting = true;
      const r = await fetch(`/aprovacoes/${planId}/revisao`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ note: this.revisionNote })
      });
      const d = await r.json();
      if (d.success) {
        this.showToast('Revisão solicitada!', true);
        setTimeout(() => location.reload(), 800);
      } else {
        this.showToast(d.error || 'Erro.', false);
        this.acting = false;
      }
    },

    showToast(msg, ok) {
      this.toast = { show: true, msg, ok };
      setTimeout(() => this.toast.show = false, 3500);
    }
  }
}

function approvalItem(itemId, initialStatus) {
  return {
    currentStatus: initialStatus,
    statusLabel: s => STATUS_LABELS[s] ?? s,
    statusClass: s => STATUS_CLASS[s] ?? STATUS_CLASS.draft,
    statusDot:   s => STATUS_DOT[s]   ?? 'bg-gray-400',

    async submitFeedback(itemId, type, comment) {
      this.submitting = true;
      const r = await fetch(`/aprovacoes/${PLAN_ID}/items/${itemId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ feedback_type: type, comment })
      });
      const d = await r.json();
      if (d.success) {
        this.open = false;
        this.comment = '';
        const map = { approved:'approved', changes_requested:'revision', rejected:'rejected' };
        if (map[type]) this.currentStatus = map[type];
        // Show a quick success flash in parent
        document.querySelector('[x-data^="approvalShow"]')
          ?.__x?.$data.showToast('Feedback enviado!', true);
        setTimeout(() => location.reload(), 1000);
      }
      this.submitting = false;
    }
  }
}
