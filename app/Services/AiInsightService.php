<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AiInsightRepository;
use App\Repositories\AdMetricsRepository;
use App\Repositories\PlatformSettingsRepository;
use GuzzleHttp\Client;

/**
 * Provider-agnostic AI insight generator.
 * Reads ai_provider + ai_model from platform_settings.
 * Supports: openai (gpt-4o, gpt-4-turbo…) and claude (claude-sonnet-4-6…).
 */
class AiInsightService
{
    private Client $http;

    public function __construct(
        private readonly AiInsightRepository        $insightRepo,
        private readonly AdMetricsRepository        $metricsRepo,
        private readonly PlatformSettingsRepository $platformSettings,
    ) {
        $this->http = new Client(['timeout' => 60]);
    }

    // ----------------------------------------------------------------- public

    /** Indica se há provedor + chave de API configurados para gerar insights. */
    public function isConfigured(): bool
    {
        $s = $this->platformSettings->getMultiple(['ai_provider', 'openai_api_key', 'anthropic_api_key']);
        $provider = $s['ai_provider'] ?? 'openai';
        $key = $provider === 'claude'
            ? ($s['anthropic_api_key'] ?? '')
            : ($s['openai_api_key'] ?? '');

        return trim((string) $key) !== '';
    }

    /**
     * Gera um insight de performance para uma conta no período dado.
     * Salva no banco e retorna o array persistido.
     */
    public function generateForAccount(
        int    $agencyId,
        int    $accountId,
        string $since,
        string $until,
        string $type = 'performance_summary',
        ?int   $clientId = null,
    ): array {
        $metrics = $this->metricsRepo->metricsPerCampaign($accountId, $since, $until);
        $summary = $this->metricsRepo->summaryForAccount($accountId, $since, $until);

        $prompt = $this->buildPrompt($type, $metrics, $summary, $since, $until);
        ['content' => $content, 'provider' => $provider, 'model' => $model] = $this->callAi($prompt);

        $id = $this->insightRepo->create([
            'agency_id'        => $agencyId,
            'client_id'        => $clientId,
            'ad_account_id'    => $accountId,
            'type'             => $type,
            'period_start'     => $since,
            'period_end'       => $until,
            'content'          => $content,
            'metrics_snapshot' => ['summary' => $summary, 'campaigns_count' => count($metrics)],
            'ai_provider'      => $provider,
            'model'            => $model,
        ]);

        return $this->insightRepo->findByIdAndAgency($id, $agencyId) ?? [];
    }

    /**
     * Gera recomendações de ações para uma conta.
     * Retorna array de ações sugeridas (não persiste — o controller decide).
     */
    public function recommendActions(
        int    $accountId,
        string $since,
        string $until,
    ): array {
        $campaigns = $this->metricsRepo->metricsPerCampaign($accountId, $since, $until);

        $prompt = $this->buildActionPrompt($campaigns, $since, $until);
        ['content' => $raw] = $this->callAi($prompt);

        return $this->parseActionSuggestions($raw, $campaigns);
    }

    // --------------------------------------------------------------- prompts

