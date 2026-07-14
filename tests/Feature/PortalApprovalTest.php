<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Aprovação do plano pelo portal do cliente.
 *
 * O envio grava `status = 'sent'`, mas o portal só aceitava aprovar planos
 * `'pending_approval'` — valor que o sistema nunca grava. Resultado: o botão
 * "Aprovar plano completo" não fazia nada e o KPI de pendentes ficava em zero
 * com plano parado esperando a cliente. Estes testes travam o vocabulário.
 */
class PortalApprovalTest extends FeatureTestCase
{
    /** @return array{plan:int,client:array{id:int,portal_token:string}} */
    private function seedSentPlan(int $agencyId, string $status = 'sent'): array
    {
        $client = $this->createClient($agencyId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO content_plans (agency_id, client_id, title, week_start, week_end, status, sent_at, created_at)
             VALUES (:a, :c, 'Semana 06/07', '2026-07-06', '2026-07-12', :s, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $client['id'], ':s' => $status]);

        return ['plan' => (int) $stmt->fetchColumn(), 'client' => $client];
    }

    public function test_cliente_aprova_plano_com_status_sent(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $token    = $seed['client']['portal_token'];

        $response = $this->post("/portal/{$token}/planos/{$seed['plan']}/aprovar");

        $this->assertSame(302, $response->getStatus());
        $status = $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn();
        $this->assertSame('approved', $status, 'Plano enviado (sent) tem de poder ser aprovado pelo portal.');
    }

    public function test_cliente_solicita_revisao_de_plano_sent(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $token    = $seed['client']['portal_token'];

        $response = $this->post("/portal/{$token}/planos/{$seed['plan']}/revisao", ['comment' => 'Trocar a capa de quarta.']);

        $this->assertSame(302, $response->getStatus());
        $status = $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn();
        $this->assertSame('revision', $status);
    }

    public function test_plano_rascunho_nao_pode_ser_aprovado_pelo_portal(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId, status: 'draft');
        $token    = $seed['client']['portal_token'];

        $this->post("/portal/{$token}/planos/{$seed['plan']}/aprovar");

        $status = $this->pdo->query("SELECT status FROM content_plans WHERE id = {$seed['plan']}")->fetchColumn();
        $this->assertSame('draft', $status, 'Rascunho nunca chegou à cliente — não pode virar aprovado.');
    }

    public function test_dashboard_do_portal_conta_plano_sent_como_pendente(): void
    {
        $agencyId = $this->createAgency();
        $seed     = $this->seedSentPlan($agencyId);
        $token    = $seed['client']['portal_token'];

        $response = $this->get("/portal/{$token}");

        $this->assertSame(200, $response->getStatus());
        // O bloco "Aguardando aprovação" só aparece quando o KPI enxerga o plano.
        $this->assertStringContainsString('Semana 06/07', $response->getBody());
    }
}
