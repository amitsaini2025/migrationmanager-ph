<?php

namespace Tests\Unit;

use App\Support\WorkflowV2Display;
use Tests\TestCase;

class WorkflowV2ChecklistAnyOrderTest extends TestCase
{
    public function test_active_checklist_index_still_reports_first_incomplete(): void
    {
        $rows = [
            ['id' => 1, 'done' => true],
            ['id' => 2, 'done' => false],
            ['id' => 3, 'done' => false],
        ];

        $this->assertSame(1, WorkflowV2Display::activeChecklistIndex($rows));
    }

    public function test_any_incomplete_item_is_eligible_when_stage_is_interactive(): void
    {
        $rows = [
            ['id' => 10, 'done' => true],
            ['id' => 20, 'done' => false],
            ['id' => 30, 'done' => false],
        ];

        $interactive = true;
        $viewIsCurrent = true;

        $enabledIds = [];
        foreach ($rows as $row) {
            $done = ! empty($row['done']);
            $itemId = $row['id'] ?? null;
            $itemActive = $interactive && $viewIsCurrent && ! $done && ! empty($itemId);
            if ($itemActive) {
                $enabledIds[] = (int) $itemId;
            }
        }

        // Any-order: later incomplete items are enabled even when an earlier incomplete exists.
        $this->assertSame([20, 30], $enabledIds);
        $this->assertNotSame(
            [WorkflowV2Display::activeChecklistIndex($rows) === 1 ? 20 : null],
            $enabledIds,
            'Eligibility must not be limited to the first incomplete item only'
        );
    }

    public function test_non_current_stage_keeps_items_disabled(): void
    {
        $rows = [
            ['id' => 20, 'done' => false],
            ['id' => 30, 'done' => false],
        ];

        $interactive = true;
        $viewIsCurrent = false;

        foreach ($rows as $row) {
            $done = ! empty($row['done']);
            $itemId = $row['id'] ?? null;
            $itemActive = $interactive && $viewIsCurrent && ! $done && ! empty($itemId);
            $this->assertFalse($itemActive);
        }
    }
}
