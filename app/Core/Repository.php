<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

abstract class Repository
{
    protected PDO $pdo;
    protected string $table = '';

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // -------------------------------------------------------------------------
    // Multi-tenant scope enforcement
    // -------------------------------------------------------------------------

    /** Override in child to enforce agency_id scope on all queries */
    protected function agencyId(): ?int
    {
        return \App\Support\Auth::agencyId();
    }

    protected function agencyScope(): string
    {
        $id = $this->agencyId();

        if ($id !== null) {
            return "agency_id = :__agency_id";
        }

        // Platform admin vê todos os tenants
        if (\App\Support\Auth::isPlatformAdmin()) {
            return '1=1';
        }

        throw new \RuntimeException('No agency_id in session and user is not a platform admin.');
    }

    /** @param array<string,mixed> $params */
    protected function bindAgency(array &$params): void
    {
        $id = $this->agencyId();
        if ($id !== null) {
            $params[':__agency_id'] = $id;
        }
    }

    // -------------------------------------------------------------------------
    // Core query helpers (all use prepared statements)
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $params */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @param array<string,mixed> $params */
    protected function first(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    /** @param array<string,mixed> $params */
    protected function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed> $data */
    protected function insert(array $data): int|string
    {
        $cols        = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $cols);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders),
        );

        $this->query($sql, $this->prefixKeys($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    protected function update(array $data, array $where): int
    {
        $setParts   = array_map(fn($c) => "{$c} = :set_{$c}", array_keys($data));
        $whereParts = array_map(fn($c) => "{$c} = :where_{$c}", array_keys($where));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts),
        );

        $params = [];
        foreach ($data  as $k => $v) $params["set_{$k}"]   = $v;
        foreach ($where as $k => $v) $params["where_{$k}"] = $v;

        $stmt = $this->query($sql, $this->prefixKeys($params));
        return $stmt->rowCount();
    }

    /** @param array<string,mixed> $where */
    protected function delete(array $where): int
    {
        $parts = array_map(fn($c) => "{$c} = :{$c}", array_keys($where));
        $sql   = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $parts);
        return $this->query($sql, $this->prefixKeys($where))->rowCount();
    }

    // -------------------------------------------------------------------------
    // Generic finders with agency scope
    // -------------------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $params = [':id' => $id];
        $scope  = '';

        if ($this->agencyId() !== null) {
            $scope = ' AND ' . $this->agencyScope();
            $this->bindAgency($params);
        }

        return $this->first(
            "SELECT * FROM {$this->table} WHERE id = :id{$scope} LIMIT 1",
            $params,
        );
    }

    // -------------------------------------------------------------------------
    // Pagination helper
    // -------------------------------------------------------------------------

    /**
     * Run a COUNT query + a paginated SELECT and return pagination metadata.
     * @param  array<string,mixed> $params
     * @return array{items: array, total: int, page: int, per_page: int, pages: int}
     */
    protected function paginate(string $sql, array $params, int $page, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        // Count total without LIMIT/OFFSET — wrap the query
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS __pq__";
        $stmt     = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Paginated fetch
        $stmt = $this->pdo->prepare("{$sql} LIMIT :__limit OFFSET :__offset");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':__limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':__offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    /** Adds ":" prefix to keys (PDO named params) */
    private function prefixKeys(array $data): array
    {
        $result = [];
        foreach ($data as $k => $v) {
            $result[str_starts_with($k, ':') ? $k : ":{$k}"] = $v;
        }
        return $result;
    }
}
