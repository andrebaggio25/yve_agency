<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <style>
    body { margin: 0; padding: 0; background: #09090f; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #e2e8f0; }
    .wrapper { max-width: 560px; margin: 0 auto; padding: 40px 20px; }
    .logo { text-align: center; margin-bottom: 32px; }
    .logo span { display: inline-block; background: #c6a15b; color: #0d0d14; font-size: 18px; font-weight: 700; padding: 10px 20px; border-radius: 12px; letter-spacing: -0.5px; }
    .card { background: #111118; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 32px; }
    h1 { font-size: 20px; font-weight: 700; color: #f1f5f9; margin: 0 0 8px; }
    p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 16px; }
    .btn { display: inline-block; background: #c6a15b; color: #0d0d14 !important; font-size: 14px; font-weight: 600; padding: 14px 32px; border-radius: 10px; text-decoration: none; margin: 8px 0 20px; }
    .notice { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 14px 18px; font-size: 13px; color: #64748b; }
    .divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 24px 0; }
    .footer { text-align: center; font-size: 12px; color: #475569; margin-top: 24px; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="logo"><span><?= e(env('APP_NAME', 'YVE Agency')) ?></span></div>
  <div class="card">
    <h1>Redefinir sua senha</h1>
    <p>Olá, <strong style="color:#f1f5f9"><?= e($user_name) ?></strong>!</p>
    <p>Recebemos uma solicitação para redefinir a senha da sua conta. Clique no botão abaixo para criar uma nova senha:</p>

    <div style="text-align:center">
      <a href="<?= e($reset_url) ?>" class="btn">Redefinir senha</a>
    </div>

    <hr class="divider">

    <div class="notice">
      <strong style="color:#f1f5f9">⏰ Este link expira em 1 hora.</strong><br>
      Se você não solicitou a redefinição de senha, ignore este e-mail — sua conta está segura.
    </div>

    <hr class="divider">

    <p style="font-size:12px;color:#475569">
      Se o botão acima não funcionar, copie e cole o seguinte endereço no seu navegador:<br>
      <span style="color:#d4b478;word-break:break-all"><?= e($reset_url) ?></span>
    </p>
  </div>
  <div class="footer">
    <?= e(env('APP_NAME', 'YVE Agency')) ?> &middot; <?= date('Y') ?>
  </div>
</div>
</body>
</html>
