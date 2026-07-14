<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * PROD-06 — white-label: cor da marca por agência.
 *
 * O portal do cliente é a tela que a agência mostra para o cliente dela. Sair
 * do violeta do YVE e usar a cor da própria agência é o que faz o portal
 * parecer da agência, e não de um fornecedor. O FE-01 já preparou o terreno:
 * o acento é a variável CSS `--accent`, então basta emiti-la com esta cor.
 *
 * Guardado como hex (`#7c3aed`). NULL = usa o padrão do sistema.
 */
final class AddAgencyBrandColor extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE agencies ADD COLUMN IF NOT EXISTS brand_color VARCHAR(7)");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE agencies DROP COLUMN IF EXISTS brand_color");
    }
}
