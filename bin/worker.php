#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Job worker — consome a fila de jobs da tabela `jobs`.
 * Rodado via cron ou supervisord:
 *   @reboot php /caminho/bin/worker.php >> storage/logs/worker.log 2>&1
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set('America/Sao_Paulo');

$pdo = Database::connection();

echo "[worker] Iniciado em " . date('Y-m-d H:i:s') . "\n";

while (true) {
    try {
        $job = reserveJob($pdo);

        if (!$job) {
            sleep(3);
            continue;
        }

        echo "[worker] Processando job #{$job['id']} ({$job['queue']})\n";

        processJob($job, $pdo);

    } catch (\Throwable $e) {
        echo "[worker] Erro crítico: " . $e->getMessage() . "\n";
        sleep(5);
    }
}

function reserveJob(\PDO $pdo): ?array
{
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT * FROM jobs
        WHERE status = 'pending'
          AND available_at <= NOW()
          AND attempts < max_attempts
        ORDER BY available_at ASC
        LIMIT 1
        FOR UPDATE SKIP LOCKED
    ");
    $stmt->execute();
    $job = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        return null;
    }

    $update = $pdo->prepare("
        UPDATE jobs SET status = 'reserved', reserved_at = NOW(), attempts = attempts + 1
        WHERE id = :id
    ");
    $update->execute([':id' => $job['id']]);

    $pdo->commit();
    return $job;
}

function processJob(array $job, \PDO $pdo): void
{
    $payload = json_decode($job['payload'], true);
    $class   = $payload['job'] ?? null;

    if (!$class || !class_exists($class)) {
        markFailed($pdo, $job['id'], "Job class [{$class}] not found.");
        return;
    }

    try {
        $handler = new $class();
        $handler->handle($payload['data'] ?? []);
        markDone($pdo, $job['id']);
        echo "[worker] Job #{$job['id']} concluído.\n";
    } catch (\Throwable $e) {
        $backoff = (int) (pow(2, $job['attempts']) * 10); // backoff exponencial em segundos
        markRetry($pdo, $job['id'], $e->getMessage(), $backoff);
        echo "[worker] Job #{$job['id']} falhou: {$e->getMessage()} (retry em {$backoff}s)\n";
    }
}

function markDone(\PDO $pdo, int $id): void
{
    $pdo->prepare("UPDATE jobs SET status = 'done', updated_at = NOW() WHERE id = :id")
        ->execute([':id' => $id]);
}

function markFailed(\PDO $pdo, int $id, string $error): void
{
    $pdo->prepare("UPDATE jobs SET status = 'failed', last_error = :err, updated_at = NOW() WHERE id = :id")
        ->execute([':id' => $id, ':err' => $error]);
}

function markRetry(\PDO $pdo, int $id, string $error, int $backoffSeconds): void
{
    $pdo->prepare("
        UPDATE jobs
        SET status = 'pending',
            available_at = NOW() + INTERVAL ':sec seconds',
            last_error = :err,
            updated_at = NOW()
        WHERE id = :id
    ")->execute([':sec' => $backoffSeconds, ':id' => $id, ':err' => $error]);
}
