<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SEC-16 — leitura de dinheiro vindo de formulário.
 *
 * O financeiro fazia `(float) str_replace(',', '.', $input)`. Os casos abaixo
 * são exatamente os que esse cast errava: "1.234,56" parava em 1.23, texto
 * virava 0 (fatura de valor zero gravada sem reclamar) e nada barrava negativo.
 */
class MoneyTest extends TestCase
{
    /**
     * @return array<string,array{0:mixed,1:float|null}>
     */
    public static function valores(): array
    {
        return [
            'ponto decimal (input type=number)' => ['1234.56', 1234.56],
            'vírgula decimal (pt-BR)'           => ['1234,56', 1234.56],
            'milhar com ponto e decimal vírgula'=> ['1.234,56', 1234.56],
            'milhar com vírgula e decimal ponto'=> ['1,234.56', 1234.56],
            'dois pontos só podem ser milhar'   => ['1.234.567', 1234567.0],
            'com símbolo de moeda e espaço'     => ['R$ 1.234,56', 1234.56],
            'negativo'                          => ['-100,50', -100.50],
            'inteiro em float'                  => [1234.5, 1234.5],
            'arredonda na terceira casa'        => ['10,006', 10.01],
            'texto não é número'                => ['abc', null],
            'vazio'                             => ['', null],
            'número com lixo colado'            => ['100abc', null],
            'nulo'                              => [null, null],
        ];
    }

    #[DataProvider('valores')]
    public function test_le_o_que_o_formulario_manda(mixed $raw, ?float $esperado): void
    {
        $this->assertSame($esperado, Money::parse($raw));
    }

    public function test_quantidade_aceita_tres_casas(): void
    {
        // invoice_items.quantity é DECIMAL(10,3) — arredondar a 2 mudaria o item.
        $this->assertSame(1.125, Money::parse('1,125', 3));
        $this->assertSame(1.13,  Money::parse('1,125'));
    }

    public function test_faixa_da_coluna_decimal_12_2(): void
    {
        $this->assertTrue(Money::inRange(Money::MAX));
        $this->assertFalse(Money::inRange(Money::MAX + 1));
        $this->assertFalse(Money::inRange(-Money::MAX - 1));
    }

    public function test_compara_saldo_em_centavos(): void
    {
        // Em float, 33.33 * 3 dá 99.98999999999999 e a fatura nunca fechava.
        $this->assertSame(0, Money::cmp(33.33 + 33.33 + 33.33, 99.99));
        $this->assertSame(1, Money::cmp(100.01, 100.0));
        $this->assertSame(-1, Money::cmp(99.99, 100.0));
    }
}
