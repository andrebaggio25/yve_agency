<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Camada fina sobre {@see Crypto} para guardar tokens de integração cifrados
 * em repouso, com tolerância a valores legados em texto puro.
 *
 * - encrypt(): null-safe; string vazia/null passa direto (preserva a lógica de
 *   COALESCE/NULLIF dos repositórios que tratam '' como "não mudar").
 * - decrypt(): tenta descriptografar; se o valor não for um ciphertext válido
 *   (registro antigo gravado antes da cifragem), devolve o valor original.
 *   O MAC Poly1305 do libsodium garante que texto puro nunca seja confundido
 *   com um ciphertext válido.
 */
class Secret
{
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return Crypto::encrypt($value);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypto::decrypt($value);
        } catch (Throwable) {
            // Valor legado em texto puro — devolve como está.
            return $value;
        }
    }

    /** Indica se o valor já está cifrado (decodifica e passa no MAC). */
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        try {
            Crypto::decrypt($value);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
