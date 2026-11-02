<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdsActionRepository extends Repository
{
    protected string $table = 'ads_actions';

    /**
     * Nome do alvo só quando ele está mesmo na conta da solicitação (SEC-13).
     * `campaigns`, `ad_sets` e `ads` não têm agency_id: com LEFT JOIN só pelo id,
     * uma linha antiga apontando para outra agência exibia o nome de lá. Fora da
     * conta o nome vem nulo, e a tela mostra o traço de "sem alvo".
     *
     * O id usado aqui é o `a.id` do JOIN com `ad_accounts`, que já confere a
     * agência: numa linha legada apontando para conta alheia ele é nulo, então
     * nem a conta nem o alvo dentro dela têm nome para exibir.
     */
    private const TARGET_NAMES = "
                (SELECT c.name FROM campaigns c
                  WHERE c.id = aa.campaign_id AND c.ad_account_id = a.id) AS campaign_name,
                (SELECT s.name FROM ad_sets s
                   JOIN campaigns sc ON sc.id = s.campaign_id
                  WHERE s.id = aa.ad_set_id AND sc.ad_account_id = a.id) AS ad_set_name,
                (SELECT ad.name FROM ads ad
                   JOIN ad_sets   asx ON asx.id = ad.ad_set_id
                   JOIN campaigns ac  ON ac.id = asx.campaign_id
                  WHERE ad.id = aa.ad_id AND ac.ad_account_id = a.id) AS ad_name";

    /**
     * A conta também carrega a agência da própria solicitação, e o JOIN é à
     * esquerda de propósito: a linha legada continua na lista (dá para rejeitar)
     * só sem o nome da conta alheia — e o contador de pendentes, que não faz
     * JOIN, segue batendo com o que a tela mostra.
     */
    private const ACCOUNT_JOIN = 'LEFT JOIN ad_accounts a ON a.id = aa.ad_account_id AND a.agency_id = aa.agency_id';

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ads_actions
                (agency_id, ad_account_id, campaign_id, ad_set_id, ad_id,
                 action_type, description, justification,
                 current_value, proposed_value,
                 status, ai_generated, requested_by,
                 created_at, updated_at)
            VALUES
                (:agency_id, :account_id, :campaign_id, :ad_set_id, :ad_id,
                 :action_type, :description, :justification,
                 :current_value, :proposed_value,
                 'pending', :ai_generated, :requested_by,
                 NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'     => $data['agency_id'],
            ':account_id'    => $data['ad_account_id'],
            ':campaign_id'   => $data['campaign_id'] ?? null,
            ':ad_set_id'     => $data['ad_set_id'] ?? null,
            ':ad_id'         => $data['ad_id'] ?? null,
            ':action_type'   => $data['action_type'],
            ':description'   => $data['description'],
            ':justification' => $data['justification'] ?? null,
            ':current_value' => $data['current_value'] ?? null,
            ':proposed_value'=> $data['proposed_value'] ?? null,
            ':ai_generated'  => $data['ai_generated'] ? 'true' : 'false',
            ':requested_by'  => $data['requested_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['aa.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['status'])) {
            $where[] = 'aa.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['ad_account_id'])) {
            $where[] = 'aa.ad_account_id = :account_id';
            $params[':account_id'] = (int) $filters['ad_account_id'];
        }

        $whereClause = implode(' AND ', $where);
        return $this->all("
            SELECT
                aa.*,
                a.name  AS account_name,
                " . self::TARGET_NAMES . ",
                ru.name AS requested_by_name,
                au.name AS approved_by_name
            FROM ads_actions aa
            " . self::ACCOUNT_JOIN . "
            LEFT JOIN users ru ON ru.id = aa.requested_by AND ru.agency_id = aa.agency_id
            LEFT JOIN users au ON au.id = aa.approved_by  AND au.agency_id = aa.agency_id
            WHERE {$whereClause}
            ORDER BY aa.created_at DESC
        ", $params);
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT
                aa.*,
                a.name  AS account_name,
                " . self::TARGET_NAMES . ",
                ru.name AS requested_by_name
            FROM ads_actions aa
            " . self::ACCOUNT_JOIN . "
            LEFT JOIN users ru ON ru.id = aa.requested_by AND ru.agency_id = aa.agency_id
            WHERE aa.id = :id AND aa.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function setStatus(int $id, string $status, ?int $userId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $approvedAt  = in_array($status, ['approved', 'rejected'], true) ? $now : null;
        $executedAt  = $status === 'executed' ? $now : null;

        $this->pdo->prepare("
            UPDATE ads_actions SET
                status      = :status,
                approved_by = CASE WHEN :user_id IS NOT NULL THEN :user_id2 ELSE approved_by END,
                approved_at = CASE WHEN :approved_at IS NOT NULL THEN :approved_at2::timestamp ELSE approved_at END,
                executed_at = CASE WHEN :executed_at IS NOT NULL THEN :executed_at2::timestamp ELSE executed_at END,
                updated_at  = NOW()
            WHERE id = :id
        ")->execute([
            ':status'      => $status,
            ':user_id'     => $userId,
            ':user_id2'    => $userId,
            ':approved_at' => $approvedAt,
            ':approved_at2'=> $approvedAt,
            ':executed_at' => $executedAt,
            ':executed_at2'=> $executedAt,
            ':id'          => $id,
        ]);
    }

    public function setError(int $id, string $error): void
    {
        $this->pdo->prepare("
            UPDATE ads_actions SET status = 'failed', error_message = :err, updated_at = NOW()
            WHERE id = :id
        ")->execute([':err' => $error, ':id' => $id]);
    }

    public function countPending(int $agencyId): int
    {
        $row = $this->first(
            "SELECT COUNT(*) AS n FROM ads_actions WHERE agency_id = :aid AND status = 'pending'",
            [':aid' => $agencyId]
        );
        return (int) ($row['n'] ?? 0);
    }
}
