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
    .task-card { background: rgba(109,40,217,0.1); border: 1px solid rgba(109,40,217,0.3); border-radius: 10px; padding: 16px 20px; margin: 20px 0; }
    .task-card p { color: #c4b5fd; font-size: 15px; font-weight: 600; margin: 0; }
    .btn { display: inline-block; background: #7c3aed; color: #fff !important; font-size: 14px; font-weight: 600; padding: 12px 28px; border-radius: 10px; text-decoration: none; margin-top: 4px; }
    .divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 24px 0; }
    .footer { text-align: center; font-size: 12px; color: #475569; margin-top: 24px; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="logo"><span><?= e(env('APP_NAME', 'YVE Agency')) ?></span></div>
  <div class="card">
    <h1>📋 Nova tarefa atribuída</h1>
    <p>Olá, <strong style="color:#f1f5f9"><?= e($user_name) ?></strong>!</p>
    <p><strong style="color:#f1f5f9"><?= e($assigned_by) ?></strong> atribuiu uma nova tarefa a você:</p>

    <div class="task-card">
      <p><?= e($task_title) ?></p>
    </div>

    <div style="text-align:center;margin-top:8px">
      <a href="<?= e($task_url) ?>" class="btn">Ver tarefa</a>
    </div>

    <hr class="divider">
    <p style="font-size:12px;color:#475569">Você recebeu este e-mail porque foi atribuído a esta tarefa em <?= e(env('APP_NAME', 'YVE Agency')) ?>.</p>
  </div>
  <div class="footer">
    <?= e(env('APP_NAME', 'YVE Agency')) ?> &middot; <?= date('Y') ?>
  </div>
</div>
</body>
</html>
