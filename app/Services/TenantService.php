<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AgencyRepository;
use App\Repositories\PlatformUserRepository;

/**
 * Regra de negócio do provisionamento de tenants (painel da plataforma).
 *
 * Criar um tenant é atômico: agência + usuário super admin + papel. Se qualquer
 * passo falhar, nada é gravado — senão sobraria uma agência sem dono, ou um
 * usuário sem papel (não conseguiria fazer nada ao logar).
 */
class TenantService
{
    public function __construct(
        private readonly AgencyRepository       $agencies,
        private readonly PlatformUserRepository $users,
    ) {}

    /**
     * Cria o tenant com o usuário administrador, em transação.
     *
     * @return array{success:bool,agency_id?:int,password?:string,error?:string}
     *         `password` só vem preenchida quando foi gerada aqui.
     */
    public function createWithAdmin(array $input): array
    {
        $name       = trim((string) ($input['name'] ?? ''));
        $adminEmail = trim((string) ($input['admin_email'] ?? ''));
        $adminName  = trim((string) ($input['admin_name'] ?? '')) ?: 'Super Admin';
        $adminPass  = trim((string) ($input['admin_password'] ?? ''));

        if ($name === '') {
            return ['success' => false, 'error' => 'O nome do tenant é obrigatório.'];
        }
        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'E-mail do administrador é obrigatório e deve ser válido.'];
        }
        if ($this->users->emailTaken($adminEmail)) {
            return ['success' => false, 'error' => "Já existe um usuário com o e-mail \"{$adminEmail}\"."];
        }

        $generated = $adminPass === '';
        $password  = $generated ? $this->generatePassword() : $adminPass;

        $this->users->beginTransaction();
        try {
            $agencyId = $this->agencies->create([
                'name'          => $name,
                'country'       => $input['country']       ?? 'BR',
                'currency_code' => $input['currency_code'] ?? 'BRL',
                'timezone'      => $input['timezone']      ?? 'America/Sao_Paulo',
                'status'        => $input['status']        ?? 'active',
                'slug'          => $this->uniqueSlug($name),
            ]);

            $userId = $this->users->create(
                $agencyId,
                $adminName,
                $adminEmail,
                password_hash($password, PASSWORD_ARGON2ID)
            );

            $roleId = $this->users->roleIdBySlug('super_admin');
            if ($roleId !== null) {
                $this->users->assignRole($userId, $roleId);
            }

            $this->users->commit();
        } catch (\Throwable $e) {
            $this->users->rollBack();
            return ['success' => false, 'error' => 'Erro ao criar tenant: ' . $e->getMessage()];
        }

        return [
            'success'   => true,
            'agency_id' => $agencyId,
            'password'  => $generated ? $password : null,
        ];
    }

    /** Slug único a partir do nome (agency-2, agency-3… se colidir). */
    private function uniqueSlug(string $name): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $base  = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($ascii)), '-') ?: 'agency';

        $slug = $base;
        $i    = 1;
        while ($this->agencies->slugExists($slug)) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    private function generatePassword(): string
    {
        // Sem caracteres ambíguos (l/1/O/0) — a senha é ditada/copiada na mão.
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
        $pass  = '';
        for ($i = 0; $i < 12; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }
}
