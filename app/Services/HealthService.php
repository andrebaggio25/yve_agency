<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\HealthRepository;
use App\Repositories\PlatformSettingsRepository;

/**
 * Saúde do sistema e alertas (OBS-01).
 *
 * O problema que resolve: hoje, se o cron para de rodar, se um job estoura as
 * tentativas ou se o sync de uma conta Meta quebra, **ninguém fica sabendo**.
 * O primeiro a descobrir é o cliente, olhando um relatório vazio.
 *
 * Aqui: (a) um retrato do estado para `/health` (monitor externo consome), e
 * (b) alerta por e-mail ao operador — com throttle, porque alerta que repete
 * a cada minuto vira ruído e é ignorado, que é o mesmo que não ter alerta.
 */
class HealthService
{
    /** Cron parado por mais que isto = degradado. */
    private const CRON_STALE_MINUTES = 30;

    /** Silêncio entre alertas do mesmo tipo (evita inundar a caixa). */
    private const ALERT_THROTTLE_MINUTES = 60;

    public function __construct(
        private readonly HealthRepository           $health,
        private readonly PlatformSettingsRepository $settings,
        private readonly EmailService               $mailer,
    ) {}

    /** Registra que um endpoint de cron rodou (heartbeat). */
    public function recordCronRun(string $name): void
    {
        $this->settings->set("cron_last_run_{$name}", date('c'));
    }

    /**
     * Retrato da saúde. `status`:
     *   ok       — tudo certo
     *   degraded — funciona, mas algo precisa de atenção (cron atrasado, job falho)
     *   error    — banco fora; o app não serve
     *
     * @return array{status:string,checks:array<string,mixed>}
     */
    public function snapshot(): array
    {
        $checks = [];

        // 1. Banco — sem ele nada funciona.
        try {
            Database::connection()->query('SELECT 1');
            $checks['database'] = ['ok' => true];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'checks' => ['database' => ['ok' => false, 'error' => 'unreachable']],
            ];
        }

        // 2. Cron — o coração das automações. Se parou, nada é enviado.
        $lastRun   = (string) ($this->settings->get('cron_last_run_work', '') ?? '');
        $cronAgeMin = $lastRun !== ''
            ? (int) floor((time() - strtotime($lastRun)) / 60)
            : null;
        $cronOk = $cronAgeMin !== null && $cronAgeMin <= self::CRON_STALE_MINUTES;

        $checks['cron'] = [
            'ok'              => $cronOk,
            'last_run'        => $lastRun ?: null,
            'minutes_ago'     => $cronAgeMin,
            'stale_threshold' => self::CRON_STALE_MINUTES,
        ];

        // 3. Filas.
        $queue  = $this->health->queueStats();
        $notify = $this->health->notificationQueueStats();

        $checks['queue']         = $queue + ['ok' => $queue['failed'] === 0];
        $checks['notifications'] = $notify + ['ok' => $notify['failed'] === 0];

        // 4. Syncs parados (não derruba a saúde: é aviso, não falha).
        $stale = $this->health->staleSyncAccounts();
        $checks['stale_syncs'] = ['ok' => count($stale) === 0, 'count' => count($stale)];

        $degraded = !$cronOk
            || $queue['failed'] > 0
            || $notify['failed'] > 0
            || count($stale) > 0;

        return [
            'status' => $degraded ? 'degraded' : 'ok',
            'checks' => $checks,
        ];
    }

    /**
     * Verifica e alerta o operador. Chamado pelo cron (não pelo /health, que
     * pode ser batido por um monitor a cada minuto).
     *
     * @return array{alerted:bool,reasons:list<string>}
     */
    public function checkAndAlert(): array
    {
        $reasons = [];

        $since  = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $failed = $this->health->recentlyFailedJobs($since);
        if ($failed) {
            $reasons[] = count($failed) . ' job(s) falharam definitivamente na última hora ('
                . implode(', ', array_unique(array_map(fn ($j) => (string) $j['queue'], $failed))) . ').';
        }

        $stale = $this->health->staleSyncAccounts();
        if ($stale) {
            $reasons[] = count($stale) . ' conta(s) sem sincronizar há mais de 48h.';
        }

        if (!$reasons || !$this->shouldAlert()) {
            return ['alerted' => false, 'reasons' => $reasons];
        }

        $to = (string) ($this->settings->get('alert_email', '') ?? '');
        if ($to === '') {
            // Sem destinatário configurado, ao menos deixa rastro no log.
            error_log('[health] ALERTA (sem alert_email configurado): ' . implode(' | ', $reasons));
            return ['alerted' => false, 'reasons' => $reasons];
        }

        $this->mailer->send($to, 'Operador', 'health_alert', [
            'app'     => env('APP_NAME', 'YVE Agency'),
            'reasons' => implode("\n", array_map(fn ($r) => "• {$r}", $reasons)),
            'time'    => date('d/m/Y H:i'),
        ]);

        $this->settings->set('alert_last_sent_at', date('c'));

        return ['alerted' => true, 'reasons' => $reasons];
    }

    /** Throttle: um alerta por hora, no máximo. */
    private function shouldAlert(): bool
    {
        $last = (string) ($this->settings->get('alert_last_sent_at', '') ?? '');
        if ($last === '') {
            return true;
        }

        return (time() - strtotime($last)) >= self::ALERT_THROTTLE_MINUTES * 60;
    }
}
