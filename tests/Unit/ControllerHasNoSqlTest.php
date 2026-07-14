<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ARCH-01: invariante de arquitetura — **controller não tem SQL**.
 *
 * A auditoria encontrou SQL cru em 9 controllers (dashboard, relatórios, admin,
 * fila…). Depois de extrair tudo para repositórios/services, este teste impede
 * a regressão: um `Database::connection()` ou `->prepare(` novo em
 * `app/Controllers` quebra a suíte antes de virar dívida.
 */
class ControllerHasNoSqlTest extends TestCase
{
    /** Sinais de acesso direto ao banco a partir de um controller. */
    private const FORBIDDEN = [
        'Database::connection',
        '->prepare(',
        'PDO::',
        'PDO $pdo',
    ];

    public function test_no_controller_touches_the_database_directly(): void
    {
        $offenders = [];

        foreach ($this->controllerFiles() as $file) {
            $code = (string) file_get_contents($file);

            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($code, $needle)) {
                    $offenders[] = basename($file) . " → contém \"{$needle}\"";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Controller com SQL/PDO direto (use um Repository — ver skill yve-arquitetura):\n"
            . implode("\n", $offenders)
        );
    }

    public function test_the_scan_actually_sees_the_controllers(): void
    {
        // Guarda do próprio teste: se o glob quebrar, o teste acima passaria
        // vazio e a invariante deixaria de ser vigiada sem ninguém notar.
        $this->assertGreaterThan(20, count($this->controllerFiles()));
    }

    /** @return list<string> */
    private function controllerFiles(): array
    {
        $dir   = dirname(__DIR__, 2) . '/app/Controllers';
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }

        return $files;
    }
}
