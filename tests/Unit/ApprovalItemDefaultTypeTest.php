<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * O componente do item de aprovação tem de USAR o tipo de feedback que o PHP
 * pré-seleciona.
 *
 * Bug real (BUG-04): a view chamava `approvalItem()` com cinco argumentos, mas
 * a função declarava quatro — o `$defaultType` calculado em
 * `approvals/show.php` era descartado e o estado nascia em `type: 'comment'`.
 * Perfil com `approvals.approve` e sem `approvals.comment` abria o formulário,
 * escrevia e enviava: ia `feedback_type=comment` e o controller respondia 403
 * ("Seu perfil não permite esta ação"), que é exatamente o que o item veio
 * consertar. Nem PHPStan nem PHPUnit viam — JS aceita argumento sobrando.
 */
class ApprovalItemDefaultTypeTest extends TestCase
{
    public function test_approval_item_usa_o_tipo_padrao_que_a_view_passa(): void
    {
        $root = dirname(__DIR__, 2);
        $js   = (string) file_get_contents($root . '/public/js/approvals.js');
        $view = (string) file_get_contents($root . '/resources/views/approvals/show.php');

        // A view passa o tipo pré-selecionado… (mesma linha do x-data; os
        // argumentos trazem `(int)` e `e()`, então nada de [^)] aqui)
        $this->assertMatchesRegularExpression(
            '/approvalItem\([^\n]*\$defaultType/',
            $view,
            'A view deve passar $defaultType para approvalItem().'
        );

        // …e a função declara o parâmetro correspondente…
        $this->assertSame(
            1,
            preg_match('/function approvalItem\(([^)]*)\)/', $js, $m),
            'approvals.js deve declarar approvalItem().'
        );

        $params = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        $this->assertCount(5, $params, 'approvalItem() recebe 5 argumentos da view: ' . $m[1]);

        // …e o usa como tipo inicial, em vez de um literal fixo.
        $last = $params[4];
        $this->assertMatchesRegularExpression(
            '/\n\s*type:\s*' . preg_quote($last, '/') . '\b/',
            $js,
            "O estado inicial `type` deve vir de `{$last}`, não de um literal."
        );
    }
}
