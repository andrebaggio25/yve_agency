<?php

declare(strict_types=1);

namespace App\Services;

class GoogleDriveService
{
    private const DRIVE_PATTERNS = [
        'file'   => '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/i',
        'folder' => '/drive\.google\.com\/drive\/folders\/([a-zA-Z0-9_-]+)/i',
        'docs'   => '/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/i',
        'sheets' => '/docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/i',
        'slides' => '/docs\.google\.com\/presentation\/d\/([a-zA-Z0-9_-]+)/i',
        'forms'  => '/docs\.google\.com\/forms\/d\/([a-zA-Z0-9_-]+)/i',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
    private const VIDEO_EXTENSIONS  = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv'];

    /**
     * Converte uma URL do Google Drive numa URL que funciona em <img>.
     * O Google descontinuou `uc?export=view` para imagens embutidas; o endpoint
     * `thumbnail` serve a imagem (para arquivos com link público). URLs que não
     * são do Drive (link direto de imagem) passam direto, sem alteração.
     */
    public static function imageSrc(?string $url, int $size = 1600): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $id = self::extractFileId($url);
        if ($id !== null) {
            return "https://drive.google.com/thumbnail?id={$id}&sz=w{$size}";
        }

        return $url;
    }

    /** Extrai o file_id de qualquer forma comum de link do Drive; null se não for Drive. */
    public static function extractFileId(?string $url): ?string
    {
        $url = (string) $url;
        if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    public function parse(string $url): array
    {
        $url = trim($url);

        foreach (self::DRIVE_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $url, $m)) {
                $fileId   = $m[1];
                $fileType = $this->detectFileType($url, $type);
                return [
                    'valid'       => true,
                    'file_id'     => $fileId,
                    'file_type'   => $fileType,
                    'embed_url'   => $this->buildEmbedUrl($fileId, $fileType, $type),
                    'preview_url' => $this->buildPreviewUrl($fileId, $fileType),
                    'thumb_url'   => $this->buildThumbUrl($fileId, $fileType),
                    'original'    => $url,
                    'drive_type'  => $type,
                ];
            }
        }

        return ['valid' => false, 'original' => $url];
    }

    private function detectFileType(string $url, string $driveType): string
    {
        return match ($driveType) {
            'folder' => 'folder',
            'docs'   => 'document',
            'sheets' => 'spreadsheet',
            'slides' => 'presentation',
            'forms'  => 'form',
            default  => $this->detectFromExtension($url),
        };
    }

    private function detectFromExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) return 'image';
        if (in_array($ext, self::VIDEO_EXTENSIONS,  true)) return 'video';
        if ($ext === 'pdf')                               return 'pdf';
        return 'file';
    }

    private function buildEmbedUrl(string $fileId, string $fileType, string $driveType): string
    {
        return match ($driveType) {
            'folder' => "https://drive.google.com/embeddedfolderview?id={$fileId}#list",
            'docs'   => "https://docs.google.com/document/d/{$fileId}/preview",
            'sheets' => "https://docs.google.com/spreadsheets/d/{$fileId}/preview",
            'slides' => "https://docs.google.com/presentation/d/{$fileId}/preview",
            default  => match ($fileType) {
                'image' => "https://drive.google.com/file/d/{$fileId}/preview",
                'video' => "https://drive.google.com/file/d/{$fileId}/preview",
                'pdf'   => "https://drive.google.com/file/d/{$fileId}/preview",
                default => "https://drive.google.com/file/d/{$fileId}/preview",
            },
        };
    }

    private function buildPreviewUrl(string $fileId, string $fileType): string
    {
        return "https://drive.google.com/file/d/{$fileId}/preview";
    }

    private function buildThumbUrl(string $fileId, string $fileType): string
    {
        if (in_array($fileType, ['image', 'video', 'pdf'], true)) {
            return "https://drive.google.com/thumbnail?id={$fileId}&sz=w400";
        }
        return '';
    }

    public function renderEmbed(array $parsed, string $cssClass = ''): string
    {
        if (!$parsed['valid']) return '';

        $embedUrl = htmlspecialchars($parsed['embed_url'], ENT_QUOTES, 'UTF-8');
        $class    = htmlspecialchars($cssClass ?: 'w-full h-64 rounded-lg border-0', ENT_QUOTES, 'UTF-8');

        return match ($parsed['file_type']) {
            'video' => <<<HTML
                <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-gray-900">
                    <iframe src="{$embedUrl}" class="{$class} absolute inset-0 w-full h-full" allowfullscreen loading="lazy"></iframe>
                </div>
            HTML,
            'image' => <<<HTML
                <div class="relative rounded-xl overflow-hidden bg-gray-50">
                    <iframe src="{$embedUrl}" class="{$class}" loading="lazy"></iframe>
                </div>
            HTML,
            default => <<<HTML
                <div class="relative rounded-xl overflow-hidden bg-gray-50">
                    <iframe src="{$embedUrl}" class="{$class}" loading="lazy"></iframe>
                </div>
            HTML,
        };
    }

    public function getIcon(string $fileType): string
    {
        return match ($fileType) {
            'image'        => 'photo',
            'video'        => 'video-camera',
            'pdf'          => 'document-text',
            'document'     => 'document',
            'spreadsheet'  => 'table-cells',
            'presentation' => 'presentation-chart-bar',
            'folder'       => 'folder',
            default        => 'document',
        };
    }

    public function getTypeLabel(string $fileType): string
    {
        return match ($fileType) {
            'image'        => 'Imagem',
            'video'        => 'Vídeo',
            'pdf'          => 'PDF',
            'document'     => 'Documento',
            'spreadsheet'  => 'Planilha',
            'presentation' => 'Apresentação',
            'folder'       => 'Pasta',
            default        => 'Arquivo',
        };
    }

    public function getTypeColor(string $fileType): string
    {
        return match ($fileType) {
            'image'        => 'violet',
            'video'        => 'rose',
            'pdf'          => 'amber',
            'document'     => 'blue',
            'spreadsheet'  => 'emerald',
            'presentation' => 'orange',
            'folder'       => 'sky',
            default        => 'gray',
        };
    }
}
