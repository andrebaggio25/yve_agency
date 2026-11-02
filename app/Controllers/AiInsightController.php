<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\ActivityLogger;
use App\Support\Auth;
use App\Repositories\AiInsightRepository;
use App\Repositories\AdAccountRepository;
use App\Services\AiInsightService;
use App\Services\AdsActionService;

class AiInsightController extends Controller
{
    public function __construct(
        private readonly AiInsightRepository $repo,
        private readonly AdAccountRepository $accountRepo,
        private readonly AiInsightService    $aiService,
        private readonly AdsActionService    $actionService,
    ) {}

    // ----------------------------------------------------------------- list

    public function index(Request $request): Response
    {
        Auth::requirePermission('ai_insights.view');
        $agencyId = Auth::agencyId();

        $filters = [
            'ad_account_id' => $request->query('account_id', ''),
            'type'          => $request->query('type', ''),
        ];

        $insights = $this->repo->listByAgency($agencyId, $filters);
        $accounts = $this->accountRepo->listByAgency($agencyId);

        return $this->view('ia.index', compact('insights', 'accounts', 'filters'));
    }

    // ----------------------------------------------------------------- show

    public function show(Request $request): Response
    {
        Auth::requirePermission('ai_insights.view');
        $id       = (int) $request->param('id');
        $agencyId = Auth::agencyId();

        $insight = $this->repo->findByIdAndAgency($id, $agencyId);
        if (!$insight) {
            return Response::view('errors.404', [], 404);
        }

        return $this->view('ia.show', compact('insight'));
    }

    // ---------------------------------------------------------------- generate

    private const AI_NOT_CONFIGURED = 'A IA ainda não foi configurada. Peça ao administrador da plataforma para definir o provedor e a chave de API em Configurações do Admin.';

    public function generateForm(Request $request): Response
    {
        Auth::requirePermission('ai.generate_report');
        $accounts     = $this->accountRepo->listByAgency(Auth::agencyId());
        $aiConfigured = $this->aiService->isConfigured();
        return $this->view('ia.generate', compact('accounts', 'aiConfigured'));
    }

    public function generate(Request $request): Response
    {
        // Gerar queima a chave de API da plataforma: é `ai.generate_report`, não
        // a permissão de leitura da lista. A permissão já existia no catálogo e
        // no papel Gestor de Tráfego, mas ninguém a verificava.
        Auth::requirePermission('ai.generate_report');
        $agencyId  = Auth::agencyId();

        $accountId = (int) $request->post('ad_account_id', 0);
        $since     = $request->post('since', date('Y-m-d', strtotime('-30 days')));
        $until     = $request->post('until', date('Y-m-d'));
        $type      = $request->post('type', 'performance_summary');

        // O tipo vem cru do POST e é gravado como veio: valida antes de tudo.
        if (!in_array($type, AiInsightService::INSIGHT_TYPES, true)) {
            $this->withError(t('ai.type_invalid'));
            return $this->redirect('/ia/gerar');
        }

        if (!$this->aiService->isConfigured()) {
            $this->withError(self::AI_NOT_CONFIGURED);
            return $this->redirect('/ia/gerar');
        }

        $account = $this->accountRepo->findByIdAndAgency($accountId, $agencyId);
        if (!$account) {
            $this->withError('Conta não encontrada.');
            return $this->redirect('/ia');
        }

        try {
            $insight = $this->aiService->generateForAccount(
                $agencyId, $accountId, $since, $until, $type, $account['client_id']
            );

            // SEC-15: gerar insight queima a chave de API da plataforma — é
            // custo da conta do dono, e precisa ser rastreável a quem pediu.
            ActivityLogger::log('ai_insight.generated', 'ai', null, (int) ($account['client_id'] ?? 0) ?: null, [
                'insight_id'    => (int) $insight['id'],
                'ad_account_id' => $accountId,
                'type'          => $type,
                'since'         => $since,
                'until'         => $until,
            ]);

            $this->withSuccess('Insight gerado com sucesso!');
            return $this->redirect('/ia/' . $insight['id']);
        } catch (\Throwable $e) {
            $this->withError('Erro ao gerar insight: ' . $e->getMessage());
            return $this->redirect('/ia/gerar');
        }
    }

