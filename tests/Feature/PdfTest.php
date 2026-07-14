<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * UX-04 — PDF de verdade.
 *
 * Antes, "PDF" era uma tela de impressão: a cliente recebia um link e tinha de
 * imprimir→salvar como PDF na mão. Além de constrangedor para quem paga, isso
 * impedia **anexar a fatura no e-mail** — que é como cobrança circula.
 *
 * O teste verifica os bytes: um PDF começa com `%PDF-`. Se um dia alguém
 * quebrar a geração e a rota voltar a devolver HTML, isto pega.
 */
class PdfTest extends FeatureTestCase
{
    private function seedInvoice(int $agencyId, int $clientId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, due_date,
                                   subtotal, total, amount_paid, currency_code, created_at)
             VALUES (:a, :c, 'FAT-2026-001', 'Serviços de julho', 'sent', CURRENT_DATE + 10,
                     1000.00, 1000.00, 0, 'BRL', NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId]);

        return (int) $stmt->fetchColumn();
    }

    public function test_fatura_gera_pdf_de_verdade(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId, 'Padaria da Esquina');
        $invoice  = $this->seedInvoice($agencyId, (int) $client['id']);

        $this->actingAs($user['id'], permissions: ['invoices.view']);

        $response = $this->get('/faturas/' . $invoice . '/pdf');
        $headers  = $response->getHeaders();

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('application/pdf', $headers['Content-Type'] ?? null);

        // Os bytes têm de ser um PDF — não a tela de impressão de antes.
        $this->assertStringStartsWith('%PDF-', $response->getBody(), 'A resposta precisa ser um PDF real.');

        // Nome de arquivo reconhecível, sem acento nem caractere que quebre o header.
        $this->assertStringContainsString('fatura-fat-2026-001', $headers['Content-Disposition'] ?? '');
        $this->assertStringNotContainsString('Ã', $headers['Content-Disposition'] ?? '');
    }

    public function test_pdf_exige_permissao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $client   = $this->createClient($agencyId);
        $invoice  = $this->seedInvoice($agencyId, (int) $client['id']);

        $this->actingAs($user['id'], permissions: []);

        $this->assertSame(403, $this->get('/faturas/' . $invoice . '/pdf')->getStatus());
    }

    /** O nome do arquivo sai limpo mesmo com acento e pontuação no cliente. */
    public function test_nome_do_arquivo_e_seguro(): void
    {
        $pdf = new \App\Services\PdfService();

        $name = $pdf->filename('relatório', 'Ação & Cia. Ltda', '2026-07');

        $this->assertSame('relatorio-acao-cia-ltda-2026-07.pdf', $name);
        $this->assertDoesNotMatchRegularExpression('/["\r\n]/', $name);
    }
}
