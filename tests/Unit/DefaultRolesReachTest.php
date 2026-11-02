<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SEC-11 — depois que `Auth::canAccessClient` passou a exigir
 * `clients.view_all` **ou** vínculo explícito em `client_user_access`, papel da
 * equipe da agência sem nenhum dos dois não alcança cliente nenhum: lista de
 * clientes vazia, módulo de Conteúdo vazio e 404 em qualquer plano.
 *
 * E o vínculo por cliente é manual — nada no sistema o cria para a equipe. Foi
 * exatamente o que aconteceu com o Gestor de Tráfego, que a migration 28 deixou
 * de fora do backfill.
 *
 * Papel de CLIENTE (`client_*`) fica fora da régua de propósito: ali o vínculo
 * explícito é o mecanismo correto — o usuário do cliente só vê o cliente dele.
 */
class DefaultRolesReachTest extends TestCase
{
    /** Permissões que só servem se o papel alcançar algum cliente. */
    private const PRECISA_ALCANCAR_CLIENTE = ['clients.view', 'content.view', 'approvals.view'];

    public function test_papel_padrao_da_equipe_que_ve_cliente_tem_view_all(): void
    {
        require_once dirname(__DIR__, 2) . '/database/seeders/InitialDataSeeder.php';

        $avaliados = 0;

        foreach (\InitialDataSeeder::DEFAULT_ROLES as $role) {
            $permissions = $role['permissions'];

            // '__ALL__' recebe o catálogo inteiro, view_all incluída.
            if (!is_array($permissions) || str_starts_with($role['slug'], 'client_')) {
                continue;
            }
            if (array_intersect(self::PRECISA_ALCANCAR_CLIENTE, $permissions) === []) {
                continue;
            }

            $avaliados++;
            $this->assertContains(
                'clients.view_all',
                $permissions,
                "O papel padrão '{$role['slug']}' vê cliente/conteúdo mas não alcança nenhum: "
                . 'sem clients.view_all e sem vínculo manual, as telas nascem vazias (SEC-11).',
            );
        }

        $this->assertGreaterThan(0, $avaliados, 'A régua precisa avaliar algum papel.');
    }
}