    private function buildPrompt(string $type, array $metrics, array $summary, string $since, string $until): string
    {
        $metricsJson = json_encode([
            'period'    => ['since' => $since, 'until' => $until],
            'summary'   => $summary,
            'campaigns' => array_map(fn($c) => [
                'name'        => $c['name'],
                'status'      => $c['status'],
                'spend'       => round((float)$c['spend'], 2),
                'impressions' => (int)$c['impressions'],
                'clicks'      => (int)$c['clicks'],
                'cpc'         => round((float)$c['cpc'], 2),
                'cpm'         => round((float)$c['cpm'], 2),
                'conversions' => (int)$c['conversions'],
                'roas'        => round((float)$c['roas'], 2),
            ], $metrics),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $typeLabel = match($type) {
            'performance_summary' => 'resumo de performance',
            'alert'               => 'alertas de atenção',
            'recommendation'      => 'recomendações de otimização',
            'report'              => 'relatório executivo',
            default               => 'análise',
        };

        return <<<PROMPT
Você é um especialista em tráfego pago com foco em Meta Ads. Analise os dados abaixo e gere um {$typeLabel} em português (pt-BR), detalhado e orientado a ação.

DADOS DE MÉTRICAS:
{$metricsJson}

Instruções:
- Use linguagem direta e profissional
- Destaque os pontos mais importantes (bom e ruim)
- Quando houver ROAS abaixo de 1, sinalize como crítico
- Sugira próximos passos concretos
- Formato: markdown com títulos, listas e destaques
- Máximo 800 palavras
PROMPT;
    }

    private function buildActionPrompt(array $campaigns, string $since, string $until): string
    {
        $json = json_encode(array_map(fn($c) => [
            'id'          => $c['id'],
            'name'        => $c['name'],
            'status'      => $c['status'],
            'daily_budget'=> $c['daily_budget'],
            'spend'       => round((float)$c['spend'], 2),
            'cpc'         => round((float)$c['cpc'], 2),
            'cpm'         => round((float)$c['cpm'], 2),
            'conversions' => (int)$c['conversions'],
            'roas'        => round((float)$c['roas'], 2),
        ], $campaigns), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Você é um especialista em tráfego pago. Analise as campanhas abaixo e sugira ações de otimização no formato JSON.

CAMPANHAS (período {$since} a {$until}):
{$json}

Retorne APENAS um JSON válido com a seguinte estrutura (array de ações):
[
  {
    "campaign_id": <id numérico ou null>,
    "action_type": "pause|resume|increase_budget|decrease_budget|test_creative|archive",
    "description": "Descrição clara da ação",
    "justification": "Por que essa ação deve ser tomada (dados específicos)",
    "current_value": "valor atual (ex: R$ 50/dia)",
    "proposed_value": "valor proposto (ex: R$ 35/dia)"
  }
]

Regras:
- Sugira apenas ações com justificativa baseada nos dados
- Para ROAS < 0.5, sempre sugira pausa ou redução de orçamento
- Para ROAS > 3, considere aumento de orçamento
- Máximo 5 sugestões
- Retorne apenas o JSON, sem texto adicional
PROMPT;
    }

    // ----------------------------------------------------------------- AI call

    private function callAi(string $prompt): array
    {
        $s = $this->platformSettings->getMultiple(['ai_provider', 'ai_model', 'openai_api_key', 'anthropic_api_key']);

        $provider = $s['ai_provider'] ?? 'openai';
        $model    = $s['ai_model'] ?? ($provider === 'openai' ? 'gpt-4o' : 'claude-sonnet-4-6');

        if ($provider === 'claude') {
            return $this->callClaude($prompt, $model, $s['anthropic_api_key'] ?? '');
        }

        return $this->callOpenAi($prompt, $model, $s['openai_api_key'] ?? '');
    }

    private function callOpenAi(string $prompt, string $model, string $apiKey): array
    {
        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key não configurada.');
        }

        $response = $this->http->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'    => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 1500,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return [
            'content'  => $data['choices'][0]['message']['content'] ?? '',
            'provider' => 'openai',
            'model'    => $model,
        ];
    }

    private function callClaude(string $prompt, string $model, string $apiKey): array
    {
        if (!$apiKey) {
            throw new \RuntimeException('Anthropic API key não configurada.');
        }

        $response = $this->http->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'json' => [
                'model'      => $model,
                'max_tokens' => 1500,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return [
            'content'  => $data['content'][0]['text'] ?? '',
            'provider' => 'claude',
            'model'    => $model,
        ];
    }

    // ---------------------------------------------------------- parse actions

    private function parseActionSuggestions(string $raw, array $campaigns): array
    {
        // Extrai JSON mesmo que venha com markdown code fence
        $json = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $json = preg_replace('/\s*```$/m', '', $json);

        $suggestions = json_decode(trim($json), true);
        if (!is_array($suggestions)) {
            return [];
        }

        // Mapeia campaign_id (da IA pode ser nosso internal ID) para o array
        $campaignMap = array_column($campaigns, null, 'id');

        return array_filter(array_map(function ($s) use ($campaignMap) {
            $cid = $s['campaign_id'] ?? null;
            return [
                'campaign_id'    => $cid,
                'campaign_name'  => $cid && isset($campaignMap[$cid]) ? $campaignMap[$cid]['name'] : null,
                'action_type'    => $s['action_type'] ?? 'pause',
                'description'    => $s['description'] ?? '',
                'justification'  => $s['justification'] ?? '',
                'current_value'  => $s['current_value'] ?? null,
                'proposed_value' => $s['proposed_value'] ?? null,
            ];
        }, $suggestions));
    }
}
