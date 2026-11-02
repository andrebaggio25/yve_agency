<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function back(): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return Response::redirect($referer);
    }

    protected function withSuccess(string $message): static
    {
        flash('success', $message);
        return $this;
    }

    protected function withError(string $message): static
    {
        flash('error', $message);
        return $this;
    }

    /**
     * Falha que não é validação não vai para a tela (SEC-16): o usuário recebe
     * o texto de `$messageKey` e o detalhe técnico vai para o `error_log`.
     *
     * Antes os controllers do financeiro jogavam `$e->getMessage()` no flash,
     * então apagar fatura com pagamento mostrava
     * `SQLSTATE[23503] … violates foreign key constraint "payments_invoice_id_fkey"`
     * ao usuário — nome de tabela, de coluna e de constraint de brinde.
     * `ValidationException` é a exceção cuja mensagem **é** para a tela.
     */
    protected function withFailure(\Throwable $e, string $context, string $messageKey): static
    {
        if ($e instanceof ValidationException) {
            return $this->withError($e->getMessage());
        }

        error_log('[' . $context . '] ' . $e::class . ': ' . $e->getMessage());

        return $this->withError(t($messageKey));
    }

    protected function withErrors(array $errors): static
    {
        flash('errors', $errors);
        return $this;
    }

    protected function withInput(array $input): static
    {
        flash('old', $input);
        return $this;
    }
}
