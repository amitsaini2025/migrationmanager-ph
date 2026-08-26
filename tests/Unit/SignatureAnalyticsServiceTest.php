<?php

namespace Tests\Unit;

use App\Services\SignatureAnalyticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignatureAnalyticsServiceTest extends TestCase
{
    private SignatureAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->service = new SignatureAnalyticsService;
    }

    #[Test]
    public function median_time_to_sign_uses_last_signer_without_loading_all_documents(): void
    {
        $this->insertStaff(1, 'Pat', 'Owner');

        $firstCreated = Carbon::parse('2026-08-24 00:00:00');
        $firstSigned = Carbon::parse('2026-08-24 05:00:00');
        $secondCreated = Carbon::parse('2026-08-23 00:00:00');
        $secondSigned = Carbon::parse('2026-08-23 10:00:00');

        $this->insertDocument(1, 1, 'signed', $firstCreated);
        $this->insertSigner(1, 1, 'signed', $firstCreated, $firstSigned);

        $this->insertDocument(2, 1, 'signed', $secondCreated);
        $this->insertSigner(2, 2, 'signed', $secondCreated, $secondSigned);

        $median = $this->service->getMedianTimeToSign();

        $this->assertEqualsWithDelta(7.5, $median, 0.05);
    }

    #[Test]
    public function document_type_stats_ignore_non_signature_uploads(): void
    {
        $this->insertStaff(1, 'Pat', 'Owner');

        $this->insertDocument(1, 1, 'signed', now()->subDay());
        $this->insertSigner(1, 1, 'signed', now()->subDay(), now()->subHours(12));

        $this->insertDocument(2, 1, 'sent', now()->subDay());
        $this->insertSigner(2, 2, 'pending', now()->subDay(), null);

        DB::table('documents')->insert([
            'id' => 99,
            'file_name' => 'client-upload.pdf',
            'status' => 'signed',
            'created_by' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $stats = $this->service->getDocumentTypeStats();
        $row = $stats->first();

        $this->assertSame('general', $row->document_type);
        $this->assertSame(2, $row->total);
        $this->assertSame(1, $row->signed);
        $this->assertSame(1, $row->pending);
        $this->assertSame(50.0, $row->completion_rate);
    }

    #[Test]
    public function completion_rate_includes_the_end_date(): void
    {
        $this->insertStaff(1, 'Pat', 'Owner');

        $this->insertDocument(1, 1, 'signed', now()->startOfDay()->addHours(18));
        $this->insertDocument(2, 1, 'sent', now()->startOfDay()->addHours(18));

        $rate = $this->service->getCompletionRate(
            now()->toDateString(),
            now()->toDateString()
        );

        $this->assertSame(50.0, $rate);
    }

    #[Test]
    public function user_performance_does_not_query_once_per_staff_member(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->insertStaff($i, 'Staff', (string) $i);
            $this->insertDocument($i, $i, 'signed', now()->subDays(2));
            $this->insertSigner($i, $i, 'signed', now()->subDays(2), now()->subDay());
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $performance = $this->service->getUserPerformance();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $performance);
        $this->assertSame(1, $performance->first()['total_sent']);
        $this->assertLessThan(
            10,
            $queryCount,
            "Expected a handful of aggregate queries, got {$queryCount}."
        );
    }

    #[Test]
    public function top_signers_and_trends_run_on_sqlite(): void
    {
        $this->insertStaff(1, 'Pat', 'Owner');
        $this->insertDocument(1, 1, 'signed', now()->subHours(3));
        $this->insertSigner(1, 1, 'signed', now()->subHours(3), now()->subHour(), 'sam@example.com', 'Sam Signer');

        $signers = $this->service->getTopSigners(5);
        $this->assertSame('sam@example.com', $signers->first()->email);
        $this->assertSame(1, (int) $signers->first()->completed_count);

        $trend = $this->service->getSignatureTrend(
            now()->subDay()->toDateString(),
            now()->toDateString(),
            'day'
        );

        $this->assertNotEmpty($trend['labels']);
        $this->assertSame(array_sum($trend['signed']), 1);
    }

    #[Test]
    public function archived_and_non_workflow_documents_are_excluded_from_dashboard_stats(): void
    {
        $this->insertStaff(1, 'Pat', 'Owner');

        $this->insertDocument(1, 1, 'signed', now()->subDay());
        $this->insertDocument(2, 1, 'archived', now()->subDay());
        DB::table('documents')->insert([
            'id' => 3,
            'file_name' => 'orphan.pdf',
            'status' => 'sent',
            'created_by' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $stats = $this->service->getDashboardStats(1);

        $this->assertSame(1, $stats['signed']);
        $this->assertSame(1, $stats['total_sent']);
        $this->assertSame(0, $stats['pending']);
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('signers');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('staff');

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('signers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamps();
        });
    }

    private function insertStaff(int $id, string $firstName, string $lastName): void
    {
        DB::table('staff')->insert([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => "staff{$id}@example.com",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertDocument(int $id, int $createdBy, string $status, $createdAt): void
    {
        DB::table('documents')->insert([
            'id' => $id,
            'file_name' => "doc-{$id}.pdf",
            'status' => $status,
            'created_by' => $createdBy,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertSigner(
        int $id,
        int $documentId,
        string $status,
        $createdAt,
        $signedAt,
        string $email = 'signer@example.com',
        string $name = 'Signer'
    ): void {
        DB::table('signers')->insert([
            'id' => $id,
            'document_id' => $documentId,
            'email' => $email,
            'name' => $name,
            'status' => $status,
            'signed_at' => $signedAt,
            'reminder_count' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
