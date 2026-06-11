<?php view_layout('app'); view_start('content'); ?>

<div class="max-w-xl mx-auto" x-data="waSettings()">
  <div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-violet-500 mb-1">Configurações</p>
    <h1 class="text-2xl font-bold text-white">WhatsApp</h1>
    <p class="mt-1 text-sm text-gray-400">Conecte o número WhatsApp da sua agência para notificações automáticas.</p>
  </div>

  <?php if (!$globalOk): ?>
  <!-- Estado: API global não configurada -->
  <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 text-center">
    <svg class="w-10 h-10 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <p class="text-sm font-semibold text-amber-300 mb-1">WhatsApp não disponível</p>
    <p class="text-sm text-gray-500">A Evolution API ainda não foi configurada pelo administrador da plataforma. Entre em contato para habilitação.</p>
  </div>

  <?php elseif (!$instance): ?>
  <!-- Estado: API OK mas sem instância ainda -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-8 text-center">
    <div class="w-16 h-16 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
      </svg>
    </div>
    <h2 class="text-base font-semibold text-white mb-2">Ative o WhatsApp</h2>
    <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
      Nenhuma instância configurada. Clique em "Ativar WhatsApp" para criar sua instância e conectar seu número.
    </p>
    <button @click="activate()" :disabled="loading"
            class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-500/20 hover:bg-violet-500 transition-all hover:scale-105 active:scale-95 disabled:opacity-50">
      <svg class="w-4 h-4" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      <span x-text="loading ? 'Criando instância...' : 'Ativar WhatsApp'"></span>
    </button>
    <div x-show="error" class="mt-4 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300" x-text="error"></div>
  </div>

  <?php else: ?>
  <!-- Estado: instância existe -->

  <!-- Status card -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6 mb-4">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-sm font-semibold text-white">Status da conexão</h2>
      <button @click="checkStatus()" :disabled="loadingStatus"
              class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-colors disabled:opacity-50">
        <svg class="w-3.5 h-3.5" :class="{'animate-spin': loadingStatus}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Verificar
      </button>
    </div>

    <!-- Connected -->
    <template x-if="status === 'connected'">
      <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3">
        <div class="flex items-center gap-3">
          <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></div>
          <div>
            <p class="text-sm font-medium text-emerald-300">Conectado</p>
            <p class="text-xs text-gray-400" x-show="phone" x-text="'Número: +' + phone"></p>
          </div>
          <div class="ml-auto">
            <button @click="disconnect()" :disabled="loading"
                    class="text-xs text-red-400 hover:text-red-300 transition-colors disabled:opacity-50">
              Desconectar
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Pending/disconnected -->
    <template x-if="status !== 'connected'">
      <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3">
        <div class="flex items-center gap-3">
          <div class="w-2.5 h-2.5 rounded-full bg-amber-400 shrink-0"></div>
          <div class="flex-1">
            <p class="text-sm font-medium text-amber-300" x-text="status === 'disconnected' ? 'Desconectado' : 'Aguardando conexão'"></p>
            <p class="text-xs text-gray-500">Escaneie o QR Code para conectar.</p>
          </div>
          <button @click="startQr()" :disabled="loading"
                  class="inline-flex items-center gap-1.5 rounded-xl bg-violet-600/20 border border-violet-500/30 px-3 py-1.5 text-xs text-violet-300 hover:bg-violet-600/30 transition-colors disabled:opacity-50">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            Conectar via QR
          </button>
        </div>
      </div>
    </template>

    <!-- QR Code display -->
    <div x-show="showQr" x-transition class="mt-4 rounded-xl border border-white/10 bg-white/5 p-5 text-center">
      <p class="text-sm text-gray-300 mb-3 font-medium">Escaneie com o WhatsApp</p>
      <p class="text-xs text-gray-500 mb-4">Abra o WhatsApp → Dispositivos vinculados → Vincular dispositivo</p>

      <div x-show="qrLoading" class="text-gray-500 text-sm py-8">
        <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Carregando QR Code...
      </div>

      <div x-show="qrImage">
        <img :src="qrImage" alt="QR Code" class="mx-auto rounded-xl max-w-[200px] bg-white p-2">
        <p class="text-xs text-gray-500 mt-3">O QR Code expira em ~30s. Clique em "Atualizar QR" se necessário.</p>
        <button @click="loadQr()" class="mt-2 text-xs text-violet-400 hover:text-violet-300 transition-colors">
          Atualizar QR
        </button>
      </div>

      <div x-show="pairingCode" class="mt-4">
        <p class="text-xs text-gray-500 mb-1">Ou use o código de pareamento:</p>
        <code class="text-lg font-mono font-bold tracking-widest text-white" x-text="pairingCode"></code>
      </div>

      <p x-show="qrError" class="text-red-400 text-sm mt-3" x-text="qrError"></p>

      <div x-show="pollingActive" class="mt-3 flex items-center justify-center gap-2 text-xs text-gray-500">
        <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Aguardando escaneamento...
      </div>
    </div>
  </div>

  <!-- Instance info -->
  <div class="rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 text-xs text-gray-500">
    <div class="flex items-center justify-between">
      <span>Instância: <code class="text-gray-400"><?= e($instance['instance_name']) ?></code></span>
      <button @click="configureWebhook()" class="text-violet-400 hover:text-violet-300 transition-colors">
        Reconfigurar webhook
      </button>
    </div>
    <p x-show="webhookMsg" class="mt-1 text-violet-400" x-text="webhookMsg"></p>
  </div>

  <?php endif; ?>
