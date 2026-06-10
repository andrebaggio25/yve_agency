<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Symmetric encryption for storing sensitive secrets (API tokens, etc.)
 * Uses libsodium (XSalsa20-Poly1305) — bundled with PHP 7.2+.
 */
class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        $key   = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $key  = self::key();
        $raw  = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted value.');
        }

        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        if ($plain === false) {
            throw new RuntimeException('Decryption failed — key mismatch or tampered data.');
        }

        return $plain;
    }

    private static function key(): string
    {
        $appKey = env('APP_KEY', '');

        if (strlen($appKey) < 32) {
            throw new RuntimeException(
                'APP_KEY must be at least 32 characters. Run: php -r "echo base64_encode(random_bytes(32));"'
            );
        }

        // Derive a 32-byte key from APP_KEY using SHA-256
        return substr(hash('sha256', $appKey, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
