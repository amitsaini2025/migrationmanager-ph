<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackStaffCrmActivity;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Note;
use App\Models\PersonalDocumentType;
use App\Models\Staff;
use App\Models\VisaDocumentType;
use App\Support\ClientDetailDocumentsTab;
use App\Support\ClientDetailTabs;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HTTP contract for each lazy client-detail tab fragment: 200 + pane DOM id.
 */
class ClientDetailTabFragmentTest extends TestCase
{
    protected Staff $staff;

    protected Admin $client;

    protected string $encodedClientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            TrackStaffCrmActivity::class,
        ]);

        if (method_exists($this, 'withoutVite')) {
            $this->withoutVite();
        }

        $this->createFragmentSchema();

        $this->staff = Staff::create([
            'first_name' => 'Fragment',
            'last_name' => 'Tester',
            'email' => 'client-detail-fragment@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);

        $this->client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'Lazy',
            'last_name' => 'Tab',
            'email' => 'lazy.tab@test.com',
            'is_company' => 0,
            'is_deleted' => null,
            'cp_status' => 0,
        ]);

        $this->encodedClientId = base64_encode(convert_uuencode((string) $this->client->id));
    }

    #[Test]
    public function workflow_tab_fragment_returns_200_and_workflow_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['workflow'],
            ClientDetailTabs::paneId('workflow')
        );
    }

    #[Test]
    public function client_portal_tab_fragment_returns_200_and_client_portal_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['client_portal'],
            ClientDetailTabs::paneId('client_portal')
        );
    }

    #[Test]
    public function account_tab_fragment_returns_200_and_account_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['account'],
            ClientDetailTabs::paneId('account')
        );
    }

    #[Test]
    public function checklists_tab_fragment_returns_200_and_checklists_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['checklists'],
            ClientDetailTabs::paneId('checklists')
        );

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['checklists'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="checklist_migration_agent"', false);
        $response->assertSee('id="btn-add-checklist"', false);
        $response->assertSee('id="checklist-create-dropdown"', false);
    }

    #[Test]
    public function emails_tab_fragment_returns_200_and_emails_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['emails'],
            ClientDetailTabs::paneId('emails')
        );

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['emails'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="emails-tab"', false);
        $response->assertSee('email-interface-container', false);
        $response->assertSee('id="folder-tab-inbox"', false);
        $response->assertSee('id="folder-tab-sent"', false);
        $response->assertSee('data-matter-id', false);
        $response->assertSee('id="mailTypeFilter"', false);
    }

    #[Test]
    public function personal_documents_tab_fragment_returns_200_and_personal_documents_pane_id(): void
    {
        $identity = PersonalDocumentType::create([
            'title' => 'Identity',
            'status' => 1,
            'type' => 'personal',
        ]);
        $education = PersonalDocumentType::create([
            'title' => 'Education',
            'status' => 1,
            'type' => 'personal',
        ]);

        Document::create([
            'client_id' => $this->client->id,
            'user_id' => $this->staff->id,
            'doc_type' => 'personal',
            'type' => 'client',
            'folder_name' => (string) $identity->id,
            'checklist' => 'Passport copy',
            'file_name' => 'passport.pdf',
            'myfile' => 'https://example.test/passport.pdf',
        ]);
        Document::create([
            'client_id' => $this->client->id,
            'user_id' => $this->staff->id,
            'doc_type' => 'personal',
            'type' => 'client',
            'folder_name' => (string) $education->id,
            'checklist' => 'Degree scan',
            'file_name' => 'degree.pdf',
            'myfile' => 'https://example.test/degree.pdf',
        ]);

        $grouped = ClientDetailDocumentsTab::personalDocumentsByFolder((int) $this->client->id);
        $this->assertTrue($grouped->has((string) $identity->id));
        $this->assertTrue($grouped->has((string) $education->id));
        $this->assertSame(1, $grouped->get((string) $identity->id)->count());
        $this->assertTrue($grouped->get((string) $identity->id)->first()->relationLoaded('staff'));

        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['personaldocuments'],
            ClientDetailTabs::paneId('personaldocuments')
        );

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['personaldocuments'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="personaldocuments-tab"', false);
        $response->assertSee('id="personal-documents-content"', false);
        $response->assertSee('data-tab="notuseddocuments"', false);
        $response->assertSee('Checklist', false);
        $response->assertSee('File Name', false);
        $response->assertSee('Passport copy', false);
        $response->assertSee('Degree scan', false);
        $response->assertSee('Uploaded by: Fragment', false);
        $response->assertSee('class="grid_data', false);
        $response->assertSee('style="display:none;"', false);
    }

    #[Test]
    public function visa_documents_tab_fragment_returns_200_and_visa_documents_pane_id(): void
    {
        $visaCat = VisaDocumentType::create([
            'title' => 'Passport',
            'status' => 1,
        ]);

        Document::create([
            'client_id' => $this->client->id,
            'user_id' => $this->staff->id,
            'doc_type' => 'visa',
            'type' => 'client',
            'folder_name' => (string) $visaCat->id,
            'checklist' => 'Visa grant',
            'file_name' => 'grant.pdf',
            'myfile' => 'https://example.test/grant.pdf',
        ]);

        $grouped = ClientDetailDocumentsTab::visaDocumentsByFolder((int) $this->client->id);
        $this->assertTrue($grouped->has((string) $visaCat->id));
        $this->assertTrue($grouped->get((string) $visaCat->id)->first()->relationLoaded('staff'));
        $this->assertTrue($grouped->get((string) $visaCat->id)->first()->relationLoaded('signers'));

        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['visadocuments'],
            ClientDetailTabs::paneId('visadocuments')
        );

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['visadocuments'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="visadocuments-tab"', false);
        $response->assertSee('id="visa-documents-content"', false);
        $response->assertSee('data-tab="notuseddocuments"', false);
        $response->assertSee('Visa grant', false);
        $response->assertSee('Uploaded by: Fragment', false);
    }

    #[Test]
    public function not_used_documents_tab_fragment_returns_200_and_not_used_pane_id(): void
    {
        Document::create([
            'client_id' => $this->client->id,
            'user_id' => $this->staff->id,
            'doc_type' => 'personal',
            'type' => 'client',
            'not_used_doc' => 1,
            'folder_name' => '1',
            'checklist' => 'Old passport',
            'file_name' => 'old.pdf',
            'myfile' => 'https://example.test/old.pdf',
            'myfile_key' => 'old.pdf',
        ]);

        $rows = ClientDetailDocumentsTab::notUsedDocuments((int) $this->client->id);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->relationLoaded('staff'));

        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['notuseddocuments'],
            ClientDetailTabs::paneId('notuseddocuments')
        );

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['notuseddocuments'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="notuseddocuments-tab"', false);
        $response->assertSee('notuseddocumnetlist', false);
        $response->assertSee('id="notUsedFileContextMenu"', false);
        $response->assertSee('backtodoc', false);
        $response->assertSee('Old passport', false);
        $response->assertSee('Uploaded by: Fragment', false);
    }

    #[Test]
    public function notes_tab_fragment_returns_200_and_notes_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['noteterm'],
            ClientDetailTabs::paneId('noteterm')
        );

        Note::create([
            'client_id' => $this->client->id,
            'user_id' => $this->staff->id,
            'type' => 'client',
            'title' => 'Pin check',
            'description' => 'Confirm pin remains after lazy load',
            'pin' => 0,
            'task_group' => 'Call',
            'matter_id' => 3,
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route(ClientDetailTabs::fragmentRouteNames()['noteterm'], [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertSee('id="noteterm-tab"', false);
        $response->assertSee('notes-container', false);
        $response->assertSee('note_term_list', false);
        $response->assertSee('create_note_d', false);
        $response->assertSee('data-subtab8="All"', false);
        $response->assertSee('data-notes-scope="matter"', false);
        $response->assertSee('window.filterNotes', false);
        $response->assertSee('class="dropdown-item pinnote"', false);
        $response->assertSee('data-matterid="3"', false);
        $response->assertSee('sel_matter_id_client_detail', false);
    }

    private function assertFragmentOk(string $routeName, string $paneId): void
    {
        $withoutMatter = $this->actingAs($this->staff, 'admin')
            ->get(route($routeName, ['client_id' => $this->encodedClientId]));

        $withoutMatter->assertOk();
        $withoutMatter->assertSee('id="'.$paneId.'"', false);

        $withMatter = $this->actingAs($this->staff, 'admin')
            ->get(route($routeName, [
                'client_id' => $this->encodedClientId,
                'client_unique_matter_ref_no' => 'APC_3',
            ]));

        $withMatter->assertOk();
        $withMatter->assertSee('id="'.$paneId.'"', false);
    }

    private function createFragmentSchema(): void
    {
        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->unsignedInteger('role')->nullable();
                $table->unsignedInteger('status')->nullable();
                $table->unsignedTinyInteger('is_migration_agent')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->string('type')->nullable();
                $table->string('country')->nullable();
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->string('zip')->nullable();
                $table->integer('status')->nullable();
                $table->date('dob')->nullable();
                $table->unsignedInteger('is_deleted')->nullable();
                $table->unsignedInteger('is_archived')->nullable();
                $table->unsignedTinyInteger('is_company')->nullable();
                $table->unsignedTinyInteger('cp_status')->nullable();
                $table->string('client_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('admins', 'is_company')) {
                Schema::table('admins', function (Blueprint $table) {
                    $table->unsignedTinyInteger('is_company')->nullable();
                });
            }
            if (! Schema::hasColumn('admins', 'cp_status')) {
                Schema::table('admins', function (Blueprint $table) {
                    $table->unsignedTinyInteger('cp_status')->nullable();
                });
            }
        }

        if (! Schema::hasTable('matters')) {
            Schema::create('matters', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->integer('status')->nullable();
                $table->boolean('is_for_company')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('matters', 'status')) {
                Schema::table('matters', function (Blueprint $table) {
                    $table->integer('status')->nullable();
                });
            }
            if (! Schema::hasColumn('matters', 'is_for_company')) {
                Schema::table('matters', function (Blueprint $table) {
                    $table->boolean('is_for_company')->nullable();
                });
            }
        }

        if (! Schema::hasTable('client_matters')) {
            Schema::create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('sel_matter_id')->nullable();
                $table->string('client_unique_matter_no')->nullable();
                $table->unsignedBigInteger('workflow_id')->nullable();
                $table->unsignedBigInteger('workflow_stage_id')->nullable();
                $table->integer('matter_status')->nullable();
                $table->date('deadline')->nullable();
                $table->unsignedBigInteger('sel_migration_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('workflow_stages')) {
            Schema::create('workflow_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->nullable();
                $table->string('name')->nullable();
                $table->unsignedInteger('sort_order')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_emails')) {
            Schema::create('client_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_addresses')) {
            Schema::create('client_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->date('start_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_passport_informations')) {
            Schema::create('client_passport_informations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_visa_countries')) {
            Schema::create('client_visa_countries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('visa_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_travel_informations')) {
            Schema::create('client_travel_informations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->date('travel_arrival_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_qualifications')) {
            Schema::create('client_qualifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->date('finish_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_experiences')) {
            Schema::create('client_experiences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->date('job_start_date')->nullable();
                $table->date('job_finish_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_occupations')) {
            Schema::create('client_occupations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_testscore')) {
            Schema::create('client_testscore', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_client_receipts')) {
            Schema::create('account_client_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->unsignedBigInteger('receipt_id')->nullable();
                $table->unsignedTinyInteger('receipt_type')->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->decimal('withdraw_amount', 12, 2)->nullable();
                $table->decimal('balance_amount', 12, 2)->nullable();
                $table->unsignedTinyInteger('invoice_status')->nullable();
                $table->unsignedTinyInteger('void_fee_transfer')->nullable();
                $table->string('invoice_no')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->string('myfile')->nullable();
                $table->string('doc_type')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('documents', 'doc_type')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->string('doc_type')->nullable();
                });
            }
            if (! Schema::hasColumn('documents', 'client_matter_id')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->unsignedBigInteger('client_matter_id')->nullable();
                });
            }
        }

        foreach ([
            'client_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'not_used_doc' => 'unsignedTinyInteger',
            'type' => 'string',
            'folder_name' => 'string',
            'file_name' => 'string',
            'myfile_key' => 'string',
            'checklist' => 'string',
            'status' => 'string',
        ] as $column => $kind) {
            if (! Schema::hasColumn('documents', $column)) {
                Schema::table('documents', function (Blueprint $table) use ($column, $kind) {
                    if ($kind === 'unsignedBigInteger') {
                        $table->unsignedBigInteger($column)->nullable();
                    } elseif ($kind === 'unsignedTinyInteger') {
                        $table->unsignedTinyInteger($column)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                });
            }
        }

        if (! Schema::hasTable('personal_document_types')) {
            Schema::create('personal_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedInteger('status')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visa_document_types')) {
            Schema::create('visa_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedInteger('status')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomination_document_types')) {
            Schema::create('nomination_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signers')) {
            Schema::create('signers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('email')->nullable();
                $table->string('name')->nullable();
                $table->string('token')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('office_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cost_assignment_forms')) {
            Schema::create('cost_assignment_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->unsignedBigInteger('agent_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->string('type')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->unsignedTinyInteger('pin')->nullable();
                $table->string('task_group')->nullable();
                $table->timestamps();
            });
        }
    }
}
