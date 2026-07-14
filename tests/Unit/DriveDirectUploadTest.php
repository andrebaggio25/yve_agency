<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GoogleDriveApiService;
use PHPUnit\Framework\TestCase;

/**
 * UP-01: upload direto browser→Drive. Cobre os dois guardas puros do fluxo:
 * - metaHasParent: o "complete" só registra arquivo que está na pasta esperada
 *   do cliente (não confia no drive_file_id vindo do navegador);
 * - originFromUrl: a sessão resumável é vinculada à origem do app (CORS) — uma
 *   APP_URL inválida precisa virar null para o front cair no fallback relay.
 */
class DriveDirectUploadTest extends TestCase
{
    public function test_meta_has_parent_accepts_direct_parent(): void
    {
        $meta = ['id' => 'F1', 'parents' => ['ROOT', 'PASTA_DO_CLIENTE']];
        $this->assertTrue(GoogleDriveApiService::metaHasParent($meta, 'PASTA_DO_CLIENTE'));
    }

    public function test_meta_has_parent_rejects_other_folder_and_missing_parents(): void
    {
        $this->assertFalse(GoogleDriveApiService::metaHasParent(['id' => 'F1', 'parents' => ['OUTRA']], 'PASTA_DO_CLIENTE'));
        $this->assertFalse(GoogleDriveApiService::metaHasParent(['id' => 'F1'], 'PASTA_DO_CLIENTE'));
        $this->assertFalse(GoogleDriveApiService::metaHasParent([], 'PASTA_DO_CLIENTE'));
    }

    public function test_meta_has_parent_requires_exact_match(): void
    {
        // Comparação estrita: prefixo/tipo diferente não passa.
        $this->assertFalse(GoogleDriveApiService::metaHasParent(['parents' => ['PASTA_DO_CLIENTE_2']], 'PASTA_DO_CLIENTE'));
        $this->assertFalse(GoogleDriveApiService::metaHasParent(['parents' => [123]], '123'));
    }

    public function test_origin_from_url_extracts_scheme_host_and_port(): void
    {
        $this->assertSame('https://app.yve.com.br', GoogleDriveApiService::originFromUrl('https://app.yve.com.br'));
        $this->assertSame('https://app.yve.com.br', GoogleDriveApiService::originFromUrl('https://app.yve.com.br/subpasta?x=1'));
        $this->assertSame('http://localhost:8000', GoogleDriveApiService::originFromUrl('http://localhost:8000'));
    }

    public function test_origin_from_url_rejects_invalid_input(): void
    {
        $this->assertNull(GoogleDriveApiService::originFromUrl(null));
        $this->assertNull(GoogleDriveApiService::originFromUrl(''));
        $this->assertNull(GoogleDriveApiService::originFromUrl('   '));
        $this->assertNull(GoogleDriveApiService::originFromUrl('sem-esquema.com'));
    }
}
