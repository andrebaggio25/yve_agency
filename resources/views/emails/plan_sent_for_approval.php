<!DOCTYPE html>
<html lang="<?= $locale ?? 'pt' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { margin: 0; padding: 0; background: #09090f; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #e2e8f0; }
    .wrapper { max-width: 560px; margin: 0 auto; padding: 40px 20px; }
    .logo { text-align: center; margin-bottom: 32px; }
    .logo span { display: inline-block; background: #c6a15b; color: #0d0d14; font-size: 18px; font-weight: 700; padding: 10px 20px; border-radius: 12px; letter-spacing: -0.5px; }
    .card { background: #111118; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 32px; }
    h1 { font-size: 20px; font-weight: 700; color: #f1f5f9; margin: 0 0 8px; }
    p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 16px; }
    .btn { display: inline-block; background: #c6a15b; color: #0d0d14 !important; font-size: 14px; font-weight: 600; padding: 12px 28px; border-radius: 10px; text-decoration: none; margin-top: 8px; }
    .divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 24px 0; }
    .footer { text-align: center; font-size: 12px; color: #475569; margin-top: 24px; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="logo"><span><?= e(env('APP_NAME', 'YVE Agency')) ?></span></div>
  <div class="card">
    <h1>
      <?= $locale === 'en' ? 'New content plan ready for review' : ($locale === 'es' ? 'Nuevo plan de contenido listo para revisar' : 'Novo plano de conteúdo para aprovação') ?>
    </h1>
    <p>
      <?= $locale === 'en'
        ? "Hello, <strong>{$client_name}</strong>! We have prepared a new content plan for your review."
        : ($locale === 'es'
          ? "¡Hola, <strong>{$client_name}</strong>! Hemos preparado un nuevo plan de contenido para tu revisión."
          : "Olá, <strong>{$client_name}</strong>! Preparamos um novo plano de conteúdo para a sua aprovação.") ?>
    </p>
    <p>
      <?= $locale === 'en' ? 'Plan:' : ($locale === 'es' ? 'Plan:' : 'Plano:') ?>
      <strong style="color:#f1f5f9"><?= e($plan_title) ?></strong>
    </p>
    <hr class="divider">
    <p>
      <?= $locale === 'en'
        ? 'Click the button below to view and approve each item.'
        : ($locale === 'es'
          ? 'Haz clic en el botón de abajo para ver y aprobar cada elemento.'
          : 'Clique no botão abaixo para visualizar e aprovar cada item.') ?>
    </p>
    <a href="<?= e($approval_url) ?>" class="btn">
      <?= $locale === 'en' ? 'Review Plan' : ($locale === 'es' ? 'Revisar Plan' : 'Revisar Plano') ?>
    </a>
  </div>
  <div class="footer">
    <?= e(env('APP_NAME', 'YVE Agency')) ?> &middot; <?= date('Y') ?>
  </div>
</div>
</body>
</html>
