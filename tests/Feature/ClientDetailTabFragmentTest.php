<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackStaffCrmActivity;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Staff;
use App\Support\ClientDetailTabs;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;
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

        $this->staff = Staff::firstOrCreate(
            ['email' => 'client-detail-fragment@test.com'],
            [
                'first_name' => 'Fragment',
                'last_name' => 'Tester',
                'password' => Hash::make('password'),
                'role' => 1,
                'status' => 1,
            ]
        );

        $this->client = Admin::firstOrCreate(
            ['email' => 'lazy.tab@test.com'],
            [
                'type' => 'client',
                'first_name' => 'Lazy',
                'last_name' => 'Tab',
                'is_company' => 0,
                'is_deleted' => null,
                'cp_status' => 0,
            ]
        );

        $this->encodedClientId = base64_encode(convert_uuencode((string) $this->client->id));
    }

    #[Test]
    public function reserved_slugs_match_the_existing_client_detail_url_contract(): void
    {
        Assert::assertSame([
            'personaldetails',
            'companydetails',
            'activityfeed',
            'noteterm',
            'personaldocuments',
            'visadocuments',
            'nominationdocuments',
            'eoiroi',
            'emails',
            'client_portal',
            'formgenerations',
            'formgenerationsl',
            'workflow',
            'checklists',
            'account',
            'notuseddocuments',
        ], ClientDetailTabs::slugs());
    }

    #[Test]
    public function fragment_route_names_cover_registered_lazy_tabs_only(): void
    {
        Assert::assertSame([
            'workflow' => 'clients.detail.workflow-tab',
            'client_portal' => 'clients.detail.client-portal-tab',
            'account' => 'clients.detail.account-tab',
            'checklists' => 'clients.detail.checklists-tab',
            'emails' => 'clients.detail.emails-tab',
            'personaldocuments' => 'clients.detail.personaldocuments-tab',
            'visadocuments' => 'clients.detail.visadocuments-tab',
            'notuseddocuments' => 'clients.detail.notuseddocuments-tab',
            'noteterm' => 'clients.detail.noteterm-tab',
        ], ClientDetailTabs::fragmentRouteNames());
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
    }

    #[Test]
    public function emails_tab_fragment_returns_200_and_emails_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['emails'],
            ClientDetailTabs::paneId('emails')
        );
    }

    #[Test]
    public function personal_documents_tab_fragment_returns_200_and_personaldocuments_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['personaldocuments'],
            ClientDetailTabs::paneId('personaldocuments')
        );
    }

    #[Test]
    public function visa_documents_tab_fragment_returns_200_and_visadocuments_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['visadocuments'],
            ClientDetailTabs::paneId('visadocuments')
        );
    }

    #[Test]
    public function not_used_documents_tab_fragment_returns_200_and_notuseddocuments_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['notuseddocuments'],
            ClientDetailTabs::paneId('notuseddocuments')
        );
    }

    #[Test]
    public function notes_tab_fragment_returns_200_and_noteterm_pane_id(): void
    {
        $this->assertFragmentOk(
            ClientDetailTabs::fragmentRouteNames()['noteterm'],
            ClientDetailTabs::paneId('noteterm')
        );
    }

    private function assertFragmentOk(string $routeName, string $paneId): void
    {
        $response = $this->actingAs($this->staff, 'admin')
            ->get(route($routeName, [
                'client_id' => $this->encodedClientId,
            ]));

        $response->assertOk();
        $response->assertSee('id="'.$paneId.'"', false);
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
                $table->string('marn_number')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('staff', 'is_migration_agent')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->unsignedTinyInteger('is_migration_agent')->nullable();
                });
            }
            if (! Schema::hasColumn('staff', 'office_id')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->unsignedBigInteger('office_id')->nullable();
                });
            }
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
                $table->unsignedTinyInteger('status')->nullable();
                $table->boolean('is_for_company')->nullable();
                $table->timestamps();
            });
        } else {
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
                $table->unsignedTinyInteger('matter_status')->nullable();
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
                $table->unsignedInteger('receipt_type')->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->decimal('withdraw_amount', 12, 2)->nullable();
                $table->decimal('balance_amount', 12, 2)->nullable();
                $table->unsignedInteger('void_fee_transfer')->nullable();
                $table->unsignedInteger('invoice_status')->nullable();
                $table->string('invoice_no')->nullable();
                $table->unsignedBigInteger('uploaded_doc_id')->nullable();
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

        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
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
        } else {
            foreach ([
                'client_id' => 'unsignedBigInteger',
                'user_id' => 'unsignedBigInteger',
                'type' => 'string',
                'folder_name' => 'string',
                'file_name' => 'string',
                'checklist' => 'string',
                'status' => 'string',
                'not_used_doc' => 'unsignedTinyInteger',
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
        }

        if (! Schema::hasTable('personal_document_types')) {
            Schema::create('personal_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedTinyInteger('status')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visa_document_types')) {
            Schema::create('visa_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedTinyInteger('status')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomination_document_types')) {
            Schema::create('nomination_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedTinyInteger('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('matters')) {
            Schema::create('matters', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('type')->nullable();
                $table->unsignedTinyInteger('pin')->nullable();
                $table->string('task_group')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('mobile_number')->nullable();
                $table->timestamps();
            });
        }
    }
}
