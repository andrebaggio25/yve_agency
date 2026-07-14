<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(view_slot('title', 'Documento')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; }
    .page { max-width: 800px; margin: 0 auto; padding: 40px 40px 60px; }

    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 36px; padding-bottom: 24px; border-bottom: 2px solid #b8914c; }
    .logo { font-size: 22px; font-weight: 800; color: #b8914c; letter-spacing: -0.5px; }
    .doc-meta { text-align: right; }
    .doc-meta .doc-number { font-size: 18px; font-weight: 700; color: #1a1a2e; }
    .doc-meta .doc-date { font-size: 11px; color: #6b7280; margin-top: 2px; }

    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 28px; }
    .party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #b8914c; margin-bottom: 6px; }
    .party-name { font-size: 15px; font-weight: 700; color: #111; }
    .party-info { font-size: 12px; color: #6b7280; margin-top: 2px; }

    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 10px; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    thead th { background: #f5f3ff; color: #9a7739; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 8px 12px; text-align: left; }
    thead th:last-child { text-align: right; }
    tbody td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; }
    tbody td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
    tbody tr:last-child td { border-bottom: none; }

    .totals { margin-left: auto; width: 240px; }
    .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; color: #6b7280; }
    .totals-row.total-final { font-size: 15px; font-weight: 700; color: #1a1a2e; border-top: 2px solid #b8914c; padding-top: 8px; margin-top: 4px; }
    .totals-row.paid { color: #059669; }
    .totals-row.remaining { color: #d97706; font-weight: 600; }

    .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
    .meta-item .meta-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #9ca3af; margin-bottom: 3px; }
    .meta-item .meta-value { font-size: 13px; font-weight: 600; color: #1a1a2e; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-sent, .badge-active { background: #eff6ff; color: #1d4ed8; }
    .badge-paid { background: #d1fae5; color: #065f46; }
    .badge-overdue, .badge-expired { background: #fee2e2; color: #991b1b; }
    .badge-partial { background: #fef3c7; color: #92400e; }
    .badge-cancelled { background: #f3f4f6; color: #9ca3af; }

    .notes { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-top: 24px; }
    .notes .notes-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
    .notes p { font-size: 12px; color: #374151; line-height: 1.5; white-space: pre-line; }

    .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; font-size: 11px; color: #9ca3af; }

    .print-btn { position: fixed; bottom: 24px; right: 24px; background: #b8914c; color: #fff; border: none; border-radius: 10px; padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 20px rgba(184,145,76,.35); display: flex; align-items: center; gap: 8px; }
    .print-btn:hover { background: #9a7739; }

    @media print {
      .print-btn { display: none !important; }
      body { background: #fff; }
      .page { padding: 20px; }
      @page { margin: 12mm 15mm; }
    }
  </style>
</head>
<body>
  <div class="page">
    <?= view_slot('content') ?>
  </div>

  <button class="print-btn" onclick="window.print()">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
    Imprimir / Salvar PDF
  </button>
</body>
</html>
