<?php view_layout('app'); view_start('title'); ?>Entregas<?php view_end(); ?>
<?php view_start('content'); ?>
<?php
/**
 * OBS-02 — timeline de entregas (o que saiu, pra quem, com que resultado).
 * Classes completas nos mapas (nunca concatenadas): o purge do Tailwind só vê
 * o que está literal no arquivo.
 */
$statusMeta = [
    'sent'    => ['label' => 'Entregue',  'cls' => 'text-emerald-300 bg-emerald-500/10'],
    'pending' => ['label' => 'Na fila',   'cls' => 'text-amber-300 bg-amber-500/10'],
    'failed'  => ['label' => 'Falhou',    'cls' => 'text-rose-300 bg-rose-500/10'],
];
$channelMeta = [
    'whatsapp' => ['label' => 'WhatsApp', 'cls' => 'text-emerald-300'],
    'email'    => ['label' => 'E-mail',   'cls' => 'text-blue-300'],
];
$fmtDate = static function (?string $dt): string {
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
};
?>

<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
  <div>
    <h1 class="text-xl font-bold text-white">Entregas</h1>
    <p class="text-sm text-gray-500 mt-0.5">Tudo que o sistema enviou por WhatsApp e e-mail nos últimos 30 dias.</p>
  </div>
  <a href="/automations" class="btn-secondary text-sm px-4 py-2">Voltar às automações</a>
</div>

<!-- Resumo -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  <?php
  $cards = [
      ['Total',    $stats['total'],   'text-white'],
      ['Entregue', $stats['sent'],    'text-emerald-300'],
      ['Na fila',  $stats['pending'], 'text-amber-300'],
      ['Falhou',   $stats['failed'],  'text-rose-300'],
  ];
  foreach ($cards as [$label, $value, $cls]): ?>
    <div class="card p-4">
      <p class="text-xs text-gray-500 mb-1"><?= e($label) ?></p>
      <p class="text-2xl font-bold <?= $cls ?>"><?= (int) $value ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filtros -->
<form method="GET" class="flex items-end gap-3 mb-4 flex-wrap">
  <div>
    <label class="label-field" for="channel">Canal</label>
    <select id="channel" name="channel" class="input-field w-40 py-2">
      <option value="">Todos</option>
      <option value="whatsapp" <?= ($filters['channel'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
      <option value="email" <?= ($filters['channel'] ?? '') === 'email' ? 'selected' : '' ?>>E-mail</option>
    </select>
  </div>
  <div>
    <label class="label-field" for="status">Situação</label>
    <select id="status" name="status" class="input-field w-40 py-2">
      <option value="">Todas</option>
      <option value="sent" <?= ($filters['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Entregue</option>
      <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Na fila</option>
      <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Falhou</option>
    </select>
  </div>
  <button type="submit" class="btn-primary text-sm px-4 py-2.5">Filtrar</button>
</form>

<?php if (empty($deliveries)): ?>
  <!-- Estado vazio: nunca uma área em branco sem explicação -->
  <div class="card p-12 text-center">
    <svg class="w-10 h-10 text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
    <p class="text-sm text-gray-400">Nenhuma entrega no período</p>
    <p class="text-xs text-gray-600 mt-1">Quando uma automação enviar WhatsApp ou e-mail, o registro aparece aqui.</p>
  </div>
<?php else: ?>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs uppercase tracking-wide text-gray-500 border-b border-white/[0.06]">
            <th class="text-left font-medium px-4 py-3">Quando</th>
            <th class="text-left font-medium px-4 py-3">Canal</th>
            <th class="text-left font-medium px-4 py-3">Destinatário</th>
            <th class="text-left font-medium px-4 py-3">Mensagem</th>
            <th class="text-left font-medium px-4 py-3">Situação</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/[0.04]">
          <?php foreach ($deliveries as $d):
            $st = $statusMeta[$d['status']] ?? ['label' => $d['status'], 'cls' => 'text-gray-400 bg-gray-500/10'];
            $ch = $channelMeta[$d['channel']] ?? ['label' => $d['channel'], 'cls' => 'text-gray-400'];
          ?>
          <tr class="hover:bg-white/[0.02] transition-colors">
            <td class="px-4 py-3 text-gray-400 whitespace-nowrap" title="<?= e((string) $d['created_at']) ?>">
              <?= e($fmtDate($d['sent_at'] ?? $d['created_at'])) ?>
            </td>
            <td class="px-4 py-3 whitespace-nowrap <?= $ch['cls'] ?>"><?= e($ch['label']) ?></td>
            <td class="px-4 py-3 text-gray-300"><?= e((string) $d['recipient']) ?></td>
            <td class="px-4 py-3 text-gray-500"><?= e((string) $d['template']) ?></td>
            <td class="px-4 py-3">
              <span class="badge <?= $st['cls'] ?>"><?= e($st['label']) ?></span>
              <?php if ($d['status'] === 'failed' && !empty($d['last_error'])): ?>
                <!-- O motivo do erro é o que transforma "falhou" em algo acionável -->
                <p class="text-xs text-rose-400/70 mt-1 max-w-md truncate" title="<?= e((string) $d['last_error']) ?>">
                  <?= e((string) $d['last_error']) ?>
                </p>
              <?php elseif ($d['status'] === 'pending' && (int) $d['attempts'] > 0): ?>
                <p class="text-xs text-amber-400/60 mt-1"><?= (int) $d['attempts'] ?>ª tentativa</p>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php view_end(); ?>
