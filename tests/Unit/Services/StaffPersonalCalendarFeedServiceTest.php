<?php

namespace Tests\Unit\Services;

use App\Services\StaffPersonalCalendarFeedService;
use PHPUnit\Framework\TestCase;

class StaffPersonalCalendarFeedServiceTest extends TestCase
{
    public function test_unknown_type_falls_back_to_employer_sponsored(): void
    {
        $service = new StaffPersonalCalendarFeedService();

        $this->assertSame('paid', $service->normalizeCalendarType(null));
        $this->assertSame('paid', $service->normalizeCalendarType('nope'));
        $this->assertSame('paid', StaffPersonalCalendarFeedService::DEFAULT_TYPE);
    }

    public function test_known_calendar_types_are_accepted(): void
    {
        $service = new StaffPersonalCalendarFeedService();

        $this->assertSame('paid', $service->normalizeCalendarType('paid'));
        $this->assertSame('jrp', $service->normalizeCalendarType('JRP'));
        $this->assertSame('ajay', $service->normalizeCalendarType('ajay'));
        $this->assertSame('arun', $service->normalizeCalendarType('arun'));
    }
}
