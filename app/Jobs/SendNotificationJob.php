<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Container;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;
use RuntimeException;

/**
 * Envia uma notificação (WhatsApp ou e-mail) — INFRA-01.
 *
 * O envio era processado por uma fila **própria** (`notification_jobs`), mais
 * primitiva que a fila genérica `jobs`: sem `SKIP LOCKED` (dois workers podiam
 * pegar o mesmo envio) e — o que mais doía — **fora do alerta do OBS-01**. Ou
 * seja: a fila mais importante para o cliente era a única não vigiada. Um
 * lembrete de fatura que morresse não avisava ninguém.
 *
 * Agora o envio é um job comum na fila `jobs`: mesma reserva concorrente, mesmo
 * backoff, mesmo `max_attempts` — e, ao esgotar as tentativas, cai no alerta.
 * A tabela `notification_jobs` permanece como **registro de entrega** (o que foi
 * enviado, para quem, por qual canal, com que resultado), que alimenta o OBS-02.
 *
 * Instanciado com `new SendNotificationJob()` (sem DI) — resolve o que precisa
 * pelo Container, como os demais jobs.
 */
class SendNotificationJob
{
    public function handle(array $data): void
    {
        $deliveryId = (int) ($data['notification_id'] ?? 0);
        if ($deliveryId <= 0) {
            return;
        }

        $repo     = new NotificationRepository();
        $delivery = $repo->findDelivery($deliveryId);

        // Já enviado (retry duplicado) ou registro sumiu: nada a fazer. Sem isto,
        // uma reexecução do job mandaria a mesma mensagem de novo pro cliente.
        if (!$delivery || ($delivery['status'] ?? '') === 'sent') {
            return;
        }

        /** @var NotificationService $notifications */
        $notifications = Container::getInstance()->make(NotificationService::class);

        $result = $notifications->deliver($delivery);

        if (!empty($result['success'])) {
            $repo->markJobSent($deliveryId);
            return;
        }

        $error = (string) ($result['error'] ?? 'Erro desconhecido no envio.');
        $repo->markJobFailed($deliveryId, $error);

        // Lança para o worker: ele aplica o backoff e, esgotadas as tentativas,
        // marca o job como `failed` — o que dispara o alerta do OBS-01.
        throw new RuntimeException("Falha ao enviar notificação #{$deliveryId}: {$error}");
    }
}
