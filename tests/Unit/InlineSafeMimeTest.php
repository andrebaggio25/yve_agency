<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * Com o upload liberado pra qualquer tipo, o proxy /raw só pode servir INLINE
 * mídia passiva — HTML/SVG inline no domínio do app seriam XSS armazenado
 * (arquivo vem do cliente do portal). Todo o resto força download.
 */
class InlineSafeMimeTest extends TestCase
{
    public function test_passive_media_is_inline(): void
    {
        $this->assertTrue(GoogleDriveService::inlineSafeMime('image/jpeg'));
        $this->assertTrue(GoogleDriveService::inlineSafeMime('image/png'));
        $this->assertTrue(GoogleDriveService::inlineSafeMime('video/mp4'));
        $this->assertTrue(GoogleDriveService::inlineSafeMime('audio/mpeg'));
        $this->assertTrue(GoogleDriveService::inlineSafeMime('application/pdf'));
        $this->assertTrue(GoogleDriveService::inlineSafeMime('IMAGE/JPEG')); // case-insensitive
    }

    public function test_active_or_unknown_content_forces_download(): void
    {
        $this->assertFalse(GoogleDriveService::inlineSafeMime('text/html'));
        $this->assertFalse(GoogleDriveService::inlineSafeMime('image/svg+xml')); // SVG executa script
        $this->assertFalse(GoogleDriveService::inlineSafeMime('application/xhtml+xml'));
        $this->assertFalse(GoogleDriveService::inlineSafeMime('application/javascript'));
        $this->assertFalse(GoogleDriveService::inlineSafeMime('application/zip'));
        $this->assertFalse(GoogleDriveService::inlineSafeMime('application/octet-stream'));
        $this->assertFalse(GoogleDriveService::inlineSafeMime(''));
        $this->assertFalse(GoogleDriveService::inlineSafeMime(null));
    }
}
