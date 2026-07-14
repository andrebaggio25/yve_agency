<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * OBS-01 — o `/api/health` diz a verdade sobre o sistema, sem contar demais.
 */
class HealthTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['QUEUE_SECRET'] = 'segredo-de-teste';
    }

    public function test_health_responde_e_nao_vaza_detalhes_sem_token(): void
    {
        $response = $this->get('/api/health');
        $body     = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatus());
        $this->assertArrayHasKey('status', $body);

        // Público não pode aprender nada da infraestrutura.
        $this->assertArrayNotHasKey('checks', $body, 'O /health público não pode expor os checks internos.');
        $this->assertArrayNotHasKey('env', $body);
    }

    public function test_com_token_devolve_os_checks_completos(): void
    {
        $response = $this->get('/api/health?token=segredo-de-teste');
        $body     = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatus());
        $this->assertArrayHasKey('checks', $body);

        foreach (['database', 'cron', 'queue', 'notifications', 'stale_syncs'] as $check) {
            $this->assertArrayHasKey($check, $body['checks'], "Check '{$check}' ausente.");
        }

        $this->assertTrue($body['checks']['database']['ok']);
    }

    /**
     * Cron parado é a falha mais traiçoeira: o app responde 200 e nada é
     * enviado. O /health tem de acusar.
     */
    public function test_cron_parado_deixa_o_sistema_degradado(): void
    {
        $body = json_decode($this->get('/api/health?token=segredo-de-teste')->getBody(), true);

        // Banco de teste zerado: nunca houve heartbeat → cron não-ok → degraded.
        $this->assertFalse($body['checks']['cron']['ok']);
        $this->assertSame('degraded', $body['status']);
    }

    public function test_job_falho_deixa_o_sistema_degradado(): void
    {
        $agencyId = $this->createAgency();

        // Simula o heartbeat recente do cron, para isolar o efeito do job falho.
        $this->pdo->prepare(
            "INSERT INTO platform_settings (key, value, created_at, updated_at)
             VALUES ('cron_last_run_work', :v, NOW(), NOW())
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        )->execute([':v' => date('c')]);

        $this->pdo->prepare(
            "INSERT INTO jobs (agency_id, queue, payload, status, attempts, max_attempts, available_at, created_at, updated_at)
             VALUES (:a, 'automations', '{}', 'failed', 3, 3, NOW(), NOW(), NOW())"
        )->execute([':a' => $agencyId]);

        $body = json_decode($this->get('/api/health?token=segredo-de-teste')->getBody(), true);

        $this->assertSame(1, $body['checks']['queue']['failed']);
        $this->assertFalse($body['checks']['queue']['ok']);
        $this->assertSame('degraded', $body['status']);
    }
}
