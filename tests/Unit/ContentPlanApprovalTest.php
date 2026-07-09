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
