<?php
view_layout('print');
view_start('title');
echo 'Contrato — ' . e($contract['title']);
view_end();
view_start('content');

$statusLabels = ['draft'=>'Rascunho','active'=>'Ativo','expired'=>'Expirado','cancelled'=>'Cancelado'];
$recurrenceLabels = ['monthly'=>'Mensal','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'];
?>

<!-- Header -->
<div class="header">
  <div class="logo"><?= e(env('APP_NAME', 'YVE Agency')) ?></div>
  <div class="doc-meta">
    <div class="doc-number">CONTRATO #<?= e($contract['id']) ?></div>
    <div class="doc-date">Gerado em <?= date('d/m/Y') ?></div>
    <div style="margin-top:6px">
      <span class="badge badge-<?= e($contract['status']) ?>"><?= $statusLabels[$contract['status']] ?? $contract['status'] ?></span>
    </div>
  </div>
</div>

<!-- Parties -->
<div class="parties">
  <div>
    <div class="party-label">Prestador</div>
    <div class="party-name"><?= e(env('APP_NAME', 'YVE Agency')) ?></div>
  </div>
  <div>
    <div class="party-label">Cliente</div>
    <div class="party-name"><?= e($contract['client_name']) ?></div>
  </div>
</div>

<!-- Meta -->
<div class="meta-grid">
  <div class="meta-item">
    <div class="meta-label">Título</div>
    <div class="meta-value"><?= e($contract['title']) ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Valor</div>
    <div class="meta-value"><?= e($contract['currency_code']) ?> <?= number_format((float)$contract['value'],2,',','.') ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Recorrência</div>
    <div class="meta-value"><?= $contract['recurring'] ? ($recurrenceLabels[$contract['recurrence']] ?? 'Sim') : 'Não' ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Início</div>
    <div class="meta-value"><?= $contract['start_date'] ? date('d/m/Y', strtotime($contract['start_date'])) : '—' ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Término</div>
    <div class="meta-value"><?= $contract['end_date'] ? date('d/m/Y', strtotime($contract['end_date'])) : '—' ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Assinado em</div>
    <div class="meta-value"><?= $contract['signed_at'] ? date('d/m/Y', strtotime($contract['signed_at'])) : '—' ?></div>
  </div>
</div>

<!-- Descrição -->
<?php if ($contract['description'] ?? null): ?>
<div style="margin-bottom:24px">
  <p class="section-title">Descrição do Serviço</p>
  <p style="font-size:13px;color:#374151;line-height:1.7;white-space:pre-line"><?= e($contract['description']) ?></p>
</div>
<?php endif; ?>

<!-- Notas -->
<?php if ($contract['notes'] ?? null): ?>
<div class="notes">
  <div class="notes-label">Observações</div>
  <p><?= e($contract['notes']) ?></p>
</div>
<?php endif; ?>

<!-- Assinatura -->
<div style="margin-top:56px;display:grid;grid-template-columns:1fr 1fr;gap:40px">
  <?php foreach (['Agência: ' . env('APP_NAME','YVE Agency'), 'Cliente: ' . $contract['client_name']] as $party): ?>
  <div>
    <div style="border-top:1px solid #9ca3af;margin-bottom:6px"></div>
    <div style="font-size:12px;color:#6b7280"><?= e($party) ?></div>
    <div style="font-size:11px;color:#9ca3af;margin-top:4px">Data: ____/____/________</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Footer -->
<div class="footer">
  <span><?= e(env('APP_NAME', 'YVE Agency')) ?> — Documento gerado em <?= date('d/m/Y \à\s H:i') ?></span>
  <span>Contrato #<?= e($contract['id']) ?></span>
</div>

<?php view_end(); ?>
