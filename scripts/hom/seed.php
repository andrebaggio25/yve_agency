<?php

declare(strict_types=1);

/**
 * Dados fictícios da homologação local: agência, equipe por perfil, clientes,
 * planificações, tarefas, financeiro, tráfego e orgânico, nas datas de hoje.
 *
 * Fica fora de database/seeders de propósito: `composer seed` roda aquela pasta
 * inteira, e isto jamais pode chegar à produção. Aborta se o banco não for o da
 * homologação. Nomes, telefones, e-mails e tokens são inventados; os telefones
 * usam a faixa 5511900000xxx, que não é entregue a ninguém.
 */

use App\Core\Secret;
use Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$host = (string) ($_ENV['DB_HOST'] ?? '');
$port = (string) ($_ENV['DB_PORT'] ?? '');
$name = (string) ($_ENV['DB_NAME'] ?? '');
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || $port !== '55433' || $name !== 'yve_hom') {
    fwrite(STDERR, "ABORTADO: este script só roda no banco yve_hom local.\n");
    exit(1);
}

date_default_timezone_set('America/Sao_Paulo');

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", (string) $_ENV['DB_USER'], (string) $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

const SENHA = 'hom-teste-123';

if ($pdo->query("SELECT 1 FROM users WHERE email = 'gestao@hom.local'")->fetchColumn()) {
    echo "Dados fictícios já existem. Para recomeçar: scripts/hom/up.sh --reset\n";
    exit(0);
}

$ins = function (string $table, array $row) use ($pdo): int {
    $cols = array_keys($row);
    $sql  = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
        $table,
        implode(', ', $cols),
        implode(', ', array_map(fn ($c) => ':' . $c, $cols)),
    );
    $stmt = $pdo->prepare($sql);
    foreach ($row as $col => $value) {
        $stmt->bindValue(':' . $col, is_bool($value) ? ($value ? 'true' : 'false') : $value);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
};

$roleId = function (string $slug) use ($pdo): int {
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :s AND agency_id IS NULL');
    $stmt->execute([':s' => $slug]);
    return (int) $stmt->fetchColumn();
};

