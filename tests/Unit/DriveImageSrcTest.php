<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * DRIVE-01: URLs do Drive precisam virar o endpoint `thumbnail` para renderizar
 * em <img> (o `uc?export=view` foi descontinuado pelo Google). Links não-Drive
 * passam intactos.
 */
class DriveImageSrcTest extends TestCase
{
    public function test_converts_file_share_url_to_thumbnail(): void
    {
        $out = GoogleDriveService::imageSrc('https://drive.google.com/file/d/ABC123_x-y/view?usp=sharing');
        $this->assertSame('https://drive.google.com/thumbnail?id=ABC123_x-y&sz=w1600', $out);
    }

    public function test_converts_open_and_uc_id_urls(): void
    {
        $this->assertStringContainsString('thumbnail?id=FILEID', GoogleDriveService::imageSrc('https://drive.google.com/open?id=FILEID'));
        $this->assertStringContainsString('thumbnail?id=FILEID', GoogleDriveService::imageSrc('https://drive.google.com/uc?export=view&id=FILEID'));
    }

    public function test_respects_requested_size(): void
    {
        $out = GoogleDriveService::imageSrc('https://drive.google.com/file/d/ID/view', 400);
        $this->assertStringEndsWith('sz=w400', $out);
    }

    public function test_passes_through_non_drive_urls(): void
    {
        $direct = 'https://cdn.example.com/foto.jpg';
        $this->assertSame($direct, GoogleDriveService::imageSrc($direct));
    }

    public function test_empty_and_null_return_empty_string(): void
    {
        $this->assertSame('', GoogleDriveService::imageSrc(''));
        $this->assertSame('', GoogleDriveService::imageSrc(null));
    }

    public function test_extract_file_id(): void
    {
        $this->assertSame('ABC', GoogleDriveService::extractFileId('https://drive.google.com/file/d/ABC/view'));
        $this->assertNull(GoogleDriveService::extractFileId('https://example.com/x.png'));
    }
}
