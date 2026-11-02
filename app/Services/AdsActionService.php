<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdsActionRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\AdSetRepository;
use App\Repositories\AdRepository;
use App\Repositories\AdAccountRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;

class AdsActionService
{
    /**
     * Os tipos que `dispatchToMeta` sabe executar (`test_creative` é manual).
     * O `<select>` da tela não é barreira: o POST chega cru e a coluna é um
     * varchar sem CHECK, então sem esta lista o valor ia para o banco e saía
     * na listagem como rótulo — inclusive com HTML dentro.
     *
     * @var list<string>
     */
    public const ACTION_TYPES = [
        'pause', 'resume', 'increase_budget', 'decrease_budget', 'test_creative', 'archive',
    ];

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
        $accountId  = (int) $input['ad_account_id'];
        $campaignId = !empty($input['campaign_id']) ? (int) $input['campaign_id'] : null;
        $adSetId    = !empty($input['ad_set_id'])   ? (int) $input['ad_set_id']   : null;
        $adId       = !empty($input['ad_id'])       ? (int) $input['ad_id']       : null;

        $this->assertActionType($input['action_type'] ?? null);
        $this->assertTargets($agencyId, $accountId, $campaignId, $adSetId, $adId);

        $id = $this->repo->create([
            'agency_id'      => $agencyId,
            'ad_account_id'  => $accountId,
            'campaign_id'    => $campaignId,
            'ad_set_id'      => $adSetId,
            'ad_id'          => $adId,
            'action_type'    => $input['action_type'],
            'description'    => $input['description'],
            'justification'  => $input['justification'] ?? null,
            'current_value'  => $input['current_value'] ?? null,
            'proposed_value' => $input['proposed_value'] ?? null,
            'ai_generated'   => false,
            'requested_by'   => Auth::id(),
        ]);

        ActivityLogger::log('ads_action.requested', 'ads_actions', null, null, [
            'action_id'      => $id,
            'ad_account_id'  => $accountId,
            'action_type'    => $input['action_type'],
            'campaign_id'    => $campaignId,
            'ad_set_id'      => $adSetId,
            'ad_id'          => $adId,
            'proposed_value' => $input['proposed_value'] ?? null,
            'ai_generated'   => false,
        ], $agencyId);

