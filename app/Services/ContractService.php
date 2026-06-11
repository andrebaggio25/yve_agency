<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContractRepository;
use App\Support\Auth;

class ContractService
{
    public function __construct(private ContractRepository $repo) {}

    public function list(array $filters = []): array
    {
        return $this->repo->listByAgency(Auth::agencyId(), $filters);
    }

    public function find(int $id): ?array
    {
        return $this->repo->findWithClient($id, Auth::agencyId());
    }

    public function findOrFail(int $id): array
    {
        $contract = $this->find($id);
        if (!$contract) {
            http_response_code(404);
            throw new \RuntimeException("Contrato não encontrado.");
        }
        return $contract;
    }

    public function create(array $input): int
    {
        $data = $this->sanitize($input);
        $data['agency_id']  = Auth::agencyId();
        $data['created_by'] = Auth::id();
        return $this->repo->create($data);
    }

    public function update(int $id, array $input): void
    {
        $this->findOrFail($id);
        $this->repo->updateById($id, Auth::agencyId(), $this->sanitize($input));
    }

    public function delete(int $id): void
    {
        if (!$this->repo->deleteById($id, Auth::agencyId())) {
            throw new \RuntimeException("Contrato não encontrado.");
        }
    }

    public function summary(): array
    {
        return $this->repo->summaryByAgency(Auth::agencyId());
    }

    public function activeForClient(int $clientId): array
    {
        return $this->repo->activeForClient($clientId, Auth::agencyId());
    }

    private function sanitize(array $input): array
    {
        return [
            'client_id'    => (int) ($input['client_id'] ?? 0),
            'title'        => trim($input['title'] ?? ''),
            'description'  => trim($input['description'] ?? '') ?: null,
            'value'        => (float) str_replace(',', '.', $input['value'] ?? '0'),
            'currency_code'=> $input['currency_code'] ?? 'BRL',
            'status'       => $input['status'] ?? 'draft',
            'start_date'   => $input['start_date'] ?: null,
            'end_date'     => $input['end_date'] ?: null,
            'signed_at'    => $input['signed_at'] ?: null,
            'recurring'    => !empty($input['recurring']),
            'recurrence'   => $input['recurrence'] ?: null,
            'notes'        => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
