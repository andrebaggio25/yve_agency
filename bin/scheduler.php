#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scheduler — chamado pelo cron a cada minuto.
 * Lê automation_rules com next_run_at <= NOW() e enfileira jobs.
 *
 * Cron:
 *   * * * * * php /caminho/bin/scheduler.php >> storage/logs/scheduler.log 2>&1
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set('America/Sao_Paulo');

$pdo = Database::connection();

// Busca regras que precisam rodar
$stmt = $pdo->prepare("
    SELECT * FROM automation_rules
    WHERE status = 'active'
      AND (next_run_at IS NULL OR next_run_at <= NOW())
");
$stmt->execute();
$rules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

foreach ($rules as $rule) {
    enqueueRule($pdo, $rule);
    updateNextRun($pdo, $rule);
    echo "[scheduler] Rule #{$rule['id']} ({$rule['name']}) enqueued.\n";
}

if (empty($rules)) {
    echo "[scheduler] Nenhuma regra para executar em " . date('Y-m-d H:i:s') . "\n";
}

function enqueueRule(\PDO $pdo, array $rule): void
{
    $payload = json_encode([
        'job'  => 'App\\Jobs\\RunAutomationRuleJob',
        'data' => ['rule_id' => $rule['id']],
    ]);

    $pdo->prepare("
        INSERT INTO jobs (agency_id, queue, payload, available_at, status, created_at, updated_at)
        VALUES (:agency_id, 'automations', :payload, NOW(), 'pending', NOW(), NOW())
    ")->execute([':agency_id' => $rule['agency_id'], ':payload' => $payload]);
}

function updateNextRun(\PDO $pdo, array $rule): void
{
    $next = calculateNextRun($rule['frequency'], $rule['scheduled_day'], $rule['scheduled_time']);

    $pdo->prepare("
        UPDATE automation_rules SET last_run_at = NOW(), next_run_at = :next WHERE id = :id
    ")->execute([':next' => $next, ':id' => $rule['id']]);
}

function calculateNextRun(string $frequency, ?string $day, ?string $time): string
{
    $timeStr = $time ?? '08:00:00';

    return match ($frequency) {
        'daily'   => date('Y-m-d H:i:s', strtotime('tomorrow ' . $timeStr)),
        'weekly'  => date('Y-m-d H:i:s', strtotime('next ' . ($day ?? 'Monday') . ' ' . $timeStr)),
        'monthly' => date('Y-m-d H:i:s', strtotime('first day of next month ' . $timeStr)),
        default   => date('Y-m-d H:i:s', strtotime('+1 hour')),
    };
}
