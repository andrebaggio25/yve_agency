<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Leitura de dinheiro vindo de formulário (SEC-16).
 *
 * O financeiro fazia `(float) str_replace(',', '.', $input)`: "1.234,56" virava
 * "1.234.56" e o cast parava em **1.23** — desconto e pagamento entravam mil
 * vezes menores, calados. E texto que não é número virava 0, então um POST cru
 * gravava fatura de valor zero sem reclamar.
 *
 * Aqui o separador decimal é o **último** ponto ou vírgula quando os dois
 * aparecem ("1.234,56" e "1,234.56" dão 1234.56); um separador repetido só pode
 * ser milhar ("1.234.567"). O que não casa com número devolve `null` — quem
 * chama decide se é erro ou se vale o padrão.
 *
 * As colunas são `DECIMAL(12,2)`: valor fora da faixa estoura o Postgres com
 * SQLSTATE 22003, então o limite vive aqui.
 */
final class Money
{
    /** Teto de `DECIMAL(12,2)`: 10 dígitos inteiros + 2 casas. */
    public const MAX = 9999999999.99;

    /**
     * Converte para decimal, ou null se não for número. `$scale` é o número de
     * casas da coluna de destino — 2 em dinheiro, 3 em `invoice_items.quantity`.
     */
    public static function parse(mixed $raw, int $scale = 2): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return is_finite((float) $raw) ? round((float) $raw, $scale) : null;
        }

        if (!is_string($raw)) {
            return null;
        }

        // Espaço (inclusive o fino e o inquebrável de copiar-e-colar), símbolo
        // de moeda e o `+` não dizem nada sobre o número.
        $s = (string) preg_replace('/[\s\x{00A0}\x{202F}]|R\$|[$€£]|\+/u', '', $raw);
        if ($s === '') {
            return null;
        }

        $negative = str_starts_with($s, '-');
        if ($negative) {
            $s = substr($s, 1);
        }

        $dots   = substr_count($s, '.');
        $commas = substr_count($s, ',');

        if ($dots > 0 && $commas > 0) {
            // Os dois presentes: o último é o decimal, o outro é milhar.
            $decimalAt = max((int) strrpos($s, '.'), (int) strrpos($s, ','));
            $s = str_replace(['.', ','], '', substr($s, 0, $decimalAt))
               . '.' . substr($s, $decimalAt + 1);
        } elseif ($dots > 1 || $commas > 1) {
            // Separador repetido não é decimal: é milhar ("1.234.567").
            $s = str_replace(['.', ','], '', $s);
        } else {
            // Um separador só: decimal. O `<input type="number">` manda ponto;
            // quem digita em pt-BR manda vírgula.
            $s = str_replace(',', '.', $s);
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $s)) {
            return null;
        }

        $value = round((float) $s, $scale);

        return $negative ? -$value : $value;
    }

    /** Está dentro da faixa que a coluna aceita? (sinal incluído) */
    public static function inRange(float $value): bool
    {
        return $value >= -self::MAX && $value <= self::MAX;
    }

    /**
     * Compara saldo em centavos: -1, 0 ou 1. Comparar `float` direto faz
     * 0.1 + 0.2 > 0.3 e a fatura nunca fecha como paga.
     */
    public static function cmp(float $a, float $b): int
    {
        return (int) round($a * 100) <=> (int) round($b * 100);
    }
}
