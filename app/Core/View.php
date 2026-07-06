<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    private static string  $basePath = '';
    private static ?string $layout   = null;

    /** @var array<string, string> */
    private static array $sections = [];

    /** @var string[] */
    private static array $sectionStack = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public static function render(string $view, array $data = []): string
    {
        self::$layout       = null;
        self::$sections     = [];
        self::$sectionStack = [];

        // Render the view file; sections are captured inside it
        $defaultContent = self::include($view, $data);

        // Nota: self::include() executa o arquivo de view, que pode chamar
        // View::start/stop/layout e mutar $sections/$layout. PHPStan não rastreia
        // esse efeito colateral (include de arquivo), então lemos via acessores
        // tipados para preservar a análise correta.

        // If the view didn't define a 'content' section but echoed directly,
        // treat that output as the content section
        if (!isset(self::sections()['content']) && $defaultContent !== '') {
            self::$sections['content'] = $defaultContent;
        }

        if (self::currentLayout() !== null) {
            return self::include('layouts/' . self::currentLayout(), $data);
        }

        return $defaultContent;
    }

    /** @return array<string,string> */
    private static function sections(): array
    {
        return self::$sections;
    }

    private static function currentLayout(): ?string
    {
        return self::$layout;
    }

    public static function partial(string $name, array $data = []): string
    {
        return self::include('partials/' . $name, $data);
    }

    // -------------------------------------------------------------------------
    // Layout / sections API (called from within view files)
    // -------------------------------------------------------------------------

    public static function layout(string $name): void
    {
        self::$layout = $name;
    }

    public static function start(string $section): void
    {
        self::$sectionStack[] = $section;
        ob_start();
    }

    public static function stop(): void
    {
        if (empty(self::$sectionStack)) {
            throw new RuntimeException('View::stop() called without a matching View::start().');
        }

        $section                  = array_pop(self::$sectionStack);
        self::$sections[$section] = ob_get_clean() ?: '';
    }

    public static function slot(string $section, string $default = ''): string
    {
        return self::$sections[$section] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private static function include(string $view, array $data): string
    {
        $path = self::$basePath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($path)) {
            throw new RuntimeException("View [{$view}] not found at [{$path}].");
        }

        // Extract data into scope; EXTR_SKIP prevents overwriting existing vars
        extract($data, EXTR_SKIP);

        ob_start();
        include $path;
        return ob_get_clean() ?: '';
    }
}
