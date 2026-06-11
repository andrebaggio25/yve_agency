<?php
// Standalone — uses print layout, no session/auth checks needed beyond what the controller enforces
if (!function_exists('view_layout')) {
    // Fallback if called outside normal view pipeline (shouldn't happen)
    http_response_code(500); exit;
}
view_layout('print');
view_start('title');
echo 'Fatura ' . e($invoice['invoice_number']);
view_end();
view_start('content');

$statusLabels = [
    'draft'=>'Rascunho','sent'=>'Enviada','paid'=>'Paga',
    'overdue'=>'Vencida','partial'=>'Parcial','cancelled'=>'Cancelada',
];
$methodLabels = ['pix'=>'PIX','boleto'=>'Boleto','credit_card'=>'Cartão de Crédito','bank_transfer'=>'TED/DOC','cash'=>'Dinheiro','other'=>'Outro'];
$remaining = (float)$invoice['total'] - (float)$invoice['amount_paid'];
?>

<!-- Header -->
<div class="header">
  <div class="logo"><?= e(env('APP_NAME', 'YVE Agency')) ?></div>
  <div class="doc-meta">
    <div class="doc-number">FATURA <?= e($invoice['invoice_number']) ?></div>
    <div class="doc-date">Emitida em <?= date('d/m/Y') ?></div>
    <div style="margin-top:6px">
      <span class="badge badge-<?= e($invoice['status']) ?>"><?= $statusLabels[$invoice['status']] ?? $invoice['status'] ?></span>
    </div>
  </div>
</div>

<!-- Parties -->
<div class="parties">
  <div>
    <div class="party-label">De (Agência)</div>
    <div class="party-name"><?= e(env('APP_NAME', 'YVE Agency')) ?></div>
  </div>
  <div>
    <div class="party-label">Para (Cliente)</div>
    <div class="party-name"><?= e($invoice['client_name']) ?></div>
    <?php if ($invoice['contract_title'] ?? null): ?>
    <div class="party-info">Contrato: <?= e($invoice['contract_title']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Meta -->
<div class="meta-grid">
  <div class="meta-item">
    <div class="meta-label">Título</div>
    <div class="meta-value"><?= e($invoice['title']) ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Vencimento</div>
    <div class="meta-value"><?= $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '—' ?></div>
  </div>
  <div class="meta-item">
    <div class="meta-label">Moeda</div>
    <div class="meta-value"><?= e($invoice['currency_code']) ?></div>
  </div>
</div>

<!-- Itens -->
<?php if (!empty($invoice['items'])): ?>
<p class="section-title">Itens</p>
<table>
  <thead>
    <tr>
      <th style="width:50%">Descrição</th>
      <th style="text-align:right">Qtd</th>
      <th style="text-align:right">Preço Unit.</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($invoice['items'] as $item): ?>
    <tr>
      <td><?= e($item['description']) ?></td>
      <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((float)$item['quantity'],3,',','.') ?></td>
      <td style="text-align:right">R$ <?= number_format((float)$item['unit_price'],2,',','.') ?></td>
      <td>R$ <?= number_format((float)$item['total_price'],2,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Totals -->
<div class="totals">
  <div class="totals-row"><span>Subtotal</span><span>R$ <?= number_format((float)$invoice['subtotal'],2,',','.') ?></span></div>
  <?php if ((float)$invoice['discount'] > 0): ?>
  <div class="totals-row"><span>Desconto</span><span style="color:#dc2626">- R$ <?= number_format((float)$invoice['discount'],2,',','.') ?></span></div>
  <?php endif; ?>
  <?php if ((float)$invoice['tax'] > 0): ?>
  <div class="totals-row"><span>Impostos</span><span>R$ <?= number_format((float)$invoice['tax'],2,',','.') ?></span></div>
  <?php endif; ?>
  <div class="totals-row total-final"><span>Total</span><span>R$ <?= number_format((float)$invoice['total'],2,',','.') ?></span></div>
  <?php if ((float)$invoice['amount_paid'] > 0): ?>
  <div class="totals-row paid"><span>Recebido</span><span>R$ <?= number_format((float)$invoice['amount_paid'],2,',','.') ?></span></div>
  <?php if ($remaining > 0): ?>
  <div class="totals-row remaining"><span>Saldo restante</span><span>R$ <?= number_format($remaining,2,',','.') ?></span></div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Pagamentos registrados -->
<?php if (!empty($invoice['payments'])): ?>
<div style="margin-top:32px">
  <p class="section-title">Pagamentos Registrados</p>
  <table>
    <thead>
      <tr>
        <th>Data</th>
        <th>Método</th>
        <th>Referência</th>
        <th>Valor</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoice['payments'] as $p): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
        <td><?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></td>
        <td><?= e($p['reference'] ?? '—') ?></td>
        <td>R$ <?= number_format((float)$p['amount'],2,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Notes -->
<?php if ($invoice['notes'] ?? null): ?>
<div class="notes">
  <div class="notes-label">Observações</div>
  <p><?= e($invoice['notes']) ?></p>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="footer">
  <span><?= e(env('APP_NAME', 'YVE Agency')) ?> — Documento gerado em <?= date('d/m/Y \à\s H:i') ?></span>
  <span>Fatura <?= e($invoice['invoice_number']) ?></span>
</div>

<?php view_end(); ?>
