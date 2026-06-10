<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

class ActivityLogger
{
    public static function log(
        string $action,
        string $module,
        ?int   $userId    = null,
        ?int   $clientId  = null,
        array  $metadata  = [],
    ): void {
        try {
            $pdo = Database::connection();

            $userId   ??= Auth::id();
            $agencyId   = Auth::agencyId() ?? 0;

            $stmt = $pdo->prepare("
                INSERT INTO activity_logs
                    (agency_id, user_id, client_id, action, module, ip_address, user_agent, metadata_json, created_at)
                VALUES
                    (:agency_id, :user_id, :client_id, :action, :module, :ip, :ua, :meta, NOW())
            ");

            $stmt->execute([
                ':agency_id' => $agencyId,
                ':user_id'   => $userId,
                ':client_id' => $clientId,
                ':action'    => $action,
                ':module'    => $module,
                ':ip'        => $_SERVER['REMOTE_ADDR']     ?? '0.0.0.0',
                ':ua'        => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':meta'      => $metadata ? json_encode($metadata) : null,
            ]);
        } catch (\Throwable $e) {
            // Logging must never crash the app; write to error log as fallback
            error_log("ActivityLogger failed: " . $e->getMessage());
        }
    }
}
