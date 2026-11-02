<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\ContractRepository;
use App\Repositories\InvoiceRepository;

/**
 * SEC-16 — rigor no dinheiro (financeiro ponta a ponta).
 *
 * O módulo não tinha teste nenhum. O que ele aceitava: pagamento negativo (que
 * **baixava** o recebido da fatura), pagamento dez vezes maior que o total,
 * fatura nascendo `paid` com `amount_paid = 0`, desconto maior que o subtotal
 * (total negativo somando no painel) e "1.234,56" entrando como 1,23. Apagar
 * fatura com pagamento mostrava `SQLSTATE[23503]` na tela, e remover pagamento
 * redirecionava para o `Referer` — cabeçalho que vem do navegador.
 *
 * Toda negativa aqui tem a positiva ao lado: "nada gravado" só prova algo
 * quando a mesma rota, com o mesmo usuário, aceita o que é dele.
 */
class FinanceRigorTest extends FeatureTestCase
{
    /** Permissões do financeiro inteiro (o papel de administrador da agência). */
    private const PERM = [
        'dashboard.view', 'clients.view', 'clients.view_all',
        'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.send',
        'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete',
        'payments.view', 'payments.create', 'payments.delete',
    ];

    protected function tearDown(): void
    {
        // O Referer é de um caso só; vazar para o próximo teste faria o
        // redirect dele provar o contrário do que diz.
        unset($_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    // ── Fábricas ──────────────────────────────────────────────────────────────

    private function criarFatura(
        int $agencyId,
        int $clientId,
        string $number,
        string $total = '1000.00',
        string $status = 'sent',
        string $dueDate = 'CURRENT_DATE + 10',
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices (agency_id, client_id, invoice_number, title, status, due_date,
                                   subtotal, total, amount_paid, currency_code, created_at, updated_at)
             VALUES (:a, :c, :n, 'Serviços do mês', :s, {$dueDate}, :t, :t2, 0, 'BRL', NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([
            ':a'  => $agencyId,
            ':c'  => $clientId,
            ':n'  => $number,
            ':s'  => $status,
            ':t'  => $total,
            ':t2' => $total,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function criarPagamento(int $agencyId, int $invoiceId, string $amount): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments (agency_id, invoice_id, amount, payment_method, payment_date, created_at, updated_at)
             VALUES (:a, :i, :v, 'pix', CURRENT_DATE, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':i' => $invoiceId, ':v' => $amount]);
        $this->pdo->prepare(
            'UPDATE invoices SET amount_paid = (SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = :i2)
             WHERE id = :i'
        )->execute([':i' => $invoiceId, ':i2' => $invoiceId]);

        return (int) $stmt->fetchColumn();
    }

    private function criarContrato(int $agencyId, int $clientId, string $title, string $status = 'active'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contracts (agency_id, client_id, title, value, currency_code, status, start_date, created_at, updated_at)
             VALUES (:a, :c, :t, 1000.00, 'BRL', :s, CURRENT_DATE, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':t' => $title, ':s' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,string> */
    private function dadosFatura(int $clientId, array $extra = []): array
    {
        return array_merge([
            'client_id'     => (string) $clientId,
            'contract_id'   => '',
            'title'         => 'Serviços do mês',
            'status'        => 'draft',
            'due_date'      => date('Y-m-d', strtotime('+10 days')),
            'currency_code' => 'BRL',
            'discount'      => '0',
            'tax'           => '0',
            'notes'         => '',
            'items'         => [
                ['description' => 'Gestão de redes', 'quantity' => '2', 'unit_price' => '1.234,56'],
            ],
        ], $extra);
    }

    /** @return array<string,string> */
    private function dadosContrato(int $clientId, array $extra = []): array
    {
        return array_merge([
            'client_id'     => (string) $clientId,
            'title'         => 'Contrato mensal',
            'description'   => '',
            'value'         => '1.234,56',
            'currency_code' => 'BRL',
            'status'        => 'draft',
            'start_date'    => date('Y-m-d'),
            'end_date'      => '',
            'signed_at'     => '',
            'recurrence'    => '',
            'notes'         => '',
        ], $extra);
    }

    // ── Leituras ──────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function fatura(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function contarFaturas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
    }

    private function contarItens(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM invoice_items')->fetchColumn();
    }

    private function contarPagamentos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    }

    private function contarContratos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn();
    }

    private function erroNoFlash(): string
    {
        return (string) ($_SESSION['flash']['error'] ?? '');
    }

    /** O flash sobrevive à requisição: sem limpar, a negativa passa com o erro da anterior. */
    private function limparFlash(): void
    {
        unset($_SESSION['flash']);
    }

    // ── 1. Fatura: faixa, obrigatoriedade e allowlist de situação ─────────────

    public function test_fatura_le_o_valor_com_separador_de_milhar(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // 2 × 1.234,56 = 2.469,12, menos 1.000,00 de desconto.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['discount' => '1.000,00']));

        $this->assertSame(1, $this->contarFaturas(), 'A fatura do próprio cliente precisa ser criada.');
        $fatura = $this->fatura((int) $this->pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn());

        $this->assertSame('2469.12', $fatura['subtotal']);
        $this->assertSame('1000.00', $fatura['discount'], 'Desconto "1.000,00" entrava como 1,23 no cast antigo.');
        $this->assertSame('1469.12', $fatura['total']);
        $this->assertSame('draft', $fatura['status']);
    }

    public function test_fatura_nao_nasce_paga_nem_sem_titulo(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // Situação derivada no POST cru: fatura "paga" com amount_paid = 0.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['status' => 'paid']));
        $this->assertSame(0, $this->contarFaturas());
        $this->assertStringContainsString('Situação inválida', $this->erroNoFlash());
        $this->limparFlash();

        // Situação inventada.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['status' => 'hackeada']));
        $this->assertSame(0, $this->contarFaturas());
        $this->limparFlash();

        // Título em branco gravava fatura sem nome na lista.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['title' => '   ']));
        $this->assertSame(0, $this->contarFaturas());
        $this->assertStringContainsString('título', $this->erroNoFlash());
        $this->limparFlash();

        // Positiva: os mesmos dados com situação e título válidos passam.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['status' => 'sent']));
        $this->assertSame(1, $this->contarFaturas());
    }

    public function test_desconto_maior_que_o_subtotal_e_recusado_antes_de_gravar(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], [
            'items'    => [['description' => 'Consultoria', 'quantity' => '1', 'unit_price' => '100,00']],
            'discount' => '150,00',
        ]));

        $this->assertSame(0, $this->contarFaturas(), 'Fatura de total negativo não pode ser gravada.');
        $this->assertSame(0, $this->contarItens(), 'Nem fatura órfã sem item: a recusa vem antes do INSERT.');
        $this->assertStringContainsString('desconto', mb_strtolower($this->erroNoFlash()));
        $this->limparFlash();

        // Positiva: desconto até o subtotal passa e o total não fica negativo.
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], [
            'items'    => [['description' => 'Consultoria', 'quantity' => '1', 'unit_price' => '100,00']],
            'discount' => '100,00',
        ]));
        $this->assertSame(1, $this->contarFaturas());
        $fatura = $this->fatura((int) $this->pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn());
        $this->assertSame('0.00', $fatura['total']);
    }

    public function test_editar_fatura_paga_mantem_a_situacao_no_seletor(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $fatura   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-700', '1000.00', 'paid');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // A opção "Paga" precisa existir e vir marcada: sem ela o navegador
        // marca "Rascunho" e salvar só o título devolve a fatura paga para
        // rascunho, calado.
        $tela = $this->get("/faturas/{$fatura}/editar");
        $this->assertSame(200, $tela->getStatus());
        $this->assertMatchesRegularExpression('/<option value="paid"\s+selected/', $tela->getBody());

        // Positiva: salvar preservando a situação atual funciona.
        $dados = $this->dadosFatura((int) $cliente['id'], [
            'status'  => 'paid',
            'title'   => 'Fatura Corrigida',
            '_method' => 'PUT',
        ]);
        $this->post("/faturas/{$fatura}", $dados);
        $this->assertSame('paid', $this->fatura($fatura)['status']);
        $this->assertSame('Fatura Corrigida', $this->fatura($fatura)['title']);

        // Negativa: situação derivada que não é a da fatura não entra pelo POST.
        $this->limparFlash();
        $this->post("/faturas/{$fatura}", $this->dadosFatura((int) $cliente['id'], [
            'status'  => 'overdue',
            'title'   => 'Forçada',
            '_method' => 'PUT',
        ]));
        $this->assertSame('paid', $this->fatura($fatura)['status']);
        $this->assertSame('Fatura Corrigida', $this->fatura($fatura)['title']);
        $this->assertStringContainsString('Situação inválida', $this->erroNoFlash());
    }

    public function test_descricao_de_item_longa_e_recusada_sem_destruir_os_itens_salvos(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $tresItens = [
            ['description' => 'Gestão de redes',  'quantity' => '1', 'unit_price' => '1.000,00'],
            ['description' => 'Tráfego pago',     'quantity' => '1', 'unit_price' => '1.000,00'],
            ['description' => 'Relatório mensal',  'quantity' => '1', 'unit_price' => '469,12'],
        ];
        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], ['status' => 'sent', 'items' => $tresItens]));
        $id = (int) $this->pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn();
        $this->assertSame(3, $this->contarItens());
        $this->assertSame('2469.12', $this->fatura($id)['total']);
        $this->limparFlash();

        // `invoice_items.description` é varchar(500) e o campo não tinha limite:
        // 600 caracteres estouravam o INSERT **no meio** do laço de itens, já
        // depois do DELETE dos antigos — e `recalcTotals`, que é a última linha,
        // nunca chegava a rodar.
        $this->post("/faturas/{$id}", $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => 'sent',
            'items'   => [['description' => str_repeat('a', 600), 'quantity' => '1', 'unit_price' => '1.000,00']],
        ]));

        $this->assertStringContainsString('500', $this->erroNoFlash());
        $this->assertSame(3, $this->contarItens(), 'POST recusado não pode apagar os itens já gravados.');
        $this->assertSame('2469.12', $this->fatura($id)['total'], 'Nem deixar o total em zero.');
        $this->limparFlash();

        // Positiva: 500 caracteres exatos passam, e a edição grava de verdade.
        $this->post("/faturas/{$id}", $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => 'sent',
            'items'   => [['description' => str_repeat('a', 500), 'quantity' => '1', 'unit_price' => '1.000,00']],
        ]));
        $this->assertSame(1, $this->contarItens());
        $this->assertSame('1000.00', $this->fatura($id)['total']);
    }

    public function test_erro_de_banco_no_meio_dos_itens_nao_deixa_a_fatura_sem_item(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->post('/faturas', $this->dadosFatura((int) $cliente['id'], [
            'status' => 'sent',
            'items'  => [
                ['description' => 'Gestão de redes', 'quantity' => '1', 'unit_price' => '1.000,00'],
                ['description' => 'Tráfego pago',    'quantity' => '1', 'unit_price' => '1.000,00'],
            ],
        ]));
        $id = (int) $this->pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn();
        $this->assertSame(2, $this->contarItens());
        $this->limparFlash();

        // Validação nenhuma pega tudo: um byte inválido em UTF-8 passa pelo
        // serviço e o Postgres recusa o parâmetro (SQLSTATE 22021) no segundo
        // item — depois do DELETE e do primeiro INSERT. Sem transação a fatura
        // ficava com 1 item e total zerado, sem volta pela interface.
        $this->post("/faturas/{$id}", $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => 'sent',
            'items'   => [
                ['description' => 'Primeiro item',            'quantity' => '1', 'unit_price' => '500,00'],
                ['description' => "Segundo \xC3\x28 item",    'quantity' => '1', 'unit_price' => '500,00'],
            ],
        ]));

        $this->assertSame(2, $this->contarItens(), 'A transação desfaz o DELETE e o item já inserido.');
        $this->assertSame('2000.00', $this->fatura($id)['total']);
        $this->assertSame(
            'Gestão de redes',
            (string) $this->pdo->query('SELECT description FROM invoice_items ORDER BY sort_order, id LIMIT 1')->fetchColumn(),
        );
        $this->assertStringNotContainsString('SQLSTATE', $this->erroNoFlash());
    }

    public function test_editar_o_total_reavalia_a_situacao_da_fatura_paga(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $fatura   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-710', '1000.00', 'paid');
        $this->criarPagamento($agencyId, $fatura, '1000.00');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // Fatura paga que ganha item: a situação é derivada, mas o `update` não
        // reavaliava — ficava "Paga" com R$ 1.000 em aberto, e o devido some do
        // `pending_total` do painel (só soma sent|overdue|partial).
        $this->post("/faturas/{$fatura}", $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => 'paid',          // é o que o navegador reenvia (a opção vem marcada)
            'items'   => [
                ['description' => 'Serviço original', 'quantity' => '1', 'unit_price' => '1.000,00'],
                ['description' => 'Serviço extra',    'quantity' => '1', 'unit_price' => '1.000,00'],
            ],
        ]));

        $atual = $this->fatura($fatura);
        $this->assertSame('2000.00', $atual['total'], 'A edição precisa ter gravado o item novo.');
        $this->assertSame('1000.00', $atual['amount_paid']);
        $this->assertSame('partial', $atual['status'], 'Com saldo em aberto a fatura não pode seguir paga.');

        // E no sentido inverso: voltar o total ao que foi pago fecha a fatura
        // (antes ela ficava presa em `partial`, sem caminho pela interface).
        $this->post("/faturas/{$fatura}", $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => 'partial',
            'items'   => [['description' => 'Serviço original', 'quantity' => '1', 'unit_price' => '1.000,00']],
        ]));

        $atual = $this->fatura($fatura);
        $this->assertSame('1000.00', $atual['total']);
        $this->assertSame('paid', $atual['status']);
    }

    public function test_fatura_com_pagamento_pode_ser_cancelada_e_nao_ressuscita(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $fatura   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-720', '1000.00', 'partial');
        $this->criarPagamento($agencyId, $fatura, '400.00');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $dados = fn (string $status): array => $this->dadosFatura((int) $cliente['id'], [
            '_method' => 'PUT',
            'status'  => $status,
            'items'   => [['description' => 'Serviços do mês', 'quantity' => '1', 'unit_price' => '1.000,00']],
        ]);

        // Cancelar cobrança com sinal pago é decisão de quem cobra, não situação
        // derivada: a re-derivação lia 400 < 1.000 e gravava `partial` de volta
        // na linha seguinte — a tela dizia "Fatura atualizada." e não havia
        // caminho nenhum no app para cancelar fatura com pagamento.
        $this->post("/faturas/{$fatura}", $dados('cancelled'));

        $atual = $this->fatura($fatura);
        $this->assertSame('cancelled', $atual['status']);
        $this->assertSame('400.00', $atual['amount_paid'], 'Cancelar não apaga o que já foi recebido.');
        $this->assertSame('', $this->erroNoFlash());

        // Salvar de novo (o `<select>` reenvia `cancelled`) não a ressuscita: a
        // fatura cancelada fica fora do faturado, do recebido e do pendente.
        $this->post("/faturas/{$fatura}", $dados('cancelled'));
        $this->assertSame('cancelled', $this->fatura($fatura)['status']);

        $resumo = (new InvoiceRepository())->summaryByAgency($agencyId);
        $this->assertSame(0.0, (float) $resumo['billed_total']);
        $this->assertSame(0.0, (float) $resumo['received_total']);
        $this->assertSame(0.0, (float) $resumo['pending_total']);

        // Positiva: reabrir na edição — é o que a recusa do pagamento manda
        // fazer — volta a derivar a situação do saldo.
        $this->post("/faturas/{$fatura}", $dados('sent'));
        $this->assertSame('partial', $this->fatura($fatura)['status']);
    }

    // ── 2. Pagamento: faixa, saldo e forma ────────────────────────────────────

    public function test_pagamento_recusa_negativo_zero_acima_do_saldo_e_forma_inventada(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $fatura   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-800', '1000.00');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $base = ['invoice_id' => (string) $fatura, 'payment_method' => 'pix', 'payment_date' => date('Y-m-d')];

        // Negativo: baixava o amount_paid da fatura.
        $this->post('/pagamentos', $base + ['amount' => '-500,00']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertStringContainsString('maior que zero', $this->erroNoFlash());
        $this->limparFlash();

        // Zero.
        $this->post('/pagamentos', $base + ['amount' => '0']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->limparFlash();

        // Acima do total da fatura.
        $this->post('/pagamentos', $base + ['amount' => '10000,00']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertStringContainsString('saldo da fatura', $this->erroNoFlash());
        $this->limparFlash();

        // Forma de pagamento fora do catálogo (a coluna é varchar livre).
        $this->post('/pagamentos', ['invoice_id' => (string) $fatura, 'amount' => '100,00', 'payment_method' => 'maquininha']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertStringContainsString('Forma de pagamento', $this->erroNoFlash());
        $this->limparFlash();

        // Positiva: valor dentro do saldo entra e a fatura vira parcial.
        $this->post('/pagamentos', $base + ['amount' => '400,00']);
        $this->assertSame(1, $this->contarPagamentos());
        $this->assertSame('400.00', $this->fatura($fatura)['amount_paid']);
        $this->assertSame('partial', $this->fatura($fatura)['status']);

        // E o resto do saldo também — o teto é o saldo, não o total.
        $this->post('/pagamentos', $base + ['amount' => '600,01']);
        $this->assertSame(1, $this->contarPagamentos(), 'Um centavo acima do saldo ainda é acima do saldo.');
        $this->limparFlash();
        $this->post('/pagamentos', $base + ['amount' => '600,00']);
        $this->assertSame(2, $this->contarPagamentos());
        $this->assertSame('paid', $this->fatura($fatura)['status']);
    }

    public function test_tres_parcelas_de_33_33_fecham_a_fatura_de_99_99(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $fatura   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-810', '99.99');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $base = ['invoice_id' => (string) $fatura, 'payment_method' => 'pix'];
        foreach (['33,33', '33,33', '33,33'] as $valor) {
            $this->post('/pagamentos', $base + ['amount' => $valor]);
        }

        // As três parcelas exatas precisam entrar e fechar a fatura: o saldo é
        // arredondado a 2 casas antes de comparar, senão a última parcela
        // tropeçaria na cauda do float e seria recusada como "acima do saldo".
        $this->assertSame(3, $this->contarPagamentos());
        $this->assertSame('paid', $this->fatura($fatura)['status']);

        // Fechada é fechada: nem um centavo a mais entra.
        $this->limparFlash();
        $this->post('/pagamentos', $base + ['amount' => '0,01']);
        $this->assertSame(3, $this->contarPagamentos());
        $this->assertStringContainsString('saldo em aberto', $this->erroNoFlash());
    }

    public function test_fatura_sem_saldo_nao_recebe_pagamento(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        // Enviada de propósito: rascunho agora é recusado pela situação, antes
        // de chegar na conta do saldo — e aqui o que se prova é o saldo.
        $zerada   = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-820', '0.00', 'sent');
        $cheia    = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-821', '500.00');
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->post('/pagamentos', ['invoice_id' => (string) $zerada, 'amount' => '100,00', 'payment_method' => 'pix']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertStringContainsString('saldo em aberto', $this->erroNoFlash());
        $this->limparFlash();

        // Positiva: a fatura com total continua recebendo.
        $this->post('/pagamentos', ['invoice_id' => (string) $cheia, 'amount' => '100,00', 'payment_method' => 'pix']);
        $this->assertSame(1, $this->contarPagamentos());
    }

    public function test_fatura_cancelada_ou_em_rascunho_nao_recebe_pagamento(): void
    {
        $agencyId  = $this->createAgency();
        $cliente   = $this->createClient($agencyId);
        $cancelada = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-830', '1000.00', 'cancelled');
        $rascunho  = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-831', '1000.00', 'draft');
        $user      = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $base = ['amount' => '1.000,00', 'payment_method' => 'pix'];

        // `summaryByAgency` deixa a fatura cancelada fora do faturado e do
        // recebido: aceitar pagamento a ressuscitava como **paga**, somando nos
        // dois. A régua existia só na view (o botão "Registrar" escondido).
        $this->post('/pagamentos', $base + ['invoice_id' => (string) $cancelada]);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertSame('cancelled', $this->fatura($cancelada)['status']);
        $this->assertStringContainsString('cancelada', mb_strtolower($this->erroNoFlash()));
        $this->limparFlash();

        // Rascunho nunca foi cobrado: também não recebe pagamento pela URL.
        $this->post('/pagamentos', $base + ['invoice_id' => (string) $rascunho]);
        $this->assertSame(0, $this->contarPagamentos());
        $this->assertSame('draft', $this->fatura($rascunho)['status']);
        $this->assertStringContainsString('enviada', mb_strtolower($this->erroNoFlash()));
        $this->limparFlash();

        // Positiva: a mesma fatura, enviada pela rota de sempre, recebe.
        $this->post("/faturas/{$rascunho}/enviar");
        $this->assertSame('sent', $this->fatura($rascunho)['status']);
        $this->post('/pagamentos', $base + ['invoice_id' => (string) $rascunho]);
        $this->assertSame(1, $this->contarPagamentos());
        $this->assertSame('paid', $this->fatura($rascunho)['status']);
    }

    // ── 3. Erro amigável no lugar do SQLSTATE ─────────────────────────────────

    public function test_excluir_fatura_com_pagamento_explica_sem_mostrar_sqlstate(): void
    {
        $agencyId  = $this->createAgency();
        $cliente   = $this->createClient($agencyId);
        $fatura    = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-900');
        $pagamento = $this->criarPagamento($agencyId, $fatura, '300.00');
        $user      = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->post("/faturas/{$fatura}", ['_method' => 'DELETE']);

        $erro = $this->erroNoFlash();
        $this->assertSame(1, $this->contarFaturas(), 'A fatura com pagamento não pode ser apagada.');
        $this->assertStringNotContainsString('SQLSTATE', $erro);
        $this->assertStringNotContainsString('foreign key', $erro);
        $this->assertStringContainsString('pagamento', mb_strtolower($erro));
        $this->limparFlash();

        // Positiva: sem o pagamento, a mesma rota apaga a fatura.
        $this->post("/pagamentos/{$pagamento}", ['_method' => 'DELETE']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->post("/faturas/{$fatura}", ['_method' => 'DELETE']);
        $this->assertSame(0, $this->contarFaturas());
    }

    public function test_remover_pagamento_volta_para_a_fatura_e_ignora_o_referer(): void
    {
        $agencyId  = $this->createAgency();
        $cliente   = $this->createClient($agencyId);
        $fatura    = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-910');
        $pagamento = $this->criarPagamento($agencyId, $fatura, '1000.00');
        $user      = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        $_SERVER['HTTP_REFERER'] = 'https://site-do-atacante.example/colhe-sessao';

        $resposta = $this->post("/pagamentos/{$pagamento}", ['_method' => 'DELETE']);

        $this->assertSame(0, $this->contarPagamentos());
        $this->assertSame(
            '/faturas/' . $fatura,
            $resposta->getHeaders()['Location'] ?? '',
            'O destino do redirect sai do banco, não do cabeçalho Referer.',
        );
        // E a fatura volta a "enviada" quando o único pagamento sai.
        $this->assertSame('sent', $this->fatura($fatura)['status']);
    }

    public function test_remover_pagamento_nao_ressuscita_fatura_cancelada(): void
    {
        $agencyId   = $this->createAgency();
        $cliente    = $this->createClient($agencyId);
        $cancelada  = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-920', '1000.00', 'cancelled');
        $primeiro   = $this->criarPagamento($agencyId, $cancelada, '400.00');
        $this->criarPagamento($agencyId, $cancelada, '100.00');
        $viva       = $this->criarFatura($agencyId, (int) $cliente['id'], 'FAT-921', '1000.00', 'partial');
        $pagoNaViva = $this->criarPagamento($agencyId, $viva, '400.00');
        $user       = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // Remover um dos pagamentos que sobraram numa fatura cancelada não pode
        // devolvê-la para `partial`: o painel ignora cancelada, então ela
        // voltaria somando no faturado e no recebido.
        $this->post("/pagamentos/{$primeiro}", ['_method' => 'DELETE']);

        $atual = $this->fatura($cancelada);
        $this->assertSame('cancelled', $atual['status']);
        $this->assertSame('100.00', $atual['amount_paid'], 'O recebido acompanha o pagamento removido.');

        // Positiva: na fatura viva a mesma rota continua re-derivando a situação.
        $this->post("/pagamentos/{$pagoNaViva}", ['_method' => 'DELETE']);
        $this->assertSame('sent', $this->fatura($viva)['status']);
    }

    // ── 4. Vencimento em massa, escopado por agência ──────────────────────────

    public function test_marcar_vencidas_so_mexe_na_agencia_de_quem_abre(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');

        $minha  = $this->criarFatura($agenciaA, (int) $clienteA['id'], 'FAT-A1', '100.00', 'sent', 'CURRENT_DATE - 5');
        $outra  = $this->criarFatura($agenciaA, (int) $clienteA['id'], 'FAT-A2', '100.00', 'sent', 'CURRENT_DATE - 1');
        $emDia  = $this->criarFatura($agenciaA, (int) $clienteA['id'], 'FAT-A3', '100.00', 'sent', 'CURRENT_DATE + 3');
        $alheia = $this->criarFatura($agenciaB, (int) $clienteB['id'], 'FAT-B1', '100.00', 'sent', 'CURRENT_DATE - 5');

        $user = $this->createUser($agenciaA, 'a@test.com');
        $this->actingAs($user['id'], permissions: self::PERM);

        $this->assertSame(200, $this->get('/faturas')->getStatus());

        $this->assertSame('overdue', $this->fatura($minha)['status']);
        $this->assertSame('overdue', $this->fatura($outra)['status']);
        $this->assertSame('sent', $this->fatura($emDia)['status'], 'Fatura em dia não vence.');
        $this->assertSame('sent', $this->fatura($alheia)['status'], 'O UPDATE em massa é escopado por agência.');
    }

    // ── 5. Outra agência ──────────────────────────────────────────────────────

    public function test_financeiro_de_outra_agencia_nao_recebe_pagamento_nem_exclusao(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clienteA = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB = $this->createClient($agenciaB, 'Cliente da B');
        $minha    = $this->criarFatura($agenciaA, (int) $clienteA['id'], 'FAT-C1');
        $dela     = $this->criarFatura($agenciaB, (int) $clienteB['id'], 'FAT-C2');

        $user = $this->createUser($agenciaA, 'a@test.com');
        $this->actingAs($user['id'], permissions: self::PERM);

        // Pagamento na fatura da outra agência: nada gravado.
        $this->post('/pagamentos', ['invoice_id' => (string) $dela, 'amount' => '100,00', 'payment_method' => 'pix']);
        $this->assertSame(0, $this->contarPagamentos());
        $this->limparFlash();

        // Exclusão da fatura da outra agência: segue de pé.
        $this->post("/faturas/{$dela}", ['_method' => 'DELETE']);
        $this->assertSame(2, $this->contarFaturas());
        $this->limparFlash();

        // Positivas na própria agência, com o mesmo usuário e as mesmas rotas.
        $this->post('/pagamentos', ['invoice_id' => (string) $minha, 'amount' => '100,00', 'payment_method' => 'pix']);
        $this->assertSame(1, $this->contarPagamentos());
        $this->assertSame('partial', $this->fatura($minha)['status']);
    }

    public function test_fatura_e_contrato_do_portal_exigem_a_agencia_do_cliente(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $cliente  = $this->createClient($agenciaA, 'Cliente da A');
        $fatura   = $this->criarFatura($agenciaA, (int) $cliente['id'], 'FAT-D1');
        $this->pdo->prepare(
            "INSERT INTO contracts (agency_id, client_id, title, value, currency_code, status, start_date, created_at, updated_at)
             VALUES (:a, :c, 'Contrato da A', 1000.00, 'BRL', 'active', CURRENT_DATE, NOW(), NOW())"
        )->execute([':a' => $agenciaA, ':c' => (int) $cliente['id']]);

        // Positiva por HTTP: o portal do cliente continua listando o que é dele.
        $token  = $cliente['portal_token'];
        $faturas = $this->get("/portal/{$token}/faturas");
        $this->assertSame(200, $faturas->getStatus());
        $this->assertStringContainsString('FAT-D1', $faturas->getBody());
        $contratos = $this->get("/portal/{$token}/contratos");
        $this->assertSame(200, $contratos->getStatus());
        $this->assertStringContainsString('Contrato da A', $contratos->getBody());

        // Negativa na origem: `findByClient` passou a exigir a agência, então o
        // mesmo cliente com a agência errada não devolve linha nenhuma — antes
        // a query não tinha filtro de agência e dependia de quem chamava.
        $invoiceRepo  = new InvoiceRepository();
        $contractRepo = new ContractRepository();
        $this->assertCount(1, $invoiceRepo->findByClient((int) $cliente['id'], $agenciaA));
        $this->assertSame([], $invoiceRepo->findByClient((int) $cliente['id'], $agenciaB));
        $this->assertCount(1, $contractRepo->findByClient((int) $cliente['id'], $agenciaA));
        $this->assertSame([], $contractRepo->findByClient((int) $cliente['id'], $agenciaB));
        $this->assertSame($fatura, (int) $invoiceRepo->findByClient((int) $cliente['id'], $agenciaA)[0]['id']);
    }

    public function test_contrato_de_outra_agencia_nao_entra_na_fatura(): void
    {
        $agenciaA  = $this->createAgency('Agência A');
        $agenciaB  = $this->createAgency('Agência B');
        $clienteA  = $this->createClient($agenciaA, 'Cliente da A');
        $clienteB  = $this->createClient($agenciaB, 'Cliente da B');
        $contratoA = $this->criarContrato($agenciaA, (int) $clienteA['id'], 'Contrato da A');
        $contratoB = $this->criarContrato($agenciaB, (int) $clienteB['id'], 'Contrato da B');

        $user = $this->createUser($agenciaA, 'a@test.com');
        $this->actingAs($user['id'], permissions: self::PERM);

        // `contract_id` é id de tabela multi-tenant e entrava cru do POST: a FK
        // só exige que o contrato exista, não que seja da agência.
        $this->post('/faturas', $this->dadosFatura((int) $clienteA['id'], ['contract_id' => (string) $contratoB]));
        $this->assertSame(0, $this->contarFaturas());
        $this->assertStringContainsString('ontrato', $this->erroNoFlash());
        $this->limparFlash();

        // Positiva: o contrato da própria agência entra e fica vinculado.
        $this->post('/faturas', $this->dadosFatura((int) $clienteA['id'], ['contract_id' => (string) $contratoA]));
        $this->assertSame(1, $this->contarFaturas());
        $nova = $this->fatura((int) $this->pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn());
        $this->assertSame($contratoA, (int) $nova['contract_id']);

        // Linha legada já vinculada ao contrato alheio: o JOIN passou a casar a
        // agência, então o título dele não sai mais na tela nem no PDF que vai
        // anexado no e-mail do cliente.
        $legada = $this->criarFatura($agenciaA, (int) $clienteA['id'], 'FAT-E9');
        $this->pdo->prepare('UPDATE invoices SET contract_id = :c WHERE id = :id')
            ->execute([':c' => $contratoB, ':id' => $legada]);

        $repo    = new InvoiceRepository();
        $detalhe = $repo->findWithDetails($legada, $agenciaA) ?? [];
        $this->assertArrayHasKey('contract_title', $detalhe);
        $this->assertNull($detalhe['contract_title']);
        $this->assertSame(
            'Contrato da A',
            ($repo->findWithDetails((int) $nova['id'], $agenciaA) ?? [])['contract_title'],
        );

        $titulos = array_column($repo->listByAgency($agenciaA), 'contract_title', 'id');
        $this->assertArrayHasKey($legada, $titulos);
        $this->assertNull($titulos[$legada]);
        $this->assertSame('Contrato da A', $titulos[(int) $nova['id']]);
    }

    // ── 6. Contrato ───────────────────────────────────────────────────────────

    public function test_contrato_le_o_valor_e_recusa_situacao_e_periodicidade_inventadas(): void
    {
        $agencyId = $this->createAgency();
        $cliente  = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM);

        // Positiva: "1.234,56" entra inteiro (o cast antigo gravava 1,23).
        $this->post('/contratos', $this->dadosContrato((int) $cliente['id']));
        $this->assertSame(1, $this->contarContratos());
        $this->assertSame(
            '1234.56',
            (string) $this->pdo->query('SELECT value FROM contracts ORDER BY id DESC LIMIT 1')->fetchColumn(),
        );

        // Negativas: situação fora do catálogo, valor negativo, periodicidade inventada.
        foreach ([
            ['status' => 'assinado_na_marra'],
            ['value' => '-500,00'],
            ['recurrence' => 'quinzenal'],
            ['title' => ''],
        ] as $quebrado) {
            $this->limparFlash();
            $this->post('/contratos', $this->dadosContrato((int) $cliente['id'], $quebrado));
            $this->assertSame(1, $this->contarContratos(), 'Recusado: ' . json_encode($quebrado));
            $this->assertNotSame('', $this->erroNoFlash());
        }
    }
}
