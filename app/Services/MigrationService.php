<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Roda as migrations do Phinx de dentro da aplicação (in-process), sem depender
 * de acesso a CLI/`exec` — essencial em hospedagem compartilhada (ex.: Hostinger).
 *
 * O estado (executadas x pendentes) é lido direto da tabela `phinxlog` + a pasta
 * de migrations; a execução usa a API programática do Phinx (Manager).
 */
class MigrationService
{
    /**
     * Lista todas as migrations com o estado atual.
     * @return array{env:string, migrations: list<array{version:string,name:string,executed:bool,executed_at:?string}>, pending:int}
     */
    public function status(): array
    {
        $env      = $this->environment();
        $executed = $this->executedVersions();
        $files    = glob(base_path('database/migrations') . '/*.php') ?: [];

        $migrations = [];
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if (!preg_match('/^(\d+)_(.+)$/', $base, $m)) {
                continue;
            }
            $version = $m[1];
            $migrations[] = [
                'version'     => $version,
                'name'        => str_replace('_', ' ', $m[2]),
                'executed'    => isset($executed[$version]),
                'executed_at' => $executed[$version] ?? null,
            ];
        }

        usort($migrations, fn($a, $b) => strcmp($a['version'], $b['version']));
        $pending = count(array_filter($migrations, fn($x) => !$x['executed']));

        return ['env' => $env, 'migrations' => $migrations, 'pending' => $pending];
    }

    /**
     * Executa todas as migrations pendentes.
     * @return array{success:bool, log:string, error:?string}
     */
    public function migrate(): array
    {
        @set_time_limit(0);
        $output = new BufferedOutput();

        try {
            [$manager, $env] = $this->manager($output);
            $manager->migrate($env);
            return ['success' => true, 'log' => trim($output->fetch()), 'error' => null];
        } catch (Throwable $e) {
            return ['success' => false, 'log' => trim($output->fetch()), 'error' => $e->getMessage()];
        }
    }

    /**
     * Reverte a última migration aplicada (rollback de 1 passo).
     * @return array{success:bool, log:string, error:?string}
     */
    public function rollback(): array
    {
        @set_time_limit(0);
        $output = new BufferedOutput();

        try {
            [$manager, $env] = $this->manager($output);
            $manager->rollback($env);
            return ['success' => true, 'log' => trim($output->fetch()), 'error' => null];
        } catch (Throwable $e) {
            return ['success' => false, 'log' => trim($output->fetch()), 'error' => $e->getMessage()];
        }
    }

    // ── internos ────────────────────────────────────────────────────────────────

    /** @return array{0:Manager,1:string} */
    private function manager(BufferedOutput $output): array
    {
        $config  = Config::fromPhp(base_path('phinx.php'));
        $manager = new Manager($config, new StringInput(''), $output);
        return [$manager, $this->environment()];
    }

    private function environment(): string
    {
        // Mesmo critério do phinx.php: casa com APP_ENV (development/production).
        return Config::fromPhp(base_path('phinx.php'))->getDefaultEnvironment();
    }

    /** @return array<string,?string> version => end_time */
    private function executedVersions(): array
    {
        try {
            $pdo  = Database::connection();
            $rows = $pdo->query('SELECT version, end_time FROM phinxlog ORDER BY version')
                        ->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r['version']] = $r['end_time'] ?? null;
            }
            return $out;
        } catch (Throwable) {
            // phinxlog ainda não existe (nenhuma migration rodou) → tudo pendente.
            return [];
        }
    }
}
