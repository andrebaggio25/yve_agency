<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Secret;
use App\Repositories\OrganicAccountRepository;

/** SEC-14: token do orgânico cifrado no banco e legível só pelo repositório. */
final class OrganicTokenEncryptionTest extends FeatureTestCase
{
    private const TOKEN = 'EAAG-token-de-page-de-longa-duracao';

    private function createAccount(int $agencyId): int
    {
        return (new OrganicAccountRepository())->create([
            'agency_id'        => $agencyId,
            'client_id'        => null,
            'platform'         => 'instagram',
            'platform_page_id' => '1784',
            'name'             => 'Perfil',
            'access_token'     => self::TOKEN,
        ]);
    }

    private function storedToken(int $id): string
    {
        return (string) $this->pdo->query("SELECT access_token FROM organic_accounts WHERE id = {$id}")->fetchColumn();
    }

    public function test_token_fica_cifrado_no_banco(): void
    {
        $id = $this->createAccount($this->createAgency());

        $stored = $this->storedToken($id);
        $this->assertNotSame(self::TOKEN, $stored);
        $this->assertTrue(Secret::isEncrypted($stored));
    }

    public function test_repositorio_devolve_o_token_decifrado_para_o_sync(): void
    {
        $agencyId = $this->createAgency();
        $id       = $this->createAccount($agencyId);
        $repo     = new OrganicAccountRepository();

        $this->assertSame(self::TOKEN, $repo->findByIdAndAgency($id, $agencyId)['access_token']);
        $this->assertSame(self::TOKEN, $repo->findAllActive()[0]['access_token']);
    }

    public function test_token_legado_em_texto_puro_continua_funcionando_antes_da_migration(): void
    {
        $agencyId = $this->createAgency();
        $id       = $this->createAccount($agencyId);
        $this->pdo->exec("UPDATE organic_accounts SET access_token = 'legado-texto-puro' WHERE id = {$id}");

        $this->assertSame('legado-texto-puro', (new OrganicAccountRepository())->findByIdAndAgency($id, $agencyId)['access_token']);
    }
}
