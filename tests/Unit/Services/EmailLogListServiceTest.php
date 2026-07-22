<?php

namespace Tests\Unit\Services;

use App\Models\EmailLog;
use App\Services\EmailLogListService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailLogListServiceTest extends TestCase
{
    #[Test]
    public function date_sort_uses_received_date_with_created_at_fallback(): void
    {
        $service = new EmailLogListService();
        $query = EmailLog::query();
        $service->applySort($query, 'date');

        $sql = strtolower($query->toSql());
        $this->assertStringContainsString('coalesce(received_date, created_at)', $sql);
        $this->assertStringContainsString('desc', $sql);
    }

    #[Test]
    public function subject_and_sender_sorts_keep_created_at_as_tiebreaker(): void
    {
        $service = new EmailLogListService();

        $subjectQuery = EmailLog::query();
        $service->applySort($subjectQuery, 'subject');
        $subjectSql = strtolower($subjectQuery->toSql());
        $this->assertStringContainsString('order by "subject"', $subjectSql);
        $this->assertStringContainsString('"created_at" desc', $subjectSql);

        $senderQuery = EmailLog::query();
        $service->applySort($senderQuery, 'sender');
        $senderSql = strtolower($senderQuery->toSql());
        $this->assertStringContainsString('order by "from_mail"', $senderSql);
        $this->assertStringContainsString('"created_at" desc', $senderSql);
    }
}
