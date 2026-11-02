<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * BUG-06 — o sino de notificações.
 *
 * "Marcar como lida" respondia **500** em produção: o handler era
 * `notificationsMarkRead(Request $r, int $id)` e o Router chama o controller com
 * um argumento só (ArgumentCountError). O JS do sino tinha `catch {}` vazio, então
 * o erro sumia: o contador descia na tela e voltava no poll de 30 s.
 *
 * Cada negativa aqui vem com a positiva da mesma rota — 404/500 não prova nada
 * se a rota nunca funcionou.
 */
class NotificationBellTest extends FeatureTestCase
{
    private function criarNotificacao(int $agencyId, int $userId, string $titulo = 'Plano enviado'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (agency_id, user_id, type, title, body, action_url, created_at)
             VALUES (:a, :u, 'plan.sent', :t, 'Confira o plano da semana', '/conteudo', NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':u' => $userId, ':t' => $titulo]);

        return (int) $stmt->fetchColumn();
    }

    private function naoLidas(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
        $stmt->execute([':u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    // ── Marcar uma como lida ─────────────────────────────────────────────────

    public function test_marcar_como_lida_responde_200_e_grava(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['dashboard.view']);

        $id = $this->criarNotificacao($agencyId, $user['id']);

        $response = $this->post("/notificacoes/{$id}/lida");

        $this->assertSame(200, $response->getStatus(), 'Marcar como lida devolveu ' . $response->getStatus() . ' — era o 500 do BUG-06.');
        $this->assertSame(['success' => true], json_decode($response->getBody(), true));
        $this->assertSame(0, $this->naoLidas($user['id']), 'A notificação continuou não lida: o contador voltaria no poll.');
    }

    public function test_a_rota_em_ingles_marca_como_lida_igual(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['dashboard.view']);

        $id = $this->criarNotificacao($agencyId, $user['id']);

        $response = $this->post("/notifications/{$id}/read");

        $this->assertSame(200, $response->getStatus());
        $this->assertSame(0, $this->naoLidas($user['id']));
    }

    public function test_nao_marca_como_lida_a_notificacao_de_outra_pessoa(): void
    {
        $agencyId = $this->createAgency();
        $dono     = $this->createUser($agencyId, 'dono@test.com');
        $outro    = $this->createUser($agencyId, 'outro@test.com');

        $id = $this->criarNotificacao($agencyId, $dono['id']);

        $this->actingAs($outro['id'], permissions: ['dashboard.view']);
        $response = $this->post("/notificacoes/{$id}/lida");

        // A rota responde 200 (não existe "notificação de ninguém"), mas a linha
        // do dono fica intacta.
        $this->assertSame(200, $response->getStatus());
        $this->assertSame(1, $this->naoLidas($dono['id']), 'Marcou como lida a notificação de outro usuário.');
    }

    public function test_marcar_como_lida_exige_login(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $id       = $this->criarNotificacao($agencyId, $user['id']);

        $_SESSION = [];
        $response = $this->post("/notificacoes/{$id}/lida");

        $this->assertNotSame(200, $response->getStatus());
        $this->assertSame(1, $this->naoLidas($user['id']));
    }

    // ── Contador e lista ─────────────────────────────────────────────────────

    public function test_contador_zera_depois_de_marcar_todas(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['dashboard.view']);

        $this->criarNotificacao($agencyId, $user['id'], 'Uma');
        $this->criarNotificacao($agencyId, $user['id'], 'Duas');

        $antes = json_decode($this->get('/notificacoes/count')->getBody(), true);
        $this->assertSame(2, $antes['count']);

        $this->assertSame(200, $this->post('/notificacoes/todas-lidas')->getStatus());

        $depois = json_decode($this->get('/notificacoes/count')->getBody(), true);
        $this->assertSame(0, $depois['count'], 'O contador não zerou depois de "marcar todas como lidas".');
    }

    public function test_contador_continua_zerado_no_poll_seguinte(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['dashboard.view']);

        $id = $this->criarNotificacao($agencyId, $user['id']);
        $this->post("/notificacoes/{$id}/lida");

        // O poll do sino é só este GET de novo — é aqui que o contador voltava.
        $primeiro = json_decode($this->get('/notificacoes/count')->getBody(), true);
        $segundo  = json_decode($this->get('/notificacoes/count')->getBody(), true);

        $this->assertSame(0, $primeiro['count']);
        $this->assertSame(0, $segundo['count']);
    }

    public function test_a_lista_do_sino_so_traz_as_nao_lidas_do_proprio_usuario(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $outro    = $this->createUser($agencyId, 'outro@test.com');
        $this->actingAs($user['id'], permissions: ['dashboard.view']);

        $minha = $this->criarNotificacao($agencyId, $user['id'], 'Minha');
        $this->criarNotificacao($agencyId, $outro['id'], 'Do outro');

        $lista = json_decode($this->get('/notificacoes')->getBody(), true);

        $titulos = array_column($lista['notifications'], 'title');
        $this->assertSame(['Minha'], $titulos);

        $this->post("/notificacoes/{$minha}/lida");
        $depois = json_decode($this->get('/notificacoes')->getBody(), true);
        $this->assertSame([], $depois['notifications'], 'A notificação lida continuou na lista do sino.');
    }
}
