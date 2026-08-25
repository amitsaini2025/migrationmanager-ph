<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Support\ClientDetailDocumentsTab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @property mixed $app
 */
class ClientDetailDocumentsTabTest extends TestCase
{
    #[Test]
    public function personal_documents_are_grouped_by_folder_with_eager_staff(): void
    {
        $this->createDocumentsSchema();

        DB::table('staff')->insert([
            'id' => 5,
            'first_name' => 'Pat',
            'last_name' => 'Uploader',
            'email' => 'pat@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insert([
            [
                'client_id' => 10,
                'user_id' => 5,
                'doc_type' => 'personal',
                'type' => 'client',
                'folder_name' => '1',
                'file_name' => 'a.pdf',
                'checklist' => 'Passport',
                'not_used_doc' => null,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'client_id' => 10,
                'user_id' => 5,
                'doc_type' => 'personal',
                'type' => 'client',
                'folder_name' => '2',
                'file_name' => 'b.pdf',
                'checklist' => 'Resume',
                'not_used_doc' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_id' => 10,
                'user_id' => 5,
                'doc_type' => 'personal',
                'type' => 'client',
                'folder_name' => '1',
                'file_name' => null,
                'checklist' => 'Unused checklist',
                'not_used_doc' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $byFolder = ClientDetailDocumentsTab::personalDocumentsByFolder(10);

        Assert::assertTrue($byFolder->has('1'));
        Assert::assertTrue($byFolder->has('2'));
        Assert::assertCount(1, $byFolder->get('1'));
        Assert::assertCount(1, $byFolder->get('2'));

        /** @var Document $doc */
        $doc = $byFolder->get('1')->first();
        Assert::assertTrue($doc->relationLoaded('staff'));
        Assert::assertSame('Pat', $doc->staff->first_name);
    }

    #[Test]
    public function visa_documents_eager_load_staff_and_signers(): void
    {
        $this->createDocumentsSchema();

        DB::table('staff')->insert([
            'id' => 7,
            'first_name' => 'Visa',
            'last_name' => 'Staff',
            'email' => 'visa@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insert([
            'client_id' => 11,
            'user_id' => 7,
            'doc_type' => 'visa',
            'type' => 'client',
            'folder_name' => '3',
            'file_name' => 'form.pdf',
            'checklist' => 'Form 80',
            'not_used_doc' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $byFolder = ClientDetailDocumentsTab::visaDocumentsByFolder(11);
        Assert::assertCount(1, $byFolder->get('3'));

        /** @var Document $doc */
        $doc = $byFolder->get('3')->first();
        Assert::assertTrue($doc->relationLoaded('staff'));
        Assert::assertTrue($doc->relationLoaded('signers'));
        Assert::assertSame('Visa', $doc->staff->first_name);
    }

    #[Test]
    public function not_used_documents_eager_load_staff(): void
    {
        $this->createDocumentsSchema();

        DB::table('staff')->insert([
            'id' => 9,
            'first_name' => 'Not',
            'last_name' => 'Used',
            'email' => 'notused@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insert([
            'client_id' => 12,
            'user_id' => 9,
            'doc_type' => 'personal',
            'type' => 'client',
            'folder_name' => '1',
            'file_name' => 'old.pdf',
            'checklist' => 'Old',
            'not_used_doc' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docs = ClientDetailDocumentsTab::notUsedDocuments(12);
        Assert::assertCount(1, $docs);
        Assert::assertTrue($docs->first()->relationLoaded('staff'));
        Assert::assertSame('Not', $docs->first()->staff->first_name);
    }

    #[Test]
    public function document_tab_blades_do_not_run_per_row_staff_lookups_or_per_category_document_queries(): void
    {
        foreach ([
            'resources/views/crm/clients/tabs/personal_documents.blade.php',
            'resources/views/crm/clients/tabs/visa_documents.blade.php',
            'resources/views/crm/clients/tabs/not_used_documents.blade.php',
        ] as $relative) {
            $contents = file_get_contents($this->projectPath($relative));
            Assert::assertNotFalse($contents);
            Assert::assertStringContainsString('ClientDetailDocumentsTab::', $contents);
            if (str_contains($contents, 'class="grid_data')) {
                Assert::assertMatchesRegularExpression(
                    '/class="grid_data[^"]*"[^>]*style="display:\s*none;"/',
                    $contents,
                    $relative.' must hide .grid_data by default so the checklist list view is not squeezed.'
                );
            }
            Assert::assertStringNotContainsString('Staff::where', $contents);
            Assert::assertStringNotContainsString('Staff::find', $contents);
            Assert::assertStringNotContainsString('Admin::find', $contents);
            Assert::assertStringNotContainsString('Admin::where', $contents);
            Assert::assertStringNotContainsString('\\App\\Models\\Document::', $contents);
        }
    }

    private function createDocumentsSchema(): void
    {
        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->string('doc_type')->nullable();
                $table->string('type')->nullable();
                $table->string('folder_name')->nullable();
                $table->string('file_name')->nullable();
                $table->string('checklist')->nullable();
                $table->string('myfile')->nullable();
                $table->string('status')->nullable();
                $table->unsignedTinyInteger('not_used_doc')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signature_activities')) {
            Schema::create('signature_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signers')) {
            Schema::create('signers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->timestamps();
            });
        }
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
