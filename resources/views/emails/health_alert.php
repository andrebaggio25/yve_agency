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
    .reasons { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 12px; padding: 16px 20px; margin: 20px 0; }
    .reasons pre { margin: 0; font-family: inherit; font-size: 14px; line-height: 1.7; color: #fca5a5; white-space: pre-wrap; }
    .foot { font-size: 12px; color: #64748b; text-align: center; margin-top: 24px; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="logo"><span><?= e($app ?? 'YVE Agency') ?></span></div>

    <div class="card">
      <h1>Alerta operacional</h1>
      <p>
        O monitoramento detectou algo que precisa da sua atenção. Isto não é um erro
        de cliente — é a plataforma avisando que um trabalho ficou por fazer.
      </p>

      <div class="reasons">
        <pre><?= e($reasons ?? '') ?></pre>
      </div>

      <p>
        Verifique o painel e o endpoint <strong>/health</strong> para o retrato completo.
        Este alerta é enviado no máximo uma vez por hora.
      </p>
    </div>

    <p class="foot">Detectado em <?= e($time ?? '') ?></p>
  </div>
</body>
</html>