        return $id;
    }

    public function createFromAi(int $agencyId, int $accountId, array $suggestion): int
    {
        // A sugestão volta pelo POST (input hidden): os ids são tão crus quanto
        // os do formulário manual (SEC-13).
        $campaignId = !empty($suggestion['campaign_id']) ? (int) $suggestion['campaign_id'] : null;
        $adSetId    = !empty($suggestion['ad_set_id'])   ? (int) $suggestion['ad_set_id']   : null;
        $adId       = !empty($suggestion['ad_id'])       ? (int) $suggestion['ad_id']       : null;

        $this->assertActionType($suggestion['action_type'] ?? null);
        $this->assertTargets($agencyId, $accountId, $campaignId, $adSetId, $adId);

        $id = $this->repo->create([
            'agency_id'      => $agencyId,
            'ad_account_id'  => $accountId,
            'campaign_id'    => $campaignId,
            'ad_set_id'      => $adSetId,
            'ad_id'          => $adId,
            'action_type'    => $suggestion['action_type'],
            'description'    => $suggestion['description'],
            'justification'  => $suggestion['justification'] ?? null,
            'current_value'  => $suggestion['current_value'] ?? null,
            'proposed_value' => $suggestion['proposed_value'] ?? null,
            'ai_generated'   => true,
            'requested_by'   => Auth::id(),
        ]);

        ActivityLogger::log('ads_action.requested', 'ads_actions', null, null, [
            'action_id'      => $id,
            'ad_account_id'  => $accountId,
            'action_type'    => $suggestion['action_type'],
            'campaign_id'    => $campaignId,
            'ad_set_id'      => $adSetId,
            'ad_id'          => $adId,
            'proposed_value' => $suggestion['proposed_value'] ?? null,
            'ai_generated'   => true,
        ], $agencyId);

        return $id;
    }

    /** O tipo é rótulo na tela e verbo na Meta: fora da lista, não grava. */
    private function assertActionType(mixed $type): void
    {
        if (!is_string($type) || !in_array($type, self::ACTION_TYPES, true)) {
            throw new \RuntimeException(t('ads.action_type_invalid'));
        }
    }

    /**
     * SEC-13: a conta tem de ser da agência e o alvo (campanha/conjunto/anúncio)
     * tem de estar dentro dessa conta. `campaigns`, `ad_sets` e `ads` não têm
     * agency_id, então sem esta checagem o POST plantava na solicitação um alvo
     * de outra agência — e a listagem passava a exibir o nome dele.
     */
    private function assertTargets(int $agencyId, int $accountId, ?int $campaignId, ?int $adSetId, ?int $adId): void
    {
        if ($accountId <= 0 || !$this->accountRepo->findByIdAndAgency($accountId, $agencyId)) {
            throw new \RuntimeException(t('ads.account_not_found'));
        }

        $alheio = ($campaignId !== null && !$this->campaignRepo->findByIdAndAccount($campaignId, $accountId))
            || ($adSetId !== null && !$this->adSetRepo->findByIdAndAccount($adSetId, $accountId))
            || ($adId !== null && !$this->adRepo->findByIdAndAccount($adId, $accountId));

        if ($alheio) {
            throw new \RuntimeException(t('ads.target_out_of_account'));
        }
    }

    public function approve(int $id, int $agencyId): void
    {
        $action = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$action || $action['status'] !== 'pending') {
            throw new \RuntimeException('Ação não encontrada ou já processada.');
        }
        $this->repo->setStatus($id, 'approved', Auth::id());

        ActivityLogger::log('ads_action.approved', 'ads_actions', null, null, [
            'action_id'     => $id,
            'ad_account_id' => (int) $action['ad_account_id'],
            'action_type'   => $action['action_type'],
        ], $agencyId);
    }

    public function reject(int $id, int $agencyId): void
    {
        $action = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$action || $action['status'] !== 'pending') {
            throw new \RuntimeException('Ação não encontrada ou já processada.');
        }
        $this->repo->setStatus($id, 'rejected', Auth::id());

        ActivityLogger::log('ads_action.rejected', 'ads_actions', null, null, [
            'action_id'     => $id,
            'ad_account_id' => (int) $action['ad_account_id'],
            'action_type'   => $action['action_type'],
        ], $agencyId);
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
            // A tentativa que falhou também entra na trilha: a chamada pode ter
            // chegado à Meta antes do erro. O texto do erro não vem para cá —
            // ele já fica em `ads_actions.error_message`, e mensagem de API é
            // string de terceiro (pode carregar parâmetro da requisição).
            $this->logExecution($id, $action, $agencyId, false);
            throw $e;
        }

        $this->logExecution($id, $action, $agencyId, true);
    }

    /**
     * Pausar campanha e trocar orçamento mexem no dinheiro do cliente na Meta:
     * é a ação mais sensível do módulo e não deixava rastro nenhum (SEC-15).
     *
     * @param array<string,mixed> $action
     */
    private function logExecution(int $id, array $action, int $agencyId, bool $ok): void
    {
        ActivityLogger::log($ok ? 'ads_action.executed' : 'ads_action.execution_failed', 'ads_actions', null, null, [
            'action_id'      => $id,
            'ad_account_id'  => (int) $action['ad_account_id'],
            'action_type'    => $action['action_type'],
            'campaign_id'    => $action['campaign_id'] ?? null,
            'ad_set_id'      => $action['ad_set_id'] ?? null,
            'ad_id'          => $action['ad_id'] ?? null,
            'proposed_value' => $action['proposed_value'] ?? null,
        ], $agencyId);
    }

    private function dispatchToMeta(array $action, string $token): void
    {
        $type      = $action['action_type'];
        $accountId = (int) $action['ad_account_id'];

        // Determina o objeto alvo e o endpoint. O alvo é relido pela conta da
        // solicitação (SEC-13): linha antiga, gravada antes da validação, com
        // alvo de outra agência não chega a virar chamada na Meta.
        if ($action['ad_id']) {
            $platformId = $this->getAdPlatformId((int) $action['ad_id'], $accountId);
            $endpoint   = $platformId;
        } elseif ($action['ad_set_id']) {
            $platformId = $this->getAdSetPlatformId((int) $action['ad_set_id'], $accountId);
            $endpoint   = $platformId;
        } elseif ($action['campaign_id']) {
            $platformId = $this->getCampaignPlatformId((int) $action['campaign_id'], $accountId);
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

    private function getCampaignPlatformId(int $id, int $accountId): string
    {
        $c = $this->campaignRepo->findByIdAndAccount($id, $accountId);
        return $c['platform_id'] ?? throw new \RuntimeException("Campanha {$id} não encontrada.");
    }

    private function getAdSetPlatformId(int $id, int $accountId): string
    {
        $s = $this->adSetRepo->findByIdAndAccount($id, $accountId);
        return $s['platform_id'] ?? throw new \RuntimeException("Conjunto {$id} não encontrado.");
    }

    private function getAdPlatformId(int $id, int $accountId): string
    {
        $ad = $this->adRepo->findByIdAndAccount($id, $accountId);
        return $ad['platform_id'] ?? throw new \RuntimeException("Anúncio {$id} não encontrado.");
    }
}
