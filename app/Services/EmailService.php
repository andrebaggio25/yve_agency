<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class EmailService
{
    public function send(string $to, string $toName, string $template, array $vars = [], string $locale = 'pt'): array
    {
        $subject = $this->renderSubject($template, $vars, $locale);
        $body    = $this->renderBody($template, $vars, $locale);

        if (!$subject || !$body) {
            return ['success' => false, 'error' => "Template '{$template}' not found."];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST', 'smtp.mailtrap.io');
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME', '');
            $mail->Password   = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(
                env('MAIL_FROM_ADDRESS', 'noreply@yveagency.com'),
                env('MAIL_FROM_NAME', 'YVE Agency')
            );
            $mail->addAddress($to, $toName);
            $mail->CharSet   = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject   = $subject;
            $mail->Body      = $body;
            $mail->AltBody   = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            return ['success' => true];
        } catch (MailerException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Template rendering ────────────────────────────────────────────────────

    private function renderSubject(string $template, array $vars, string $locale): ?string
    {
        $subjects = $this->subjects($locale);
        $subject  = $subjects[$template] ?? null;
        if (!$subject) return null;
        return $this->interpolate($subject, $vars);
    }

    private function renderBody(string $template, array $vars, string $locale): ?string
    {
        $viewPath = resource_path("views/emails/{$template}.php");
        if (!file_exists($viewPath)) return null;

        // Save and set locale for the template
        $prevLocale = Lang::getLocale();
        Lang::setLocale($locale);

        ob_start();
        extract($vars, EXTR_SKIP);
        include $viewPath;
        $html = ob_get_clean();

        Lang::setLocale($prevLocale);
        return $html ?: null;
    }

    private function interpolate(string $str, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $str = str_replace("::{$k}::", (string) $v, $str);
        }
        return $str;
    }

    public function sendWithAttachment(
        string $to,
        string $toName,
        string $template,
        array  $vars = [],
        string $locale = 'pt',
        array  $attachments = []
    ): array {
        $subject = $this->renderSubject($template, $vars, $locale);
        $body    = $this->renderBody($template, $vars, $locale);

        if (!$subject || !$body) {
            return ['success' => false, 'error' => "Template '{$template}' not found."];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST', 'smtp.mailtrap.io');
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME', '');
            $mail->Password   = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom(
                env('MAIL_FROM_ADDRESS', 'noreply@yveagency.com'),
                env('MAIL_FROM_NAME', 'YVE Agency')
            );
            $mail->addAddress($to, $toName);
            $mail->CharSet  = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $body;
            $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            foreach ($attachments as $att) {
                // ['path' => '/tmp/...', 'name' => 'fatura.pdf']
                if (!empty($att['path']) && file_exists($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? basename($att['path']));
                }
            }

            $mail->send();
            return ['success' => true];
        } catch (MailerException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function subjects(string $locale): array
    {
        return match($locale) {
            'en' => [
                'plan_sent_for_approval' => 'New content plan awaiting your approval — ::plan_title::',
                'plan_approved'          => 'Content plan approved ✓ — ::plan_title::',
                'plan_revision'          => 'Revision requested for — ::plan_title::',
                'invoice_sent'           => 'Invoice ::invoice_number:: — ::invoice_title::',
                'password_reset'         => 'Reset your password — ::app_name::',
                'task_assigned'          => 'New task assigned to you — ::task_title::',
                'health_alert'           => '[::app::] Alerta operacional — ação necessária',
            ],
            'es' => [
                'plan_sent_for_approval' => 'Nuevo plan de contenido esperando tu aprobación — ::plan_title::',
                'plan_approved'          => 'Plan de contenido aprobado ✓ — ::plan_title::',
                'plan_revision'          => 'Revisión solicitada para — ::plan_title::',
                'invoice_sent'           => 'Factura ::invoice_number:: — ::invoice_title::',
                'password_reset'         => 'Restablece tu contraseña — ::app_name::',
                'task_assigned'          => 'Nueva tarea asignada a ti — ::task_title::',
                'health_alert'           => '[::app::] Alerta operacional — ação necessária',
            ],
            default => [
                'plan_sent_for_approval' => 'Novo plano de conteúdo aguardando sua aprovação — ::plan_title::',
                'plan_approved'          => 'Plano de conteúdo aprovado ✓ — ::plan_title::',
                'plan_revision'          => 'Revisão solicitada para — ::plan_title::',
                'invoice_sent'           => 'Fatura ::invoice_number:: — ::invoice_title::',
                'password_reset'         => 'Redefinição de senha — ::app_name::',
                'task_assigned'          => 'Nova tarefa atribuída a você — ::task_title::',
                'health_alert'           => '[::app::] Alerta operacional — ação necessária',
            ],
        };
    }
}
