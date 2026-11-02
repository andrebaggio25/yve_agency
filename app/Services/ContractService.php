<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\ContractRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;
use App\Support\Money;

class ContractService
{
    /** Situações do contrato (o mesmo catálogo do `<select>` do formulário). */
    private const STATUSES = ['draft', 'active', 'expired', 'cancelled'];

    /** Periodicidades aceitas quando o contrato é recorrente. */
    private const RECURRENCES = ['monthly', 'quarterly', 'semiannual', 'annual'];

    public function __construct(private ContractRepository $repo) {}

    public function list(array $filters = []): array
    {
        return $this->repo->listByAgency(Auth::agencyId(), $this->scopeToReach($filters));
    }

    /**
     * Mesmo recorte da fatura (SEC-11): a listagem só mostra contrato de
     * cliente que o usuário alcança — senão o seletor esconde e a URL entrega.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function scopeToReach(array $filters): array
    {
        $clientIds = Auth::reachableClientIds();
        if ($clientIds !== null) {
            $filters['client_ids'] = $clientIds;
        }

        return $filters;
    }

    public function find(int $id): ?array
    {
        return $this->repo->findWithClient($id, Auth::agencyId());
    }

    public function findOrFail(int $id): array
    {
        $contract = $this->find($id);
        if (!$contract) {
            throw HttpException::notFound(Auth::isAjax());
        }

        // O contrato é de um cliente: escopo de agência não basta (SEC-11).
        Auth::requireClientAccess((int) $contract['client_id']);

        return $contract;
    }

    public function create(array $input): int
    {
        $data = $this->sanitize($input);
        $this->requireReachableClient((int) $data['client_id']);
        $data['agency_id']  = Auth::agencyId();
        $data['created_by'] = Auth::id();
        $id = $this->repo->create($data);

        ActivityLogger::log('contract.created', 'finance', null, (int) $data['client_id'], [
            'contract_id' => $id,
            'title'       => $data['title'],
            'value'       => $data['value'],
            'status'      => $data['status'],
        ]);

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $contract = $this->findOrFail($id);
        $data = $this->sanitize($input);
        $this->requireReachableClient((int) $data['client_id']);
        $this->repo->updateById($id, Auth::agencyId(), $data);

        ActivityLogger::log('contract.updated', 'finance', null, (int) $data['client_id'], [
            'contract_id'   => $id,
            'status_before' => $contract['status'] ?? null,
            'status'        => $data['status'],
            'value'         => $data['value'],
        ]);
    }

    /**
     * Mesmo cuidado da fatura (SEC-11): o `client_id` do POST precisa ser de
     * cliente da agência e dentro do alcance de quem está logado.
     */
    private function requireReachableClient(int $clientId): void
    {
        if ($clientId <= 0 || !Auth::canAccessClient($clientId)) {
            throw new ValidationException(t('clients.out_of_reach'));
        }
    }

    public function delete(int $id): void
    {
        $contract = $this->findOrFail($id);   // alcance do cliente antes de apagar (SEC-11)

        if (!$this->repo->deleteById($id, Auth::agencyId())) {
            throw new ValidationException(t('finance.contract_not_found'));
        }

        ActivityLogger::log('contract.deleted', 'finance', null, (int) $contract['client_id'], [
            'contract_id' => $id,
            'title'       => $contract['title'] ?? null,
            'value'       => $contract['value'] ?? null,
        ]);
    }

    public function summary(): array
    {
        return $this->repo->summaryByAgency(Auth::agencyId());
    }

    public function activeForClient(int $clientId): array
    {
        return $this->repo->activeForClient($clientId, Auth::agencyId());
    }

    /**
     * Valida o que o formulário manda (SEC-16): título obrigatório, situação e
     * periodicidade por allowlist e valor lido com `Money` — antes,
     * `(float) str_replace(',', '.', …)` lia "1.234,56" como 1,23 e qualquer
     * texto como 0, então um POST cru assinava contrato de valor zero.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function sanitize(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(t('finance.title_required'));
        }

        $status = trim((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException(t('finance.status_invalid'));
        }

        // Campo não obrigatório na tela: vazio vale zero, como valia antes.
        $raw   = $input['value'] ?? null;
        $value = ($raw === null || (is_string($raw) && trim($raw) === '')) ? 0.0 : Money::parse($raw);
        if ($value === null || $value < 0 || !Money::inRange($value)) {
            throw new ValidationException(t('finance.value_invalid'));
        }

        $recurrence = trim((string) ($input['recurrence'] ?? ''));
        if ($recurrence !== '' && !in_array($recurrence, self::RECURRENCES, true)) {
            throw new ValidationException(t('finance.recurrence_invalid'));
        }

        return [
            'client_id'    => (int) ($input['client_id'] ?? 0),
            'title'        => $title,
            'description'  => trim($input['description'] ?? '') ?: null,
            'value'        => $value,
            'currency_code'=> $input['currency_code'] ?? 'BRL',
            'status'       => $status,
            'start_date'   => ($input['start_date'] ?? '') ?: null,
            'end_date'     => ($input['end_date'] ?? '') ?: null,
            'signed_at'    => ($input['signed_at'] ?? '') ?: null,
            'recurring'    => !empty($input['recurring']),
            'recurrence'   => $recurrence ?: null,
            'notes'        => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
