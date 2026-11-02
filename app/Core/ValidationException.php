<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Erro de validação com mensagem **pronta para a tela** (SEC-16).
 *
 * Os controllers do financeiro jogavam `$e->getMessage()` no flash, qualquer
 * que fosse a exceção: apagar fatura com pagamento mostrava
 * `SQLSTATE[23503] … violates foreign key constraint` ao usuário. Agora só a
 * mensagem desta exceção vai para a tela (já traduzida por `t()`); o resto vira
 * texto amigável e o detalhe técnico fica no `error_log`.
 */
class ValidationException extends \RuntimeException
{
}
