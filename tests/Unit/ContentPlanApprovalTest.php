<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ContentPlanRepository;
use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * Aprovar o último criativo pendente aprova o plano inteiro — o cliente não
 * precisa clicar também em "Aprovar Tudo". Também cobre a proporção do preview
 * (9:16 para capa de Reels/Story, 3:4 para foto e carrossel).
 */
class ContentPlanApprovalTest extends TestCase
{
    private function service(ContentPlanRepository $repo): ContentPlanService
    {
        return new ContentPlanService($repo, $this->createMock(GoogleDriveService::class));
    }

    private function planRow(string $status): array
    {
        return [
            'id'         => 7,
            'agency_id'  => 1,
            'client_id'  => 42,
            'title'      => 'Semana 01',
            'status'     => $status,
            'created_by' => 3,
        ];
    }

    public function test_approving_last_pending_item_approves_the_plan(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 3]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('sent'));

        $repo->expects($this->once())
            ->method('updatePlan')
            ->with(7, 1, $this->callback(fn($f) => $f['status'] === 'approved' && !empty($f['approved_at'])))
            ->willReturn(1);

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    public function test_plan_stays_open_while_any_item_is_pending(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 2, 'draft' => 1]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('sent'));

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    public function test_plan_is_not_reapproved_when_already_approved(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 3]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('approved'));

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    /**
     * BUG-05: o portal bloqueia "aprovar plano" fora de 'sent', mas a via item
     * a item chamava approvePlan sem olhar o status. Plano em revisão espera a
     * agência corrigir e reenviar — aprovar ali diria que a cliente aceitou
     * justamente a versão que pediu para mudar.
     */
    public function test_plan_in_revision_is_not_closed_item_by_item(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 3]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('revision'));

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    public function test_rejected_plan_is_not_closed_item_by_item(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 3]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('rejected'));

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    /** Dado legado: 'pending_approval' também está na mesa da cliente. */
    public function test_legacy_pending_approval_plan_can_still_be_approved(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdForClient')->willReturn($this->planRow('pending_approval'));

        $repo->expects($this->once())
            ->method('updatePlan')
            ->with(7, 1, $this->callback(fn($f) => $f['status'] === 'approved'))
            ->willReturn(1);

        $this->assertTrue($this->service($repo)->approvePlan(7, 42));
    }

    /**
     * BUG-05 (2ª revisão): a transição fechava só a linha do plano. Os posts
     * ficavam em rascunho e as telas se contradiziam — selo "Aprovado" ao lado
     * de "0/3 aprovados", e cada post "Pendente" no portal. Quem vai com o
     * plano é o post PENDENTE: 'revision'/'rejected' é decisão registrada de
     * quem viu o post e fica onde está (ver `approvePendingItems`).
     */
    public function test_approving_the_plan_also_approves_its_pending_items(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdForClient')->willReturn($this->planRow('sent'));
        $repo->method('updatePlan')->willReturn(1);

        $repo->expects($this->once())->method('approvePendingItems')->with(7)->willReturn(3);

        $this->assertTrue($this->service($repo)->approvePlan(7, 42));
    }

    /** Recusou a transição, não toca nos posts. */
    public function test_items_are_untouched_when_the_transition_is_refused(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdForClient')->willReturn($this->planRow('approved'));

        $repo->expects($this->never())->method('approvePendingItems');

        $this->assertFalse($this->service($repo)->approvePlan(7, 42));
    }

    /**
     * BUG-05 (2ª revisão): trocar `week_start` passou a ARRASTAR os posts, e o
     * `update` não olhava o status — a semana que a cliente fechou era re-datada
     * em silêncio, com os posts aprovados indo junto.
     */
    public function test_week_cannot_move_on_an_approved_plan(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdFull')->willReturn(
            $this->planRow('approved') + ['week_start' => '2026-07-06', 'week_end' => '2026-07-12']
        );

        $repo->expects($this->never())->method('updatePlan');
        $repo->expects($this->never())->method('updateItem');

        $this->expectException(\InvalidArgumentException::class);
        $this->service($repo)->update(7, 1, ['week_start' => '2026-07-13']);
    }

    /** Título e notas não mexem no que a cliente aprovou — continuam gravando. */
    public function test_title_is_still_editable_on_an_approved_plan(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdFull')->willReturn(
            $this->planRow('approved') + ['week_start' => '2026-07-06', 'week_end' => '2026-07-12']
        );

        $repo->expects($this->once())
            ->method('updatePlan')
            ->with(7, 1, ['title' => 'Semana 01 (aprovada)'])
            ->willReturn(1);

        $result = $this->service($repo)->update(7, 1, ['title' => 'Semana 01 (aprovada)']);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['week_moved']);
    }

    public function test_revision_is_refused_on_a_plan_already_approved(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findByIdForClient')->willReturn($this->planRow('approved'));

        $repo->expects($this->never())->method('updatePlan');

        $this->assertFalse($this->service($repo)->requestRevision(7, 42, 'reabre para mim'));
    }

    public function test_a_plain_comment_never_approves_the_plan(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn(['approved' => 3]);
        $repo->method('findByIdForClient')->willReturn($this->planRow('sent'));

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'comment', 'ficou ótimo');
    }

    public function test_plan_without_items_is_never_auto_approved(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('addFeedback')->willReturn(99);
        $repo->method('getItemStatusSummary')->willReturn([]);

        $repo->expects($this->never())->method('updatePlan');

        $this->service($repo)->addFeedback(5, 7, 42, null, 'approved', null);
    }

    public function test_reels_and_story_covers_are_vertical_others_are_portrait(): void
    {
        $this->assertSame('9/16', ContentPlanService::previewRatio('Reels / Vídeo'));
        $this->assertSame('9/16', ContentPlanService::previewRatio('Story'));
        $this->assertSame('3/4',  ContentPlanService::previewRatio('Carrossel'));
        $this->assertSame('3/4',  ContentPlanService::previewRatio('Feed Estático'));
        $this->assertSame('3/4',  ContentPlanService::previewRatio(null));

        $this->assertStringContainsString('aspect-[9/16]', ContentPlanService::previewFrameClass('Story'));
        $this->assertStringContainsString('aspect-[3/4]',  ContentPlanService::previewFrameClass('Carrossel'));
    }
}
