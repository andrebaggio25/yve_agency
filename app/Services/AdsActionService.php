<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdsActionRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\AdSetRepository;
use App\Repositories\AdRepository;
use App\Repositories\AdAccountRepository;
use App\Support\Auth;

class AdsActionService
{
    public function __construct(
        private readonly AdsActionRepository $repo,
        private readonly CampaignRepository  $campaignRepo,
        private readonly AdSetRepository     $adSetRepo,
        private readonly AdRepository        $adRepo,
        private readonly AdAccountRepository $accountRepo,
        private readonly MetaAdsService      $meta,
    ) {}

    public function createManual(int $agencyId, array $input): int
    {
        return $this->repo->create([
            'agency_id'      => $agencyId,
            'ad_account_id'  => (int) $input['ad_account_id'],
            'campaign_id'    => !empty($input['campaign_id']) ? (int) $input['campaign_id'] : null,
            'ad_set_id'      => !empty($input['ad_set_id'])   ? (int) $input['ad_set_id']   : null,
            'ad_id'          => !empty($input['ad_id'])        ? (int) $input['ad_id']        : null,
            'action_type'    => $input['action_type'],
            'description'    => $input['description'],
            'justification'  => $input['justification'] ?? null,
            'current_value'  => $input['current_value'] ?? null,
            'proposed_value' => $input['proposed_value'] ?? null,
            'ai_generated'   => false,
            'requested_by'   => Auth::id(),
        ]);
    }

    public function createFromAi(int $agencyId, int $accountId, array $suggestion): int
    {
        return $this->repo->create([
            'agency_id'      => $agencyId,
            'ad_account_id'  => $accountId,
            'campaign_id'    => $suggestion['campaign_id'] ?? null,
            'ad_set_id'      => $suggestion['ad_set_id'] ?? null,
            'ad_id'          => $suggestion['ad_id'] ?? null,
            'action_type'    => $suggestion['action_type'],
            'description'    => $suggestion['description'],
            'justification'  => $suggestion['justification'] ?? null,
            'current_value'  => $suggestion['current_value'] ?? null,
            'proposed_value' => $suggestion['proposed_value'] ?? null,
            'ai_generated'   => true,
            'requested_by'   => Auth::id(),
        ]);
    }

    public function approve(int $id, int $agencyId): void
    {
        $action = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$action || $action['status'] !== 'pending') {
            throw new \RuntimeException('Ação não encontrada ou já processada.');
        }
        $this->repo->setStatus($id, 'approved', Auth::id());
    }

    public function reject(int $id, int $agencyId): void
    {
        $action = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$action || $action['status'] !== 'pending') {
            throw new \RuntimeException('Ação não encontrada ou já processada.');
        }
        $this->repo->setStatus($id, 'rejected', Auth::id());
    }

    /**
     * Executa a ação aprovada via Meta Ads API.
     * Suporta: pause, resume, increase_budget, decrease_budget, archive.
     */
    public function execute(int $id, int $agencyId): void
    {
        $action = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$action || $action['status'] !== 'approved') {
            throw new \RuntimeException('Ação não aprovada ou não encontrada.');
        }

        $account = $this->accountRepo->findByIdAndAgency($action['ad_account_id'], $agencyId);
        if (!$account) {
            throw new \RuntimeException('Conta de anúncios não encontrada.');
        }

        try {
            $this->dispatchToMeta($action, $account['access_token']);
            $this->repo->setStatus($id, 'executed');
        } catch (\Throwable $e) {
            $this->repo->setError($id, $e->getMessage());
            throw $e;
        }
    }

    private function dispatchToMeta(array $action, string $token): void
    {
        $type = $action['action_type'];

        // Determina o objeto alvo e o endpoint
        if ($action['ad_id']) {
            $platformId = $this->getAdPlatformId($action['ad_id']);
            $endpoint   = $platformId;
        } elseif ($action['ad_set_id']) {
            $platformId = $this->getAdSetPlatformId($action['ad_set_id']);
            $endpoint   = $platformId;
        } elseif ($action['campaign_id']) {
            $platformId = $this->getCampaignPlatformId($action['campaign_id']);
            $endpoint   = $platformId;
        } else {
            throw new \RuntimeException('Nenhum objeto alvo definido para a ação.');
        }

        match ($type) {
            'pause'           => $this->meta->updateStatus($endpoint, $token, 'PAUSED'),
            'resume'          => $this->meta->updateStatus($endpoint, $token, 'ACTIVE'),
            'archive'         => $this->meta->updateStatus($endpoint, $token, 'ARCHIVED'),
            'increase_budget',
            'decrease_budget' => $this->meta->updateBudget($endpoint, $token, $action['proposed_value']),
            default           => throw new \RuntimeException("Tipo de ação '{$type}' não suportado para execução automática."),
        };
    }

    private function getCampaignPlatformId(int $id): string
    {
        $c = $this->campaignRepo->findById($id);
        return $c['platform_id'] ?? throw new \RuntimeException("Campanha {$id} não encontrada.");
    }

    private function getAdSetPlatformId(int $id): string
    {
        $s = $this->adSetRepo->findById($id);
        return $s['platform_id'] ?? throw new \RuntimeException("Conjunto {$id} não encontrado.");
    }

    private function getAdPlatformId(int $id): string
    {
        $ad = $this->adRepo->findById($id);
        return $ad['platform_id'] ?? throw new \RuntimeException("Anúncio {$id} não encontrado.");
    }
}
