<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\LoginThrottle;

/**
 * SEC-18 — rate limit das rotas públicas do portal.
 *
 * O portal só tem o token da URL como credencial, e ele circula por WhatsApp.
 * Sem teto, um token vazado valia escrita e upload ilimitados. O que estes
 * testes travam: a cota é por IP + token (não por rota), leitura não entra na
 * cota (a galeria chama o proxy uma vez por miniatura), o upload tem cesta
 * própria e maior, e cada token tem a sua.
 *
 * Nada aqui fala com o Google: as mutações usadas ou são de banco (aprovar o
 * plano) ou param na validação antes da chamada ao Drive (nome vazio).
 */
class PortalRateLimitTest extends FeatureTestCase
{
    /** Teto das mutações que não são upload (espelha a constante do middleware). */
    private const WRITE_MAX = 60;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearRateLimitFiles();
    }

    protected function tearDown(): void
    {
        $this->clearRateLimitFiles();
        parent::tearDown();
    }

    private function clearRateLimitFiles(): void
    {
        foreach (glob(storage_path('framework/rate_limit_*')) ?: [] as $file) {
            @unlink($file);
        }
    }

    /** @return array{plan:int,token:string} */
    private function seedSentPlan(int $agencyId): array
    {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, 'Semana 02/11', '2026-11-02', '2026-11-08', 'sent', NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id']]);

        return ['plan' => (int) $stmt->fetchColumn(), 'token' => $client['portal_token']];
    }

    /**
     * Gasta a cota de escrita do token com mutações baratas (nome vazio → 422,
     * sem nenhuma chamada ao Drive). O limitador conta antes do controller.
     */
    private function exhaustWriteQuota(string $token): void
    {
        for ($i = 0; $i < self::WRITE_MAX; $i++) {
            $response = $this->post("/portal/{$token}/drive/folders", ['name' => '']);
            $this->assertSame(422, $response->getStatus(), "Requisição {$i} devia passar pelo limitador.");
        }
    }

    // ── Positivo: a rota funciona com o limitador no pipeline ────────────────

    public function test_mutacao_do_portal_funciona_abaixo_do_limite(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);

        $response = $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(302, $response->getStatus());
        $this->assertSame(
            'approved',
            $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn(),
            'Com o limitador na rota, a aprovação normal do cliente tem de continuar passando.'
        );
    }

    // ── Negativo: a rajada é barrada ─────────────────────────────────────────

    public function test_rajada_de_mutacoes_do_portal_devolve_429(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $this->exhaustWriteQuota($client['portal_token']);

        $response = $this->post("/portal/{$client['portal_token']}/drive/folders", ['name' => '']);

        $this->assertSame(429, $response->getStatus());
        // A tela de estouro é a do portal (idioma do cliente), não a do login.
        $this->assertStringContainsString('Muitas solicitações', $response->getBody());
        $this->assertStringNotContainsString('/login', $response->getBody());
    }

    public function test_estouro_em_json_devolve_mensagem_traduzida_e_retry_after(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $this->exhaustWriteQuota($client['portal_token']);

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        try {
            $response = $this->post("/portal/{$client['portal_token']}/drive/folders", ['name' => '']);
        } finally {
            unset($_SERVER['HTTP_ACCEPT']);
        }

        $this->assertSame(429, $response->getStatus());
        $payload = json_decode($response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame(t('portal.rate_limited.text'), $payload['error'] ?? null);
        $this->assertGreaterThan(0, $payload['retry_after'] ?? 0);
    }

    /**
     * O limitador do login tem chave por path — ali cada rota tem a sua cota.
     * No portal isso não serviria: o token é a credencial, e trocar de rota
     * daria cota nova. Aqui a cota gasta no Drive também barra a aprovação.
     */
    public function test_cota_vale_para_o_token_inteiro_nao_por_rota(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);

        $this->exhaustWriteQuota($seed['token']);

        $response = $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(429, $response->getStatus());
        $this->assertSame(
            'sent',
            $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn(),
            'A requisição barrada não pode ter chegado ao controller.'
        );
    }

    // ── Leitura e upload: cestas separadas ───────────────────────────────────

    /**
     * A galeria pede `/drive/file/{id}/raw` uma vez por miniatura. Teto na
     * leitura quebraria a tela em silêncio (a miniatura some no `onerror`).
     */
    public function test_leitura_do_portal_nao_entra_na_cota(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);

        for ($i = 0; $i < self::WRITE_MAX + 5; $i++) {
            $response = $this->get("/portal/{$seed['token']}/drive/file/999999/raw");
            $this->assertSame(404, $response->getStatus(), "Miniatura {$i} não podia ser barrada.");
        }

        // E a cota de escrita continua inteira depois da rolagem da galeria.
        $this->assertSame(
            302,
            $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar")->getStatus()
        );
    }

    /**
     * Cada arquivo são 2 POSTs nossos; uma remessa grande passaria do teto das
     * outras mutações. Por isso o upload tem cesta própria e maior.
     */
    public function test_upload_tem_cesta_propria_e_maior(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $this->exhaustWriteQuota($client['portal_token']);

        // `name` vazio → 422 na validação, antes de qualquer chamada ao Drive.
        $response = $this->post("/portal/{$client['portal_token']}/drive/upload/session", ['name' => '', 'size' => 0]);

        $this->assertSame(422, $response->getStatus(), 'A cota de escrita não pode fechar o envio de arquivos.');
    }

    public function test_cada_token_tem_a_sua_cesta(): void
    {
        $agencyId = $this->createAgency();
        $vizinho  = $this->createClient($agencyId, 'Cliente Vizinho');
        $seed     = $this->seedSentPlan($agencyId);

        $this->exhaustWriteQuota($vizinho['portal_token']);

        $response = $this->post("/portal/{$seed['token']}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(302, $response->getStatus(), 'O estouro de um cliente não pode travar o portal do outro.');
        $this->assertSame(
            'approved',
            $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn()
        );
    }

    // ── Regressão: o limitador do login segue como era ───────────────────────

    /**
     * O `PortalRateLimitMiddleware` nasceu de um refactor do limitador do login
     * (chave e teto viraram métodos). O login não pode ter afrouxado: 5 POSTs
     * por IP + path e o 6º é 429.
     */
    public function test_rate_limit_do_login_continua_em_cinco_tentativas(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId, 'rate-limit-login@test.com');
        LoginThrottle::clear($user['email']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => $user['email'], 'password' => 'senha-errada']);
            $this->assertNotSame(429, $response->getStatus(), "Tentativa {$i} não devia ser barrada.");
        }

        $response = $this->post('/login', ['email' => $user['email'], 'password' => 'senha-errada']);

        $this->assertSame(429, $response->getStatus());
        LoginThrottle::clear($user['email']);
    }
}
