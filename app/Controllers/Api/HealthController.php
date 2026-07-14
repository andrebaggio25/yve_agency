<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\HealthService;

/**
 * `/api/health` — para um monitor externo (UptimeRobot, Better Stack…).
 *
 * Antes devolvia `status: ok` fixo: dizia que o app está de pé, o que o próprio
 * HTTP 200 já dizia. Agora checa o que de fato quebra em silêncio — banco, cron
 * parado, jobs falhos, syncs congelados.
 *
 * **Público, mas mudo por padrão:** só `status` + HTTP 200/503. Um curioso não
 * aprende nada da infraestrutura (tamanho de fila, se o banco caiu, nomes de
 * conta). O **detalhe** só sai com o `QUEUE_SECRET` — o mesmo segredo dos crons.
 *
 * 503 em `error` é o que faz o monitor disparar. `degraded` responde 200 de
 * propósito: o app está servindo; disso cuida o alerta por e-mail, não um pager
 * às 3h da manhã.
 */
class HealthController extends Controller
{
    public function __construct(private readonly HealthService $health) {}

    public function index(Request $request): Response
    {
        $snapshot = $this->health->snapshot();
        $status   = $snapshot['status'];

        $body = ['status' => $status, 'time' => date('c')];

        if ($this->authorized($request)) {
            $body['app']    = env('APP_NAME', 'YVE Agency');
            $body['env']    = env('APP_ENV', 'production');
            $body['checks'] = $snapshot['checks'];
        }

        return Response::json($body, $status === 'error' ? 503 : 200);
    }

    private function authorized(Request $request): bool
    {
        $secret = (string) env('QUEUE_SECRET', '');
        $token  = (string) $request->query('token', '');

        return $secret !== '' && hash_equals($secret, $token);
    }
}
