<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Aborta a requisição com uma Response pronta (401/403/404/…).
 *
 * Antes, os guards do `Auth` faziam `Response::…->send(); exit;` no meio do
 * pipeline. Funcionava, mas: (a) matava o processo, tornando impossível
 * testar autorização por HTTP (o `exit` derruba o próprio PHPUnit), e (b)
 * saltava fora do fluxo do Router, que é quem deveria decidir como responder.
 *
 * Agora o guard lança esta exceção e o `Router::handle()` a converte na
 * Response — mesmo resultado para o usuário, com o fluxo íntegro e testável.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly Response $response,
        string $message = '',
    ) {
        parent::__construct($message ?: 'HTTP ' . $response->getStatus());
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**403 — autenticado, mas sem permissão. */
    public static function forbidden(bool $json, array $viewData = []): self
    {
        return new self(
            $json
                ? Response::json(['error' => 'Forbidden'], 403)
                : Response::view('errors.403', $viewData, 403),
            'Forbidden'
        );
    }

    /** 401/redirect — não autenticado. */
    public static function unauthenticated(bool $json): self
    {
        return new self(
            $json
                ? Response::json(['error' => 'Unauthenticated'], 401)
                : Response::redirect('/login'),
            'Unauthenticated'
        );
    }
}