</div>

<script>
function waSettings() {
  return {
    status:        '<?= e($instance['status'] ?? 'no_instance') ?>',
    phone:         <?= !empty($instance['phone_number']) ? json_encode($instance['phone_number']) : 'null' ?>,
    loading:       false,
    loadingStatus: false,
    showQr:        false,
    qrLoading:     false,
    qrImage:       null,
    pairingCode:   null,
    qrError:       null,
    pollingActive: false,
    pollingTimer:  null,
    error:         null,
    webhookMsg:    null,

    csrf() {
      return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    // Criar instância (estado inicial)
    async activate() {
      this.loading = true;
      this.error   = null;
      try {
        const r = await fetch('/configuracoes/whatsapp/ativar', {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() }
        });
        const d = await r.json();
        if (d.ok) {
          window.location.reload();
        } else {
          this.error = d.error || 'Erro ao criar instância.';
        }
      } catch {
        this.error = 'Erro de rede.';
      } finally {
        this.loading = false;
      }
    },

    // Verificar status manualmente
    async checkStatus() {
      this.loadingStatus = true;
      try {
        const r = await fetch('/configuracoes/whatsapp/status');
        const d = await r.json();
        this.status = d.status || (d.connected ? 'connected' : 'disconnected');
        this.phone  = d.phone || null;
      } finally {
        this.loadingStatus = false;
      }
    },

    // Iniciar fluxo de conexão por QR
    async startQr() {
      this.showQr  = true;
      await this.loadQr();
      this.startPolling();
    },

    // Buscar QR Code
    async loadQr() {
      this.qrLoading = true;
      this.qrImage   = null;
      this.qrError   = null;
      try {
        const r = await fetch('/configuracoes/whatsapp/qr');
        const d = await r.json();
        if (d.qr_code) {
          this.qrImage     = d.qr_code.startsWith('data:') ? d.qr_code : 'data:image/png;base64,' + d.qr_code;
          this.pairingCode = d.pairing_code || null;
        } else {
          this.qrError = d.error || 'Não foi possível gerar o QR Code.';
        }
      } catch {
        this.qrError = 'Erro ao conectar com a API.';
      } finally {
        this.qrLoading = false;
      }
    },

    // Polling de status a cada 3s por até 5 min
    startPolling() {
      this.pollingActive = true;
      const maxMs  = 5 * 60 * 1000;
      const start  = Date.now();

      this.pollingTimer = setInterval(async () => {
        if (Date.now() - start > maxMs) {
          this.stopPolling();
          return;
        }
        try {
          const r = await fetch('/configuracoes/whatsapp/status');
          const d = await r.json();
          if (d.connected) {
            this.status = 'connected';
            this.phone  = d.phone || null;
            this.stopPolling();
            this.showQr = false;
            // Tocar som ou mostrar toast
          }
        } catch {}
      }, 3000);
    },

    stopPolling() {
      this.pollingActive = false;
      if (this.pollingTimer) {
        clearInterval(this.pollingTimer);
        this.pollingTimer = null;
      }
    },

    // Desconectar
    async disconnect() {
      if (!confirm('Desconectar o WhatsApp desta instância?')) return;
      this.loading = true;
      try {
        const r = await fetch('/configuracoes/whatsapp/desconectar', {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() }
        });
        const d = await r.json();
        if (d.ok) {
          this.status = 'disconnected';
          this.phone  = null;
        }
      } finally {
        this.loading = false;
      }
    },

    // Reconfigurar webhook
    async configureWebhook() {
      this.webhookMsg = 'Configurando...';
      try {
        const r = await fetch('/configuracoes/whatsapp/webhook', {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() }
        });
        const d = await r.json();
        this.webhookMsg = d.ok ? '✓ Webhook configurado.' : '✗ Falha: ' + (d.error || '');
      } catch {
        this.webhookMsg = '✗ Erro de rede.';
      }
    },
  };
}
</script>

<?php view_end(); ?>