$user = function (int $agency, string $nome, string $email, string $role) use ($ins, $roleId, $pdo): int {
    $id = $ins('users', [
        'agency_id' => $agency, 'name' => $nome, 'email' => $email,
        'password_hash' => password_hash(SENHA, PASSWORD_ARGON2ID), 'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:u, :r, NOW())')
        ->execute([':u' => $id, ':r' => $roleId($role)]);
    return $id;
};

$hoje   = new DateTimeImmutable('today');
$monday = new DateTimeImmutable('monday this week');
$dia    = fn (int $offset): string => $hoje->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
$now    = date('Y-m-d H:i:s');

$pdo->beginTransaction();

// ── Agência principal e equipe, uma pessoa por perfil ────────────────────────
$agency = $ins('agencies', [
    'name' => 'Agência Horizonte', 'slug' => 'agencia-horizonte', 'status' => 'active', 'country' => 'BR',
    'currency_code' => 'BRL', 'timezone' => 'America/Sao_Paulo', 'language' => 'pt',
    'email' => 'contato@hom.local', 'created_at' => $now,
]);
$gestao     = $user($agency, 'Marina Gestão', 'gestao@hom.local', 'agency_admin');
$social     = $user($agency, 'Bruno Social', 'social@hom.local', 'social_media');
$designer   = $user($agency, 'Carla Designer', 'designer@hom.local', 'designer');
$trafego    = $user($agency, 'Diego Tráfego', 'trafego@hom.local', 'traffic_manager');
$financeiro = $user($agency, 'Elisa Financeiro', 'financeiro@hom.local', 'financial');
$aprovador  = $user($agency, 'Fábio Cliente', 'cliente@hom.local', 'client_approver');

// Outra agência: entre com este login para conferir que nada da Horizonte aparece.
$rival      = $ins('agencies', [
    'name' => 'Agência Concorrente', 'slug' => 'agencia-concorrente', 'status' => 'active', 'country' => 'BR',
    'currency_code' => 'BRL', 'timezone' => 'America/Sao_Paulo', 'language' => 'pt', 'created_at' => $now,
]);
$rivalAdmin = $user($rival, 'Gabriel Concorrente', 'outra@hom.local', 'agency_admin');
$ins('clients', [
    'agency_id' => $rival, 'name' => 'Cliente Secreto da Concorrente', 'status' => 'active', 'language' => 'pt',
    'currency_code' => 'BRL', 'portal_token' => bin2hex(random_bytes(16)), 'portal_enabled' => true, 'created_at' => $now,
]);

// ── Clientes ─────────────────────────────────────────────────────────────────
$cliente = function (array $row) use ($ins, $agency, $gestao, $now): array {
    $token = bin2hex(random_bytes(16));
    $id    = $ins('clients', $row + [
        'agency_id' => $agency, 'status' => 'active', 'country' => 'BR', 'language' => 'pt', 'currency_code' => 'BRL',
        'manager_user_id' => $gestao, 'notify_whatsapp' => true, 'notify_email' => true,
        'portal_token' => $token, 'portal_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    return ['id' => $id, 'token' => $token, 'name' => $row['name']];
};
$aurora = $cliente(['name' => 'Studio Aurora', 'niche' => 'Estética', 'segment' => 'Beleza', 'city' => 'São Paulo', 'state' => 'SP', 'whatsapp' => '5511900000101']);
$bella  = $cliente(['name' => 'Clínica Bella Vida', 'niche' => 'Dermatologia', 'segment' => 'Saúde', 'city' => 'Campinas', 'state' => 'SP', 'whatsapp' => '5511900000102']);
$solar  = $cliente(['name' => 'Café Solar', 'niche' => 'Cafeteria', 'segment' => 'Alimentação', 'country' => 'ES', 'language' => 'es', 'currency_code' => 'EUR', 'city' => 'Madrid', 'whatsapp' => '34600000103']);
$forte  = $cliente(['name' => 'Academia Forte', 'niche' => 'Fitness', 'segment' => 'Saúde', 'city' => 'Santos', 'state' => 'SP', 'whatsapp' => '5511900000104', 'portal_enabled' => false]);
$lua    = $cliente(['name' => 'Moda Lua (arquivada)', 'niche' => 'Moda', 'segment' => 'Varejo', 'status' => 'cancelled', 'portal_enabled' => false, 'whatsapp' => '5511900000105']);

// Só o usuário do cliente depende de vínculo; a equipe alcança por clients.view_all.
$pdo->prepare("INSERT INTO client_user_access (user_id, client_id, access_level, created_at) VALUES (:u, :c, 'approver', NOW())")
    ->execute([':u' => $aprovador, ':c' => $aurora['id']]);
$ins('client_contacts', ['client_id' => $aurora['id'], 'name' => 'Helena Aurora', 'role' => 'Proprietária', 'email' => 'helena@aurora.hom.local', 'whatsapp' => '5511900000201', 'language' => 'pt', 'channel' => 'whatsapp', 'type' => 'approver', 'is_primary' => true, 'created_at' => $now]);
$ins('client_contacts', ['client_id' => $aurora['id'], 'name' => 'Igor Contas', 'role' => 'Financeiro', 'email' => 'igor@aurora.hom.local', 'whatsapp' => '5511900000202', 'language' => 'pt', 'channel' => 'email', 'type' => 'financial', 'is_primary' => false, 'created_at' => $now]);

// ── Planificações: uma em cada situação do fluxo ─────────────────────────────
$posts = [
    ['Reels / Vídeo', 'Reels: bastidores do atendimento', 'instagram', 'Bastidores', '08:30'],
    ['Carrossel', 'Carrossel: 5 cuidados no verão', 'instagram', 'Educativo', '12:00'],
    ['Feed Estático', 'Depoimento de cliente', 'facebook', 'Prova social', '18:00'],
    ['Story', 'Enquete da semana', 'instagram', 'Interação', '10:00'],
    ['Reels / Vídeo', 'Antes e depois', 'tiktok', 'Resultado', '19:30'],
];

$plano = function (array $c, int $semana, string $status, string $itemStatus) use ($ins, $pdo, $agency, $social, $designer, $monday, $posts, $now): int {
    $inicio = $monday->modify(($semana >= 0 ? '+' : '') . ($semana * 7) . ' days');
    $id     = $ins('content_plans', [
        'agency_id' => $agency, 'client_id' => $c['id'], 'status' => $status, 'created_by' => $social,
        'title' => $c['name'] . ' — semana de ' . $inicio->format('d/m'),
        'week_start' => $inicio->format('Y-m-d'), 'week_end' => $inicio->modify('+6 days')->format('Y-m-d'),
        'sent_at' => $status === 'draft' ? null : $now, 'approved_at' => $status === 'approved' ? $now : null,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    foreach ($posts as $i => [$tipo, $titulo, $rede, $tema, $hora]) {
        $ins('content_plan_items', [
            'content_plan_id' => $id, 'client_id' => $c['id'], 'content_type' => $tipo, 'title' => $titulo,
            'platform' => $rede, 'theme' => $tema, 'publish_date' => $inicio->modify("+{$i} days")->format('Y-m-d'), 'publish_time' => $hora,
            'caption' => "Legenda fictícia para \"{$titulo}\". Texto de exemplo para conferir a tela.",
            'cta' => 'Chame a gente no direct', 'script' => $tipo === 'Reels / Vídeo' ? "Cena 1: abertura.\nCena 2: desenvolvimento.\nCena 3: chamada final." : null,
            'status' => $itemStatus, 'assigned_to' => $i % 2 === 0 ? $social : $designer, 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    return $id;
};

$plano($aurora, -1, 'approved', 'approved');
$auroraSent = $plano($aurora, 0, 'sent', 'draft');
$plano($aurora, 1, 'draft', 'draft');
$bellaRev = $plano($bella, 0, 'revision', 'draft');
$plano($solar, 0, 'approved', 'approved');
$plano($solar, 1, 'sent', 'draft');
// Academia Forte fica sem plano nesta semana para aparecer no radar de pauta.

$itemBella = (int) $pdo->query("SELECT id FROM content_plan_items WHERE content_plan_id = {$bellaRev} ORDER BY sort_order LIMIT 1")->fetchColumn();
$pdo->exec("UPDATE content_plan_items SET status = 'revision' WHERE id = {$itemBella}");
$ins('content_feedbacks', [
    'content_plan_item_id' => $itemBella, 'content_plan_id' => $bellaRev, 'client_id' => $bella['id'], 'feedback_type' => 'changes_requested',
    'comment' => 'Pode trocar a música e cortar os 3 primeiros segundos?', 'timecode_seconds' => 3, 'source' => 'client', 'created_at' => $now,
]);

// ── Tarefas: todas as colunas, atrasadas e no prazo ──────────────────────────
$tarefas = [
    [$aurora, $designer, 'Editar Reels de bastidores', 'in_progress', 'high', $dia(-2)],
    [$aurora, $designer, 'Criar capa do carrossel de verão', 'todo', 'medium', $dia(1)],
    [$aurora, $social, 'Revisar legendas da semana', 'review', 'medium', $dia(0)],
    [$bella, $designer, 'Refazer corte do Reels (pedido da cliente)', 'todo', 'urgent', $dia(-1)],
    [$bella, $social, 'Responder feedback da clínica', 'in_progress', 'high', $dia(0)],
    [$solar, $social, 'Traduzir legendas para espanhol', 'todo', 'low', $dia(3)],
    [$solar, $designer, 'Fotos do cardápio novo', 'done', 'medium', $dia(-5)],
    [$forte, $social, 'Montar pauta da semana', 'todo', 'high', $dia(1)],
    [$forte, $trafego, 'Revisar campanha de matrículas', 'in_progress', 'medium', $dia(2)],
    [null, $gestao, 'Reunião de planejamento mensal', 'todo', 'low', $dia(7)],
];
$primeiraTarefa = 0;
foreach ($tarefas as [$c, $resp, $titulo, $status, $prio, $prazo]) {
    $id = $ins('tasks', [
        'agency_id' => $agency, 'client_id' => $c['id'] ?? null, 'assigned_to' => $resp, 'created_by' => $gestao,
        'title' => $titulo, 'description' => 'Descrição de exemplo da tarefa.', 'status' => $status, 'priority' => $prio, 'due_date' => $prazo,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $primeiraTarefa = $primeiraTarefa ?: $id;
}
$ins('internal_comments', ['agency_id' => $agency, 'entity_type' => 'task', 'entity_id' => $primeiraTarefa, 'user_id' => $gestao, 'message' => 'Carla, a cliente pediu para priorizar este.', 'created_at' => $now]);
$ins('internal_comments', ['agency_id' => $agency, 'entity_type' => 'task', 'entity_id' => $primeiraTarefa, 'user_id' => $designer, 'message' => 'Combinado, entrego hoje à tarde.', 'created_at' => $now]);

// ── Notificações não lidas para testar o sino ────────────────────────────────
foreach ([
    ['Tarefa atrasada', 'Editar Reels de bastidores está atrasada.', '/tarefas/' . $primeiraTarefa, 'task.sla_overdue'],
    ['Pedido de ajuste', 'Clínica Bella Vida pediu ajuste em um post.', '/conteudo/' . $bellaRev, 'content.feedback'],
    ['Aprovação parada', 'Studio Aurora ainda não aprovou a semana.', '/conteudo/' . $auroraSent, 'content.approval'],
] as [$titulo, $corpo, $url, $tipo]) {
    $ins('notifications', ['agency_id' => $agency, 'user_id' => $gestao, 'type' => $tipo, 'title' => $titulo, 'body' => $corpo, 'action_url' => $url, 'created_at' => $now]);
}

// ── Financeiro: fatura em cada situação ──────────────────────────────────────
$contrato = fn (array $c, string $titulo, float $valor, string $moeda = 'BRL') => $ins('contracts', [
    'agency_id' => $agency, 'client_id' => $c['id'], 'title' => $titulo, 'value' => $valor, 'currency_code' => $moeda, 'status' => 'active',
    'start_date' => $dia(-180), 'end_date' => $dia(20), 'recurring' => true, 'recurrence' => 'monthly', 'created_by' => $financeiro, 'created_at' => $now, 'updated_at' => $now,
]);
$ctAurora = $contrato($aurora, 'Gestão de redes — Studio Aurora', 3500.00);
$ctBella  = $contrato($bella, 'Gestão + tráfego — Bella Vida', 6200.00);
$ctSolar  = $contrato($solar, 'Redes sociales — Café Solar', 900.00, 'EUR');

$numero = 1000;
$fatura = function (array $c, ?int $ct, string $titulo, float $total, string $status, string $venc, float $pago = 0.0, string $moeda = 'BRL') use ($ins, $agency, $financeiro, $now, &$numero, $dia): int {
    $numero++;
    $id = $ins('invoices', [
        'agency_id' => $agency, 'client_id' => $c['id'], 'contract_id' => $ct, 'invoice_number' => 'FAT-' . $numero, 'title' => $titulo,
        'status' => $status, 'subtotal' => $total, 'discount' => 0, 'tax' => 0, 'total' => $total, 'amount_paid' => $pago, 'currency_code' => $moeda,
        'due_date' => $venc, 'paid_at' => $status === 'paid' ? $now : null, 'created_by' => $financeiro, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $ins('invoice_items', ['invoice_id' => $id, 'description' => $titulo, 'quantity' => 1, 'unit_price' => $total, 'total_price' => $total, 'sort_order' => 0, 'created_at' => $now]);
    if ($pago > 0) {
        $ins('payments', ['agency_id' => $agency, 'invoice_id' => $id, 'amount' => $pago, 'payment_method' => 'pix', 'payment_date' => $dia(-3), 'created_by' => $financeiro, 'created_at' => $now, 'updated_at' => $now]);
    }
    return $id;
};
$fatura($aurora, $ctAurora, 'Mensalidade do mês passado', 3500.00, 'paid', $dia(-25), 3500.00);
$fatura($aurora, $ctAurora, 'Mensalidade deste mês', 3500.00, 'sent', $dia(3));
$fatura($bella, $ctBella, 'Mensalidade vencida', 6200.00, 'overdue', $dia(-8));
$fatura($bella, $ctBella, 'Campanha extra (parcial)', 2000.00, 'partial', $dia(5), 800.00);
$fatura($solar, $ctSolar, 'Mensualidad', 900.00, 'sent', $dia(10), 0.0, 'EUR');
$fatura($forte, null, 'Rascunho de proposta', 1500.00, 'draft', $dia(15));

// ── Automações: algumas ligadas para ver a matriz preenchida ─────────────────
foreach (['billing.invoice_due_reminder', 'content.approval_reminder', 'task.sla_overdue'] as $chave) {
    $pdo->prepare("INSERT INTO automation_rules (agency_id, automation_key, status, channels) VALUES (:a, :k, 'active', '[\"whatsapp\",\"email\"]')")
        ->execute([':a' => $agency, ':k' => $chave]);
    foreach ([$aurora, $bella] as $c) {
        $pdo->prepare('INSERT INTO client_automation_settings (agency_id, client_id, automation_key, enabled) VALUES (:a, :c, :k, true)')
            ->execute([':a' => $agency, ':c' => $c['id'], ':k' => $chave]);
    }
}

// ── Tráfego: conta com token inventado, métricas dos últimos 30 dias ─────────
$conta = $ins('ad_accounts', [
    'agency_id' => $agency, 'client_id' => $bella['id'], 'platform' => 'meta', 'platform_account_id' => 'act_hom000000001', 'name' => 'Bella Vida — Anúncios',
    'currency' => 'BRL', 'access_token' => Secret::encrypt('token-ficticio-homologacao'), 'token_type' => 'long_lived', 'token_expires_at' => $hoje->modify('+45 days')->format('Y-m-d H:i:s'),
    'status' => 'active', 'last_synced_at' => $now, 'sync_days_back' => 30, 'created_by' => $trafego, 'created_at' => $now, 'updated_at' => $now,
]);
$campanhas = [
    ['Harmonização — Leads', 'ACTIVE', 'LEADS', 80.00, 1.0],
    ['Remarketing — Agenda', 'ACTIVE', 'CONVERSIONS', 40.00, 2.4],
    ['Marca — Alcance', 'PAUSED', 'REACH', 30.00, 0.5],
];
$primeiraCampanha = 0;
foreach ($campanhas as $i => [$nome, $status, $objetivo, $verba, $fator]) {
    $camp = $ins('campaigns', [
        'ad_account_id' => $conta, 'platform' => 'meta', 'platform_id' => 'camp_hom_' . $i, 'name' => $nome, 'status' => $status, 'objective' => $objetivo,
        'daily_budget' => $verba, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $primeiraCampanha = $primeiraCampanha ?: $camp;
    for ($d = 30; $d >= 1; $d--) {
        // Variação previsível (sem random) para os números baterem entre recargas.
        $spend  = round($verba * (0.8 + 0.4 * (($d * 7 + $i * 3) % 10) / 10), 2);
        $impr   = (int) ($spend * 95);
        $clicks = (int) ($impr * 0.021);
        $conv   = (int) max(1, $clicks * 0.08 * $fator);
        $valor  = round($conv * 110, 2);
        $ins('ad_daily_metrics', [
            'ad_account_id' => $conta, 'entity_type' => 'campaign', 'entity_id' => $camp, 'date' => $dia(-$d), 'platform' => 'meta',
            'impressions' => $impr, 'reach' => (int) ($impr * 0.72), 'frequency' => 1.38, 'clicks' => $clicks, 'link_clicks' => (int) ($clicks * 0.8), 'spend' => $spend,
            'cpc' => round($spend / max(1, $clicks), 2), 'cpm' => round($spend / max(1, $impr) * 1000, 2), 'ctr' => 2.1, 'conversions' => $conv, 'conversion_value' => $valor,
            'roas' => round($valor / max(0.01, $spend), 2), 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
$ins('ads_actions', [
    'agency_id' => $agency, 'ad_account_id' => $conta, 'campaign_id' => $primeiraCampanha, 'action_type' => 'pause_campaign',
    'description' => 'Pausar campanha com custo por lead acima da média', 'justification' => 'Exemplo fictício para ver o fluxo de aprovação.', 'status' => 'pending',
    'ai_generated' => false, 'requested_by' => $trafego, 'created_at' => $now, 'updated_at' => $now,
]);

// ── Orgânico: perfil com histórico de seguidores e posts ─────────────────────
$organico = $ins('organic_accounts', [
    'agency_id' => $agency, 'client_id' => $aurora['id'], 'platform' => 'instagram', 'platform_page_id' => 'page_hom_0001', 'instagram_user_id' => 'ig_hom_0001',
    'name' => 'Studio Aurora', 'username' => 'studioaurora.hom', 'access_token' => Secret::encrypt('token-ficticio-homologacao'), 'token_expires_at' => $hoje->modify('+45 days')->format('Y-m-d H:i:s'),
    'followers_count' => 12840, 'following_count' => 312, 'media_count' => 486, 'status' => 'active', 'last_synced_at' => $now, 'sync_days_back' => 30, 'created_by' => $social, 'created_at' => $now, 'updated_at' => $now,
]);
for ($d = 30; $d >= 1; $d--) {
    $ins('organic_daily', [
        'organic_account_id' => $organico, 'date' => $dia(-$d), 'followers_count' => 12840 - $d * 9, 'followers_gained' => 12 + $d % 5, 'followers_lost' => 3 + $d % 2,
        'impressions' => 3100 + ($d * 37) % 900, 'reach' => 2200 + ($d * 29) % 700, 'profile_views' => 140 + $d % 40, 'website_clicks' => 18 + $d % 9, 'posts_count' => $d % 3 === 0 ? 1 : 0, 'created_at' => $now,
    ]);
}
foreach ([['REELS', 'Bastidores do atendimento', 3, 18400, 512], ['CAROUSEL_ALBUM', 'Cuidados no verão', 6, 7200, 268], ['IMAGE', 'Depoimento de cliente', 9, 3900, 121]] as $i => [$tipo, $legenda, $dias, $alcance, $curtidas]) {
    $ins('organic_posts', [
        'organic_account_id' => $organico, 'platform' => 'instagram', 'platform_post_id' => 'post_hom_' . $i, 'media_type' => $tipo, 'caption' => $legenda, 'posted_at' => $dia(-$dias) . ' 18:00:00',
        'impressions' => (int) ($alcance * 1.3), 'reach' => $alcance, 'likes' => $curtidas, 'comments' => (int) ($curtidas / 12), 'shares' => (int) ($curtidas / 20), 'saves' => (int) ($curtidas / 9),
        'engagement_rate' => round($curtidas / 12840 * 100, 2), 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);
}

$pdo->commit();

$base = 'http://localhost:8010';
echo "\nDados fictícios prontos. Senha de todos os logins abaixo: " . SENHA . "\n\n";
foreach ([
    ['Admin da agência', 'gestao@hom.local'], ['Social Media', 'social@hom.local'], ['Designer', 'designer@hom.local'],
    ['Gestor de Tráfego', 'trafego@hom.local'], ['Financeiro', 'financeiro@hom.local'], ['Cliente (só Studio Aurora)', 'cliente@hom.local'],
    ['Outra agência', 'outra@hom.local'],
] as [$perfil, $email]) {
    echo str_pad($perfil, 30) . $email . "\n";
}
echo "\nPortal: Studio Aurora  {$base}/portal/{$aurora['token']}\n";
echo "Portal: Clínica Bella  {$base}/portal/{$bella['token']}\n";
echo "Portal: Café Solar (es) {$base}/portal/{$solar['token']}\n";
