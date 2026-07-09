<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ContentPlanRepository;
use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * updateItem/update gravam apenas as chaves enviadas. Chave ausente preserva o
 * valor; chave presente e vazia limpa o campo (antes o array_filter descartava
 * o vazio, tornando impossível apagar uma legenda ou remover uma capa).
 */
class ContentPlanItemUpdateTest extends TestCase
{
    /** @param array<string,mixed> $input */
    private function capturedItemFields(array $input): array
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findItem')->willReturn(['id' => 5, 'agency_id' => 1]);

        $captured = [];
        $repo->method('updateItem')->willReturnCallback(
            function (int $id, array $fields) use (&$captured): int {
                $captured = $fields;
                return 1;
            }
        );

        $service = new ContentPlanService($repo, $this->createMock(GoogleDriveService::class));
        $service->updateItem(5, 1, $input);

        return $captured;
    }

    public function test_empty_string_clears_a_nullable_field(): void
    {
        $fields = $this->capturedItemFields(['caption' => '', 'cover_url' => '']);

        $this->assertArrayHasKey('caption', $fields);
        $this->assertNull($fields['caption']);
        $this->assertNull($fields['cover_url']);
    }

    public function test_absent_key_is_left_untouched(): void
    {
        $fields = $this->capturedItemFields(['caption' => 'nova legenda']);

        $this->assertSame(['caption' => 'nova legenda'], $fields);
        $this->assertArrayNotHasKey('script', $fields);
        $this->assertArrayNotHasKey('cover_url', $fields);
    }

    public function test_values_are_trimmed_and_whitespace_only_clears(): void
    {
        $fields = $this->capturedItemFields(['title' => '  Post  ', 'theme' => '   ']);

        $this->assertSame('Post', $fields['title']);
        $this->assertNull($fields['theme']);
    }

    public function test_emptying_the_carousel_stores_null_not_an_empty_json_array(): void
    {
        $this->assertNull($this->capturedItemFields(['images' => []])['images']);
        $this->assertNull($this->capturedItemFields(['images' => ['', '  ']])['images']);

        $fields = $this->capturedItemFields(['images' => [' a.jpg ', '', 'b.jpg']]);
        $this->assertSame('["a.jpg","b.jpg"]', $fields['images']);
    }

    public function test_unassigning_the_owner_stores_null(): void
    {
        $this->assertNull($this->capturedItemFields(['assigned_to' => ''])['assigned_to']);
        $this->assertSame(9, $this->capturedItemFields(['assigned_to' => '9'])['assigned_to']);
    }

    public function test_sort_order_zero_is_persisted(): void
    {
        $fields = $this->capturedItemFields(['sort_order' => 0]);

        $this->assertArrayHasKey('sort_order', $fields);
        $this->assertSame(0, $fields['sort_order']);
    }

    public function test_an_invalid_status_is_ignored(): void
    {
        $this->assertArrayNotHasKey('status', $this->capturedItemFields(['status' => 'published']));
        $this->assertSame('approved', $this->capturedItemFields(['status' => 'approved'])['status']);
    }

    public function test_an_update_with_nothing_to_write_is_a_no_op(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);
        $repo->method('findItem')->willReturn(['id' => 5, 'agency_id' => 1]);
        $repo->expects($this->never())->method('updateItem');

        $service = new ContentPlanService($repo, $this->createMock(GoogleDriveService::class));
        $this->assertFalse($service->updateItem(5, 1, []));
    }

    public function test_plan_notes_can_be_cleared_but_title_cannot(): void
    {
        $repo = $this->createMock(ContentPlanRepository::class);

        $captured = [];
        $repo->method('updatePlan')->willReturnCallback(
            function (int $id, int $agencyId, array $fields) use (&$captured): int {
                $captured = $fields;
                return 1;
            }
        );

        $service = new ContentPlanService($repo, $this->createMock(GoogleDriveService::class));
        $service->update(7, 1, ['title' => '', 'notes' => '']);

        $this->assertArrayNotHasKey('title', $captured);
        $this->assertNull($captured['notes']);
    }
}
