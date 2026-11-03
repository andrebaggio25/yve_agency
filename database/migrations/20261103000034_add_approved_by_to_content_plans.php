<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Decisão do dono (02/11): a equipe pode aprovar em nome do cliente, mas o sistema
 * precisa dizer quem aprovou. Nulo = aprovado pelo próprio cliente no portal, ou
 * aprovação anterior a esta coluna (nesse caso a tela não atribui autor).
 *
 * `ON DELETE SET NULL`: excluir o usuário não pode apagar o plano. Índice para a
 * FK, como nas demais. Coluna nula sem default: sobe sem reescrever a tabela.
 */
final class AddApprovedByToContentPlans extends AbstractMigration
{
    public function up(): void
    {
        $this->table('content_plans')
            ->addColumn('approved_by', 'biginteger', ['null' => true, 'signed' => false])
            ->addIndex(['approved_by'])
            ->addForeignKey('approved_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->update();
    }

    public function down(): void
    {
        $this->table('content_plans')
            ->dropForeignKey('approved_by')
            ->removeIndex(['approved_by'])
            ->removeColumn('approved_by')
            ->update();
    }
}