    // ----------------------------------------------------------- recommendations

    public function recommendations(Request $request): Response
    {
        // Também chama a IA: `ai.recommend_ads_action` (idem, permissão morta até aqui).
        Auth::requirePermission('ai.recommend_ads_action');
        $agencyId = Auth::agencyId();

        $accountId = (int) $request->query('account_id', '0');
        $since     = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until     = $request->query('until', date('Y-m-d'));

        $accounts    = $this->accountRepo->listByAgency($agencyId);
        $suggestions = [];
        $account     = null;

        if ($accountId) {
            $account = $this->accountRepo->findByIdAndAgency($accountId, $agencyId);
            if (!$account) {
                // O id vinha do `?account_id=` e só decidia o título da tela: as
                // métricas da conta alheia iam para a IA e voltavam com o nome
                // das campanhas dela na lista (SEC-13).
                $this->withError(t('ads.account_not_found'));
                $accountId = 0;
            } elseif (!$this->aiService->isConfigured()) {
                $this->withError(self::AI_NOT_CONFIGURED);
            } else {
                try {
                    $suggestions = $this->aiService->recommendActions($accountId, $since, $until);
                } catch (\Throwable $e) {
                    $this->withError('Erro ao gerar recomendações: ' . $e->getMessage());
                }
            }
        }

        return $this->view('ia.recommendations', compact('accounts', 'account', 'suggestions', 'accountId', 'since', 'until'));
    }

    /** Salva sugestões selecionadas como ads_actions */
    public function saveRecommendations(Request $request): Response
    {
        // Esta rota grava em `ads_actions` — a mesma escrita de
        // `AdsActionController::store`, que cobra `ads_actions.request`. Com só
        // `ai_insights.view`, um perfil de leitura punha solicitação na fila do
        // aprovador sem nunca ter tido permissão de solicitar.
        Auth::requirePermission('ai.recommend_ads_action');
        Auth::requirePermission('ads_actions.request');
        $agencyId  = Auth::agencyId();
        $accountId = (int) $request->post('account_id', 0);

        $selected = $request->post('suggestions', []);
        if (empty($selected) || !is_array($selected)) {
            $this->withError('Nenhuma sugestão selecionada.');
            return $this->back();
        }

        // account_id e os ids dentro de cada sugestão vêm do POST (SEC-13).
        if (!$this->accountRepo->findByIdAndAgency($accountId, (int) $agencyId)) {
            $this->withError(t('ads.account_not_found'));
            return $this->back();
        }

        $saved     = 0;
        $recusadas = 0;
        foreach ($selected as $json) {
            $s = json_decode((string) $json, true);
            if (!is_array($s)) {
                continue;
            }
            try {
                $this->actionService->createFromAi($agencyId, $accountId, $s);
                $saved++;
            } catch (\Throwable) {
                $recusadas++;
            }
        }

        if ($recusadas > 0) {
            $this->withError(t('ads.suggestions_refused', ['count' => $recusadas]));
        }

        $this->withSuccess("{$saved} ação(ões) criada(s) — aguardando aprovação.");
        return $this->redirect('/trafego/acoes');
    }

    // ----------------------------------------------------------------- delete

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('ai_insights.view');
        $id       = (int) $request->param('id');
        $agencyId = (int) Auth::agencyId();

        // Lido antes só para a trilha: o DELETE já é escopado por agência.
        $insight = $this->repo->findByIdAndAgency($id, $agencyId);
        $this->repo->deleteById($id, $agencyId);

        if ($insight) {
            ActivityLogger::log('ai_insight.deleted', 'ai', null, (int) ($insight['client_id'] ?? 0) ?: null, [
                'insight_id'    => $id,
                'ad_account_id' => $insight['ad_account_id'] ?? null,
                'type'          => $insight['type'] ?? null,
            ]);
        }

        $this->withSuccess('Insight removido.');
        return $this->redirect('/ia');
    }
}
