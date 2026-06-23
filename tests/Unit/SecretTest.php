<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Secret;
use PHPUnit\Framework\TestCase;

class SecretTest extends TestCase
{
    public function test_encrypt_then_decrypt_round_trips(): void
    {
        $plain = 'EAAB-super-secret-meta-token-123';
        $cipher = Secret::encrypt($plain);

        $this->assertNotSame($plain, $cipher);
        $this->assertSame($plain, Secret::decrypt($cipher));
    }

    public function test_encrypt_is_non_deterministic(): void
    {
        // Nonce aleatório → dois ciphertexts diferentes para o mesmo plaintext.
        $this->assertNotSame(Secret::encrypt('abc'), Secret::encrypt('abc'));
    }

    public function test_null_and_empty_pass_through_untouched(): void
    {
        $this->assertNull(Secret::encrypt(null));
        $this->assertSame('', Secret::encrypt(''));
        $this->assertNull(Secret::decrypt(null));
        $this->assertSame('', Secret::decrypt(''));
    }

    public function test_decrypt_falls_back_to_legacy_plaintext(): void
    {
        // Valor antigo gravado antes da cifragem deve ser devolvido como está.
        $legacy = 'plaintext-legacy-token';
        $this->assertSame($legacy, Secret::decrypt($legacy));
    }

    public function test_is_encrypted_detects_ciphertext(): void
    {
        $this->assertTrue(Secret::isEncrypted(Secret::encrypt('x')));
        $this->assertFalse(Secret::isEncrypted('plaintext'));
        $this->assertFalse(Secret::isEncrypted(''));
        $this->assertFalse(Secret::isEncrypted(null));
    }
}
