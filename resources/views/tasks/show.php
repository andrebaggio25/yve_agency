<?php view_layout('app'); view_start('title'); ?><?= e($task['title']) ?><?php view_end(); ?>
<?php view_start('content'); ?>

<?php
$statusLabels   = ['todo' => 'A fazer', 'in_progress' => 'Em andamento', 'review' => 'Revisão', 'done' => 'Concluído'];
$statusColors   = [
    'todo'        => 'bg-gray-500/15 text-gray-300',
    'in_progress' => 'bg-blue-500/15 text-blue-300',
    'review'      => 'bg-yellow-500/15 text-yellow-300',
    'done'        => 'bg-green-500/15 text-green-300',
];
$priorityColors = [
    'urgent' => 'text-red-400 bg-red-500/10',
    'high'   => 'text-orange-400 bg-orange-500/10',
    'medium' => 'text-yellow-400 bg-yellow-500/10',
    'low'    => 'text-gray-400 bg-gray-500/10',
];
$priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
$overdue = $task['due_date'] && $task['status'] !== 'done' && strtotime($task['due_date']) < strtotime('today');
?>

<nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
  <a href="/tarefas" class="hover:text-gray-300">Tarefas</a>
  <span>/</span>
  <span class="text-gray-300 truncate max-w-xs"><?= e($task['title']) ?></span>
</nav>

<div class="max-w-2xl">
  <div class="card p-6 mb-4">
    <!-- Badges topo -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium <?= $statusColors[$task['status']] ?>">
        <?= $statusLabels[$task['status']] ?>
      </span>
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $priorityColors[$task['priority']] ?>">
        <?= $priorityLabels[$task['priority']] ?>
      </span>
      <?php if ($overdue): ?>
      <span class="text-xs text-red-400 font-semibold">⚠ Atrasada</span>
      <?php endif; ?>
    </div>

    <h1 class="text-xl font-semibold text-white mb-4"><?= e($task['title']) ?></h1>

    <?php if ($task['description']): ?>
    <div class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap mb-6">
      <?= e($task['description']) ?>
    </div>
    <?php endif; ?>

    <!-- Metadados -->
    <div class="grid grid-cols-2 gap-4 text-sm border-t border-white/[0.06] pt-5">
      <div>
        <p class="text-xs text-gray-400 mb-1">Cliente</p>
        <p class="text-gray-300"><?= e($task['client_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Responsável</p>
        <p class="text-gray-300"><?= e($task['assigned_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Criada por</p>
        <p class="text-gray-300"><?= e($task['created_by_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Data de entrega</p>
        <p class="<?= $overdue ? 'text-red-400 font-medium' : 'text-gray-300' ?>">
          <?= $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '—' ?>
        </p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Criada em</p>
        <p class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($task['created_at'])) ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Atualizada em</p>
        <p class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($task['updated_at'])) ?></p>
      </div>
    </div>
  </div>

  <!-- Chat da Tarefa -->
  <div class="card mt-4"
       x-data="planChat(<?= $task['id'] ?>, 'task')"
       x-init="loadComments()">
    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
      <h2 class="text-sm font-semibold text-white flex items-center gap-2">
        <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        Comentários
      </h2>
      <button @click="loadComments()" class="text-gray-400 hover:text-gray-400 transition-colors">
        <svg class="w-3.5 h-3.5" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
      </button>
    </div>
    <div class="px-5 py-4 space-y-3 max-h-72 overflow-y-auto" x-ref="chatMessages">
      <template x-if="comments.length === 0 && !loading">
        <p class="text-xs text-gray-400 text-center py-3">Nenhum comentário ainda.</p>
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
    <div class="px-5 pb-4 pt-2 border-t border-white/5">
      <div class="flex gap-2">
        <textarea x-model="newMessage" rows="2"
                  placeholder="Adicionar comentário..."
                  @keydown.ctrl.enter.prevent="sendComment()"
                  class="flex-1 rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 resize-none"></textarea>
        <button @click="sendComment()" :disabled="sending || !newMessage.trim()"
                class="self-end rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-gray-950 transition-all hover:bg-brand-500 disabled:opacity-40">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </button>
      </div>
      <p class="text-[10px] text-gray-400 mt-1">Ctrl+Enter para enviar</p>
    </div>
  </div>

  <!-- Ações -->
  <div class="flex items-center gap-3 flex-wrap mt-4">
    <?php if (\App\Support\Auth::can('tasks.edit')): ?>
    <a href="/tarefas/<?= $task['id'] ?>/editar" class="btn-secondary text-sm px-4 py-2">Editar</a>
    <?php endif; ?>
    <?php if (\App\Support\Auth::can('tasks.edit')): ?>
    <!-- Mudança rápida de status -->
    <div x-data="{open: false}" class="relative">
      <button @click="open = !open" class="btn-secondary text-sm px-4 py-2">
        Mover para &darr;
      </button>
      <div x-show="open" @click.outside="open = false"
           class="absolute left-0 mt-1 w-44 card shadow-xl z-20 py-1 text-sm">
        <?php foreach ($statusLabels as $v => $l): ?>
        <?php if ($v !== $task['status']): ?>
        <form method="POST" action="/tarefas/<?= $task['id'] ?>/status">
          <?= csrf_field() ?>
          <input type="hidden" name="status" value="<?= $v ?>">
          <button class="w-full text-left px-4 py-2 hover:bg-white/[0.05] text-gray-300"><?= $l ?></button>
        </form>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (\App\Support\Auth::can('tasks.delete')): ?>
    <form method="POST" action="/tarefas/<?= $task['id'] ?>" class="ml-auto"
          onsubmit="return confirm('Excluir esta tarefa?')">
      <?= csrf_field() ?>
      <input type="hidden" name="_method" value="DELETE">
      <button class="text-sm text-red-400 hover:text-red-300">Excluir</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php view_start('scripts'); ?>
<script>
function planChat(entityId, entityType = 'content_plan') {
    return {
        comments: [], newMessage: '', loading: false, sending: false, _interval: null,
        async loadComments() {
            this.loading = true;
            try {
                const r = await fetch(`/api/comentarios/${entityType}/${entityId}`, {
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
            } catch(e) {}
            this.loading = false;
        },
        async sendComment() {
            if (this.sending || !this.newMessage.trim()) return;
            this.sending = true;
            try {
                const r = await fetch(`/api/comentarios/${entityType}/${entityId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ message: this.newMessage.trim() })
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
            } catch(e) {}
            this.sending = false;
        },
        chatDate(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
                + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        },
        init() { this._interval = setInterval(() => this.loadComments(), 30000); },
        destroy() { clearInterval(this._interval); },
    };
}
</script>
<?php view_end(); ?>

<?php view_end(); ?>
