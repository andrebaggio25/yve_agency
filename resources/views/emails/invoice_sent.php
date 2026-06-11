<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <style>
    body { margin: 0; padding: 0; background: #09090f; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #e2e8f0; }
    .wrapper { max-width: 560px; margin: 0 auto; padding: 40px 20px; }
    .logo { text-align: center; margin-bottom: 32px; }
    .logo span { display: inline-block; background: #7c3aed; color: #fff; font-size: 18px; font-weight: 700; padding: 10px 20px; border-radius: 12px; letter-spacing: -0.5px; }
    .card { background: #111118; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 32px; }
    h1 { font-size: 20px; font-weight: 700; color: #f1f5f9; margin: 0 0 8px; }
    p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 16px; }
    .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    .table td { padding: 8px 0; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .table td:first-child { color: #64748b; }
    .table td:last-child { text-align: right; color: #f1f5f9; font-weight: 600; }
    .total-row td { color: #f1f5f9 !important; font-size: 15px; font-weight: 700; border-bottom: none; border-top: 2px solid rgba(109,40,217,.4); padding-top: 12px; }
    .total-row td:last-child { color: #a78bfa !important; }
    .btn { display: inline-block; background: #7c3aed; color: #fff !important; font-size: 14px; font-weight: 600; padding: 12px 28px; border-radius: 10px; text-decoration: none; margin-top: 8px; }
    .divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 24px 0; }
    .footer { text-align: center; font-size: 12px; color: #475569; margin-top: 24px; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="logo"><span><?= e(env('APP_NAME', 'YVE Agency')) ?></span></div>
  <div class="card">
    <h1>Nova fatura disponível</h1>
    <p>Olá, <strong style="color:#f1f5f9"><?= e($client_name) ?></strong>!</p>
    <p>Temos uma nova fatura para você:</p>

    <table class="table">
      <tr><td>Nº da Fatura</td><td><?= e($invoice_number) ?></td></tr>
      <tr><td>Título</td><td><?= e($invoice_title) ?></td></tr>
      <?php if (!empty($due_date)): ?>
      <tr><td>Vencimento</td><td><?= e($due_date) ?></td></tr>
      <?php endif; ?>
      <tr class="total-row"><td>Total</td><td><?= e($total) ?></td></tr>
    </table>

    <hr class="divider">
    <?php if (!empty($notes)): ?>
    <p style="font-style: italic;"><?= nl2br(e($notes)) ?></p>
    <hr class="divider">
    <?php endif; ?>
    <p>Acesse o portal para mais detalhes ou entre em contato conosco.</p>
  </div>
  <div class="footer">
    <?= e(env('APP_NAME', 'YVE Agency')) ?> &middot; <?= date('Y') ?>
  </div>
</div>
</body>
</html>
