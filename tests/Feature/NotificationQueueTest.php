<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Container;
use App\Services\NotificationService;

/**
 * INFRA-01 (fila única) + INT-02 (espaçamento de WhatsApp).
 *
 * Antes existiam duas filas: `jobs` (boa — SKIP LOCKED, backoff, alerta) e
 * `notification_jobs` (primitiva, **fora do alerta do OBS-01**). Ou seja: um
 * lembrete de fatura que morresse não avisava ninguém — justamente a fila que
 * mais importa para o cliente era a única não vigiada.
 */
class NotificationQueueTest extends FeatureTestCase
{
    private function service(): NotificationService
    {
        return Container::getInstance()->make(NotificationService::class);
    }

    /** @return array<int,array<string,mixed>> */
    private function jobsInQueue(): array
    {
        return $this->pdo->query("SELECT * FROM jobs WHERE queue = 'notifications' ORDER BY available_at")->fetchAll();
    }

    public function test_entrega_cria_registro_e_job_na_fila_unica(): void
    {
        $agencyId = $this->createAgency();

        $id = $this->service()->queueDelivery([
            'agency_id' => $agencyId,
            'channel'   => 'email',
            'recipient' => 'cliente@test.com',
            'template'  => 'invoice_sent',
            'payload'   => ['to_name' => 'Cliente'],
        ]);

        // 1. registro de entrega (histórico — alimenta a timeline do OBS-02)
        $delivery = $this->pdo->query("SELECT * FROM notification_jobs WHERE id = {$id}")->fetch();
        $this->assertSame('pending', $delivery['status']);
        $this->assertSame('cliente@test.com', $delivery['recipient']);

        // 2. job na fila ÚNICA — é o que garante retry com backoff e alerta
        $jobs = $this->jobsInQueue();
        $this->assertCount(1, $jobs);

        $payload = json_decode($jobs[0]['payload'], true);
        $this->assertSame(\App\Jobs\SendNotificationJob::class, $payload['job']);
        $this->assertSame($id, $payload['data']['notification_id']);
    }

    /**
     * INT-02: o WhatsApp bane número que dispara em rajada — e o número é o
     * telefone da agência. Envios têm de sair espaçados.
     */
    public function test_whatsapp_e_espacado_para_nao_queimar_o_numero(): void
    {
        $agencyId = $this->createAgency();
        $service  = $this->service();

        for ($i = 1; $i <= 4; $i++) {
            $service->queueDelivery([
                'agency_id' => $agencyId,
                'channel'   => 'whatsapp',
                'recipient' => '5511999990' . $i,
                'template'  => 'invoice_due_reminder',
                'payload'   => [],
            ]);
        }

        $jobs = $this->jobsInQueue();
        $this->assertCount(4, $jobs);

        $times = array_map(fn ($j) => strtotime((string) $j['available_at']), $jobs);

        // Cada envio fica ao menos alguns segundos após o anterior — nada de rajada.
        for ($i = 1; $i < count($times); $i++) {
            $gap = $times[$i] - $times[$i - 1];
            $this->assertGreaterThanOrEqual(
                5,
                $gap,
                "Envio #{$i} saiu {$gap}s depois do anterior — rajada queima o número da agência."
            );
        }
    }

    /** E-mail não precisa de espaçamento: sai o quanto antes. */
    public function test_email_nao_e_espacado(): void
    {
        $agencyId = $this->createAgency();

        foreach (['a@test.com', 'b@test.com', 'c@test.com'] as $to) {
            $this->service()->queueDelivery([
                'agency_id' => $agencyId,
                'channel'   => 'email',
                'recipient' => $to,
                'template'  => 'invoice_sent',
                'payload'   => [],
            ]);
        }

        $times = array_map(fn ($j) => strtotime((string) $j['available_at']), $this->jobsInQueue());

        $this->assertLessThanOrEqual(1, max($times) - min($times), 'E-mails não devem ser adiados.');
    }

    // ── OBS-02: a tela de entregas ───────────────────────────────────────────

    public function test_timeline_de_entregas_exige_permissao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: []);
        $this->assertSame(403, $this->get('/automations/deliveries')->getStatus());
    }

    public function test_timeline_mostra_as_entregas_da_agencia_e_nao_de_outra(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');
        $user    = $this->createUser($agencyA, 'a@test.com');

        $this->service()->queueDelivery([
            'agency_id' => $agencyA, 'channel' => 'email',
            'recipient' => 'daminhaagencia@test.com', 'template' => 'invoice_sent', 'payload' => [],
        ]);
        $this->service()->queueDelivery([
            'agency_id' => $agencyB, 'channel' => 'email',
            'recipient' => 'deoutraagencia@test.com', 'template' => 'invoice_sent', 'payload' => [],
        ]);

        $this->actingAs($user['id'], permissions: ['automations.view']);
        $response = $this->get('/automations/deliveries');

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('daminhaagencia@test.com', $response->getBody());
        $this->assertStringNotContainsString('deoutraagencia@test.com', $response->getBody());
    }

    /**
     * Resgate do legado: entrega pendente sem job na fila (deixada pela fila
     * antiga) volta a ser processada em vez de morrer esquecida.
     */
    public function test_entrega_orfa_do_legado_e_resgatada(): void
    {
        $agencyId = $this->createAgency();

        // Simula o registro que a fila antiga deixou (sem job correspondente).
        $this->pdo->prepare(
            "INSERT INTO notification_jobs (agency_id, channel, recipient, template, locale, payload, status, attempts, created_at)
             VALUES (:a, 'email', 'orfao@test.com', 'invoice_sent', 'pt', '{}', 'pending', 0, NOW())"
        )->execute([':a' => $agencyId]);

        $this->assertCount(0, $this->jobsInQueue());

        $rescued = $this->service()->rescueOrphanDeliveries();

        $this->assertSame(1, $rescued);
        $this->assertCount(1, $this->jobsInQueue(), 'A entrega órfã precisa voltar para a fila.');

        // Idempotente: rodar de novo não duplica (senão o cliente receberia 2×).
        $this->assertSame(0, $this->service()->rescueOrphanDeliveries());
        $this->assertCount(1, $this->jobsInQueue());
    }
}
