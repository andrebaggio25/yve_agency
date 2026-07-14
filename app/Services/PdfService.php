<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Gera PDF de verdade (UX-04).
 *
 * Até aqui, "PDF" era uma **tela de impressão**: o cliente recebia um link e
 * precisava ele mesmo mandar imprimir e salvar como PDF. Para um cliente
 * pagante, isso é constrangedor — e impedia anexar a fatura no e-mail, que é
 * como cobrança circula de verdade.
 *
 * Reaproveita as views de impressão que já existiam (`faturas.print`,
 * `contratos.print`, `executive.client_report`): o layout continua sendo um só,
 * agora renderizado para PDF em vez de para a impressora do usuário.
 */
class PdfService
{
    /** Renderiza uma view do projeto como PDF e devolve os bytes. */
    public function fromView(string $template, array $data = [], string $paper = 'A4'): string
    {
        return $this->fromHtml(View::render($template, $data), $paper);
    }

    public function fromHtml(string $html, string $paper = 'A4'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);   // logo da agência vem por URL
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // acentuação correta em pt-BR

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Nome de arquivo seguro e reconhecível: "fatura-2026-001-cliente.pdf".
     * Sem acento e sem caractere que quebre o header Content-Disposition.
     */
    public function filename(string ...$parts): string
    {
        // Mapa explícito em vez de iconv//TRANSLIT: a transliteração do iconv é
        // dependente de plataforma (no macOS "ó" vira "'o", produzindo
        // "relat-orio"). Aqui o resultado é o mesmo em qualquer servidor.
        $slug = static function (string $s): string {
            $from = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
            $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

            $ascii = str_replace($from, $to, mb_strtolower($s, 'UTF-8'));
            $clean = (string) preg_replace('/[^a-z0-9]+/', '-', $ascii);

            return trim($clean, '-');
        };

        $name = implode('-', array_filter(array_map($slug, $parts)));

        return ($name ?: 'documento') . '.pdf';
    }
}
