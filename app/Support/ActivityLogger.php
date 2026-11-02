<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

class ActivityLogger
{
    /**
     * Grava uma linha na trilha de auditoria.
     *
     * `$agencyId` explícito é para os dois casos em que a sessão não responde a
     * pergunta "de qual agência é isso?" (SEC-15):
     *   - **portal do cliente**: não há usuário logado nem `agency_id` em
     *     sessão (a autenticação é o capability-token da URL), então sem este
     *     parâmetro a escrita do cliente no Drive cairia na agência 0 e
     *     desapareceria da trilha da agência dona dele;
     *   - **admin da plataforma**: não tem `agency_id` próprio, mas a ação é
     *     *sobre* um tenant — e é a trilha desse tenant que precisa mostrá-la.
     *
     * @param array<string,mixed> $metadata nunca token, senha ou segredo
     */
    public static function log(
        string $action,
        string $module,
        ?int   $userId    = null,
        ?int   $clientId  = null,
        array  $metadata  = [],
        ?int   $agencyId  = null,
    ): void {
        try {
            $pdo = Database::connection();

            $userId   ??= Auth::id();
            $agencyId ??= Auth::agencyId() ?? 0;

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
                ':ip'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ':ua'        => self::utf8Safe((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                // JSON_INVALID_UTF8_SUBSTITUTE: sem ele o `json_encode` devolve
                // `false` no primeiro byte inválido e a linha perde o metadata.
                ':meta'      => $metadata ? json_encode($metadata, JSON_INVALID_UTF8_SUBSTITUTE) : null,
            ]);
        } catch (\Throwable $e) {
            // Logging must never crash the app; write to error log as fallback
            error_log("ActivityLogger failed: " . $e->getMessage());
        }
    }

    /**
     * Texto do cliente não é UTF-8 garantido, e o Postgres recusa o parâmetro
     * inválido (SQLSTATE 22021). Com o `catch` acima engolindo a exceção — log
     * não pode derrubar a ação —, `User-Agent: Mozilla \xC3\x28` fazia a ação
     * acontecer **sem nenhuma linha** na trilha: bastava escolher o próprio
     * header para agir sem rastro. Byte inválido entra como `?`.
     */
    private static function utf8Safe(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
