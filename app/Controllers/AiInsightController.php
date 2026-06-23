<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
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
        Auth::requirePermission('ai_insights.view');
        $accounts     = $this->accountRepo->listByAgency(Auth::agencyId());
        $aiConfigured = $this->aiService->isConfigured();
        return $this->view('ia.generate', compact('accounts', 'aiConfigured'));
    }

    public function generate(Request $request): Response
    {
        Auth::requirePermission('ai_insights.view');
        $agencyId  = Auth::agencyId();

        if (!$this->aiService->isConfigured()) {
            $this->withError(self::AI_NOT_CONFIGURED);
            return $this->redirect('/ia/gerar');
        }

        $accountId = (int) $request->post('ad_account_id', 0);
        $since     = $request->post('since', date('Y-m-d', strtotime('-30 days')));
        $until     = $request->post('until', date('Y-m-d'));
        $type      = $request->post('type', 'performance_summary');

        $account = $this->accountRepo->findByIdAndAgency($accountId, $agencyId);
        if (!$account) {
            $this->withError('Conta não encontrada.');
            return $this->redirect('/ia');
        }

        try {
            $insight = $this->aiService->generateForAccount(
                $agencyId, $accountId, $since, $until, $type, $account['client_id']
            );
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
        Auth::requirePermission('ai_insights.view');
        $agencyId = Auth::agencyId();

        $accountId = (int) $request->query('account_id', '0');
        $since     = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until     = $request->query('until', date('Y-m-d'));

        $accounts    = $this->accountRepo->listByAgency($agencyId);
        $suggestions = [];
        $account     = null;

        if ($accountId) {
            $account = $this->accountRepo->findByIdAndAgency($accountId, $agencyId);
            if (!$this->aiService->isConfigured()) {
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
        Auth::requirePermission('ai_insights.view');
        $agencyId  = Auth::agencyId();
        $accountId = (int) $request->post('account_id', 0);

        $selected = $request->post('suggestions', []);
        if (empty($selected) || !is_array($selected)) {
            $this->withError('Nenhuma sugestão selecionada.');
            return $this->back();
        }

        $saved = 0;
        foreach ($selected as $json) {
            $s = json_decode($json, true);
            if ($s) {
                $this->actionService->createFromAi($agencyId, $accountId, $s);
                $saved++;
            }
        }

        $this->withSuccess("{$saved} ação(ões) criada(s) — aguardando aprovação.");
        return $this->redirect('/trafego/acoes');
    }

    // ----------------------------------------------------------------- delete

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('ai_insights.view');
        $id = (int) $request->param('id');
        $this->repo->deleteById($id, Auth::agencyId());
        $this->withSuccess('Insight removido.');
        return $this->redirect('/ia');
    }
}
