<?php

namespace Tests\Unit\Models;

use App\Models\ActivitiesLog;
use PHPUnit\Framework\TestCase;

class ActivitiesLogFollowupDateTest extends TestCase
{
    public function test_formats_iso_datetime_with_time(): void
    {
        $formatted = ActivitiesLog::formatFollowupDateForDisplay('2026-05-20T14:00:00.000000Z');

        $this->assertMatchesRegularExpression('/^20 May 2026/', $formatted);
        $this->assertStringContainsString('PM', $formatted);
    }

    public function test_formats_date_only_without_time_suffix(): void
    {
        $formatted = ActivitiesLog::formatFollowupDateForDisplay('2026-05-20');

        $this->assertSame('20 May 2026', $formatted);
    }

    public function test_returns_empty_for_null_or_blank(): void
    {
        $this->assertSame('', ActivitiesLog::formatFollowupDateForDisplay(null));
        $this->assertSame('', ActivitiesLog::formatFollowupDateForDisplay(''));
    }
}
