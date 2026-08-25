<?php

namespace Tests\Unit;

use App\Support\ClientDetailChecklistsTab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @property mixed $app
 */
class ClientDetailChecklistsTabTest extends TestCase
{
    #[Test]
    public function build_returns_staff_office_and_matter_dropdown_payloads(): void
    {
        $this->createChecklistsTabSchema();

        DB::table('staff')->insert([
            [
                'id' => 10,
                'first_name' => 'Mig',
                'last_name' => 'Agent',
                'email' => 'mig@example.com',
                'role' => 16,
                'status' => 1,
                'is_migration_agent' => 1,
                'office_id' => 1,
            ],
            [
                'id' => 11,
                'first_name' => 'Person',
                'last_name' => 'Responsible',
                'email' => 'pr@example.com',
                'role' => 12,
                'status' => 1,
                'is_migration_agent' => 0,
                'office_id' => 1,
            ],
            [
                'id' => 12,
                'first_name' => 'Person',
                'last_name' => 'Assisting',
                'email' => 'pa@example.com',
                'role' => 13,
                'status' => 1,
                'is_migration_agent' => 0,
                'office_id' => 1,
            ],
        ]);

        DB::table('branches')->insert([
            'id' => 1,
            'office_name' => 'Melbourne',
        ]);

        DB::table('matters')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'is_for_company' => false,
        ]);

        $payload = ClientDetailChecklistsTab::build((object) [
            'id' => 1,
            'client_id' => 'C001',
            'is_company' => 0,
        ]);

        Assert::assertNull($payload['checklistCurrentMatterId']);
        Assert::assertFalse($payload['checklistCurrentMatterNeedsCostAssignment']);
        Assert::assertNotEmpty($payload['checklistMigrationAgents']);
        Assert::assertNotEmpty($payload['checklistPersonsResponsible']);
        Assert::assertNotEmpty($payload['checklistPersonsAssisting']);
        Assert::assertNotEmpty($payload['checklistOffices']);
        Assert::assertNotEmpty($payload['checklistMatterList']);
        Assert::assertEmpty($payload['checklistForms']);
        Assert::assertSame('Melbourne', $payload['checklistOffices']->first()->office_name);
    }

    #[Test]
    public function build_flags_matter_needing_cost_assignment_when_form_missing(): void
    {
        $this->createChecklistsTabSchema();

        DB::table('client_matters')->insert([
            'id' => 5,
            'client_id' => 1,
            'client_unique_matter_no' => 'APC_5',
            'matter_status' => 1,
        ]);

        $payload = ClientDetailChecklistsTab::build((object) [
            'id' => 1,
            'client_id' => 'C001',
            'is_company' => 0,
        ], 'APC_5');

        Assert::assertSame(5, $payload['checklistCurrentMatterId']);
        Assert::assertSame('APC_5', $payload['checklistCurrentMatterRef']);
        Assert::assertTrue($payload['checklistCurrentMatterNeedsCostAssignment']);
    }

    private function createChecklistsTabSchema(): void
    {
        $schema = $this->app->make('db')->connection()->getSchemaBuilder();

        if (! $schema->hasTable('staff')) {
            $schema->create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedInteger('role')->nullable();
                $table->unsignedInteger('status')->nullable();
                $table->unsignedTinyInteger('is_migration_agent')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('branches')) {
            $schema->create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('office_name')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('matters')) {
            $schema->create('matters', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->unsignedTinyInteger('status')->nullable();
                $table->boolean('is_for_company')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('client_matters')) {
            $schema->create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('client_unique_matter_no')->nullable();
                $table->unsignedInteger('matter_status')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('cost_assignment_forms')) {
            $schema->create('cost_assignment_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->unsignedBigInteger('agent_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('documents')) {
            $schema->create('documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->string('doc_type')->nullable();
                $table->string('myfile')->nullable();
                $table->timestamps();
            });
        }
    }
}
