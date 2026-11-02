<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

/**
 * Rate limit das rotas públicas do portal (SEC-18).
 *
 * O portal é autenticado só pelo capability-token da URL, que circula por
 * WhatsApp e e-mail: sem teto, um token vazado vale upload e escrita ilimitados
 * na conta Drive da agência. O `RateLimitMiddleware` do login não serve aqui —
 * 5 tentativas/60 s é apertado demais para o envio de arquivos, e a chave por
 * **path** daria uma cota a cada rota, sem nunca limitar o token como um todo.
 *
 * Daí as três escolhas desta variante:
 *   1. a cesta é por **IP + portal_token**, não por path: o teto vale para o
 *      token inteiro, em qualquer rota da mesma classe;
 *   2. só **mutação** conta. GET passa livre: a galeria pede o proxy
 *      `/drive/file/{id}/raw` uma vez por miniatura (dezenas de requisições em
 *      uma rolagem) e qualquer teto por requisição ali quebraria a tela em
 *      silêncio — a miniatura some no `onerror`. Ou seja, a **banda** do proxy
 *      segue sem cota: isso é limite de volume, não de taxa, e pede outra
 *      solução (cota por cliente/dia), fora deste item;
 *   3. o **upload** tem cesta separada e bem maior que as outras mutações: cada
 *      arquivo são 2 POSTs nossos (abrir a sessão resumável + confirmar), com 2
 *      envios em paralelo — ~4 req/s no pico de uma remessa grande. Os bytes
 *      (os PUTs em chunk) vão direto do navegador ao Google e nem passam aqui.
 */
final class PortalRateLimitMiddleware extends RateLimitMiddleware
{
    /** Métodos que contam. Leitura não conta (ver ponto 2 acima). */
    private const MUTATIONS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Envio de arquivos: ~4 req/s no pico → folga de 2,5× sobre o uso real. */
    private const UPLOAD_MAX = 600;

    /** Aprovar, feedback, pasta, renomear, excluir, mover: ritmo humano. */
    private const WRITE_MAX = 60;

    private const WINDOW = 60;

    /**
     * @return array{key:string,max:int,decay:int}|null
     */
    protected function bucket(Request $request): ?array
    {
        if (!in_array($request->method(), self::MUTATIONS, true)) {
            return null;
        }

        $token    = (string) $request->param('portal_token', '');
        $isUpload = str_contains($request->path(), '/drive/upload');

        return [
            'key'   => 'rate_limit_portal_' . ($isUpload ? 'upload' : 'write')
                       . '_' . md5($request->ip() . '|' . $token),
            'max'   => $isUpload ? self::UPLOAD_MAX : self::WRITE_MAX,
            'decay' => self::WINDOW,
        ];
    }

    /** O portal fala o idioma do cliente e não tem tela de login para voltar. */
    protected function tooManyRequests(Request $request, int $retryAfter): Response
    {
        if ($request->wantsJson()) {
            return Response::json([
                'error'       => t('portal.rate_limited.text'),
                'retry_after' => $retryAfter,
            ], 429);
        }

        return Response::view('portal.rate_limited', ['retry_after' => $retryAfter], 429);
    }
}
