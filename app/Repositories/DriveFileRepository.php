<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class DriveFileRepository extends Repository
{
    protected string $table = 'drive_files';

    public function create(array $data): int
    {
        return (int) $this->insert([
            'agency_id'      => $data['agency_id'],
            'client_id'      => $data['client_id'],
            'folder_id'      => $data['folder_id'] ?? null,
            'drive_file_id'  => $data['drive_file_id'],
            'name'           => $data['name'],
            'mime_type'      => $data['mime_type'] ?? null,
            'size_bytes'     => $data['size_bytes'] ?? null,
            'thumbnail_link' => $data['thumbnail_link'] ?? null,
            'web_view_link'  => $data['web_view_link'] ?? null,
            'uploaded_via'   => $data['uploaded_via'] ?? 'portal',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** Arquivos de uma pasta (folder_id NULL = raiz do cliente). */
    public function forFolder(int $clientId, ?int $folderId): array
    {
        if ($folderId === null) {
            return $this->all(
                "SELECT * FROM drive_files
                 WHERE client_id = :c AND folder_id IS NULL
                 ORDER BY created_at DESC",
                [':c' => $clientId]
            );
        }

        return $this->all(
            "SELECT * FROM drive_files
             WHERE client_id = :c AND folder_id = :f
             ORDER BY created_at DESC",
            [':c' => $clientId, ':f' => $folderId]
        );
    }

    public function forClient(int $clientId): array
    {
        return $this->all(
            "SELECT * FROM drive_files WHERE client_id = :c ORDER BY created_at DESC",
            [':c' => $clientId]
        );
    }

    /** Busca pelo ID do arquivo no Drive (dedupe do upload direto). */
    public function findByDriveId(string $driveFileId, int $clientId): ?array
    {
        return $this->first(
            "SELECT * FROM drive_files WHERE drive_file_id = :d AND client_id = :c LIMIT 1",
            [':d' => $driveFileId, ':c' => $clientId]
        );
    }

    public function findForClient(int $id, int $clientId): ?array
    {
        return $this->first(
            "SELECT * FROM drive_files WHERE id = :id AND client_id = :c",
            [':id' => $id, ':c' => $clientId]
        );
    }

    public function deleteForClient(int $id, int $clientId): void
    {
        $this->query(
            "DELETE FROM drive_files WHERE id = :id AND client_id = :c",
            [':id' => $id, ':c' => $clientId]
        );
    }

    /** Atualiza nome/metadados a partir do que veio do Drive (reconciliação). */
    public function updateFromDrive(int $id, int $clientId, array $data): void
    {
        $this->query(
            "UPDATE drive_files
             SET name = :n, mime_type = :m, size_bytes = :s,
                 thumbnail_link = :t, web_view_link = :w
             WHERE id = :id AND client_id = :c",
            [
                ':n'  => $data['name'] ?? '',
                ':m'  => $data['mime_type'] ?? null,
                ':s'  => $data['size_bytes'] ?? null,
                ':t'  => $data['thumbnail_link'] ?? null,
                ':w'  => $data['web_view_link'] ?? null,
                ':id' => $id,
                ':c'  => $clientId,
            ]
        );
    }

    /** Remove todos os arquivos de uma pasta (usado na exclusão recursiva de pasta). */
    public function deleteByFolder(int $clientId, int $folderId): void
    {
        $this->query(
            "DELETE FROM drive_files WHERE client_id = :c AND folder_id = :f",
            [':c' => $clientId, ':f' => $folderId]
        );
    }

    public function totalBytesForAgency(int $agencyId): int
    {
        $row = $this->first(
            "SELECT COALESCE(SUM(size_bytes), 0) AS total FROM drive_files WHERE agency_id = :a",
            [':a' => $agencyId]
        );
        return (int) ($row['total'] ?? 0);
    }
}
