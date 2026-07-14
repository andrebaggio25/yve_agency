<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatório — <?= e($client['name']) ?></title>
  <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
  <style>
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { background: #fff; color: #111; font-family: 'Inter', system-ui, sans-serif; }
    @media screen {
      body { background: #f3f4f6; }
      .page { max-width: 900px; margin: 2rem auto; background: #fff; border-radius: 12px;
               box-shadow: 0 4px 32px rgba(0,0,0,0.12); padding: 3rem; }
    }
    @media print {
      .no-print { display: none !important; }
      .page { padding: 0; box-shadow: none; }
      .break-before { page-break-before: always; }
    }
    .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
    .kpi-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem 1.25rem; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    thead tr { background: #f9fafb; }
    th { text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
    td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }
    .section-title { font-size: 14px; font-weight: 700; color: #374151; margin: 2rem 0 .75rem; border-left: 3px solid #c6a15b; padding-left: .75rem; }
  </style>
</head>
<body>
<?php
$fmtMoney = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtInt   = fn($v) => number_format((int)$v, 0, '.', '.');
$fmtFloat = fn($v, $d = 2) => number_format((float)$v, $d, ',', '.');

$planStatusLabels = ['draft' => 'Rascunho', 'sent' => 'Aguardando', 'approved' => 'Aprovado', 'revision' => 'Revisão', 'archived' => 'Arquivado'];
$planStatusColors = ['draft' => '#6b7280', 'sent' => '#d97706', 'approved' => '#059669', 'revision' => '#dc2626', 'archived' => '#9ca3af'];
$taskStatusLabels = ['todo' => 'A Fazer', 'in_progress' => 'Em Andamento', 'review' => 'Revisão', 'done' => 'Concluída'];
$priorityColors   = ['low' => '#6b7280', 'medium' => '#d97706', 'high' => '#dc2626', 'urgent' => '#c6a15b'];
$invoiceStatusLabels = ['draft' => 'Rascunho', 'sent' => 'Enviada', 'paid' => 'Paga', 'overdue' => 'Atrasada', 'partial' => 'Parcial', 'cancelled' => 'Cancelada'];
$invoiceStatusColors = ['paid' => '#059669', 'sent' => '#2563eb', 'overdue' => '#dc2626', 'partial' => '#d97706', 'draft' => '#9ca3af', 'cancelled' => '#9ca3af'];
?>

<div class="page">
  <!-- Header -->
  <div class="flex items-start justify-between mb-8">
    <div>
      <div style="font-size:11px;color:#c6a15b;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">
        <?= e($agency['name'] ?? 'Agência') ?>
      </div>
      <h1 style="font-size:24px;font-weight:800;color:#111;margin:0"><?= e($client['name']) ?></h1>
      <p style="font-size:13px;color:#6b7280;margin:.25rem 0 0">
        Relatório de desempenho · <?= date('d/m/Y', strtotime($since)) ?> a <?= date('d/m/Y', strtotime($until)) ?>
      </p>
    </div>
    <div class="no-print" style="display:flex;gap:.5rem;align-items:center">
      <!-- UX-04: PDF de verdade (gerado no servidor), não "imprima você mesmo" -->
      <a href="/relatorio-executivo/cliente/<?= (int) $client['id'] ?>/pdf"
         style="background:#c6a15b;color:#0d0d14;padding:.5rem 1.25rem;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none">
        Baixar PDF
      </a>
      <button onclick="window.print()"
              style="background:transparent;color:#6b7280;border:1px solid #e5e7eb;padding:.5rem 1rem;border-radius:8px;font-size:13px;cursor:pointer">
        Imprimir
      </button>
      <a href="/relatorio-executivo" style="font-size:13px;color:#6b7280;text-decoration:none">← Voltar</a>
    </div>
  </div>

  <!-- ── Financial KPIs ─────────────────────────────────────────────── -->
  <p class="section-title">Financeiro</p>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="kpi-card">
      <p style="font-size:11px;color:#9ca3af;margin:0 0 .25rem">Total Faturado</p>
      <p style="font-size:20px;font-weight:800;color:#111;margin:0"><?= $fmtMoney($invoiceSummary['total_billed'] ?? 0) ?></p>
    </div>
    <div class="kpi-card">
      <p style="font-size:11px;color:#9ca3af;margin:0 0 .25rem">Total Recebido</p>
      <p style="font-size:20px;font-weight:800;color:#059669;margin:0"><?= $fmtMoney($invoiceSummary['total_paid'] ?? 0) ?></p>
    </div>
    <div class="kpi-card">
      <p style="font-size:11px;color:#9ca3af;margin:0 0 .25rem">Pendente</p>
      <p style="font-size:20px;font-weight:800;color:<?= ($invoiceSummary['total_pending'] ?? 0) > 0 ? '#d97706' : '#6b7280' ?>;margin:0"><?= $fmtMoney($invoiceSummary['total_pending'] ?? 0) ?></p>
    </div>
  </div>

  <?php if (!empty($invoices)): ?>
  <table>
    <thead>
      <tr>
        <th>Nº</th><th>Título</th><th>Vencimento</th><th style="text-align:right">Valor</th><th style="text-align:right">Pago</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (array_slice($invoices, 0, 15) as $inv): ?>
      <tr>
        <td style="color:#6b7280"><?= e($inv['invoice_number'] ?? '') ?></td>
        <td><?= e($inv['title']) ?></td>
        <td style="color:#6b7280"><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></td>
        <td style="text-align:right"><?= $fmtMoney($inv['total']) ?></td>
        <td style="text-align:right;color:#059669"><?= $fmtMoney($inv['amount_paid']) ?></td>
        <td>
          <span class="badge" style="background:<?= $invoiceStatusColors[$inv['status']] ?? '#9ca3af' ?>22;color:<?= $invoiceStatusColors[$inv['status']] ?? '#9ca3af' ?>">
            <?= $invoiceStatusLabels[$inv['status']] ?? $inv['status'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Ads Metrics ────────────────────────────────────────────────── -->
  <?php if ($adMetrics && $adMetrics['impressions'] > 0): ?>
  <p class="section-title">Tráfego Pago — <?= date('d/m/Y', strtotime($since)) ?> a <?= date('d/m/Y', strtotime($until)) ?></p>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1.5rem">
    <?php foreach ([
      ['Investimento', $fmtMoney($adMetrics['spend'])],
      ['Impressões',   $fmtInt($adMetrics['impressions'])],
      ['Cliques',      $fmtInt($adMetrics['clicks'])],
      ['Conversões',   $fmtInt($adMetrics['conversions'])],
      ['ROAS',         $fmtFloat($adMetrics['roas']) . 'x'],
    ] as [$label, $value]): ?>
    <div class="kpi-card" style="text-align:center">
      <p style="font-size:10px;color:#9ca3af;margin:0 0 .25rem"><?= $label ?></p>
      <p style="font-size:16px;font-weight:800;color:#111;margin:0"><?= $value ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Organic Metrics ───────────────────────────────────────────── -->
  <?php if ($organicMetrics && $organicMetrics['posts'] > 0): ?>
  <p class="section-title">Orgânico — <?= date('d/m/Y', strtotime($since)) ?> a <?= date('d/m/Y', strtotime($until)) ?></p>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.5rem">
    <?php foreach ([
      ['Publicações',   $fmtInt($organicMetrics['posts'])],
      ['Alcance',       $fmtInt($organicMetrics['reach'])],
      ['Impressões',    $fmtInt($organicMetrics['impressions'])],
      ['Engajamentos',  $fmtInt($organicMetrics['engagement'])],
    ] as [$label, $value]): ?>
    <div class="kpi-card" style="text-align:center">
      <p style="font-size:10px;color:#9ca3af;margin:0 0 .25rem"><?= $label ?></p>
      <p style="font-size:16px;font-weight:800;color:#111;margin:0"><?= $value ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Content Plans ──────────────────────────────────────────────── -->
  <?php if (!empty($plans)): ?>
  <p class="section-title">Planos de Conteúdo</p>
  <table style="margin-bottom:1.5rem">
    <thead>
      <tr><th>Título</th><th>Semana</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($plans as $plan): ?>
      <tr>
        <td><?= e($plan['title']) ?></td>
        <td style="color:#6b7280"><?= $plan['week_start'] ? date('d/m/Y', strtotime($plan['week_start'])) : '—' ?></td>
        <td>
          <span class="badge" style="background:<?= $planStatusColors[$plan['status']] ?? '#6b7280' ?>22;color:<?= $planStatusColors[$plan['status']] ?? '#6b7280' ?>">
            <?= $planStatusLabels[$plan['status']] ?? $plan['status'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Tasks ─────────────────────────────────────────────────────── -->
  <?php if (!empty($tasks)): ?>
  <p class="section-title">Tarefas</p>
  <table>
    <thead>
      <tr><th>Título</th><th>Status</th><th>Prioridade</th><th>Responsável</th><th>Prazo</th></tr>
    </thead>
    <tbody>
      <?php foreach ($tasks as $task): ?>
      <tr>
        <td><?= e($task['title']) ?></td>
        <td><span class="badge" style="background:#f3f4f6;color:#374151"><?= $taskStatusLabels[$task['status']] ?? $task['status'] ?></span></td>
        <td><span style="color:<?= $priorityColors[$task['priority']] ?? '#6b7280' ?>;font-weight:600;font-size:12px"><?= ucfirst($task['priority']) ?></span></td>
        <td style="color:#6b7280"><?= e($task['assigned_name'] ?? '—') ?></td>
        <td style="color:<?= ($task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done') ? '#dc2626' : '#6b7280' ?>">
          <?= $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '—' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Footer -->
  <div style="margin-top:3rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:11px;color:#9ca3af">
    <span><?= e($agency['name'] ?? 'Agência') ?> · Relatório gerado em <?= date('d/m/Y H:i') ?></span>
    <span>Período: <?= date('d/m/Y', strtotime($since)) ?> – <?= date('d/m/Y', strtotime($until)) ?></span>
  </div>
</div>
</body>
</html>
