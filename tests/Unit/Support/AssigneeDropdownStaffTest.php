<?php

namespace Tests\Unit\Support;

use App\Models\Branch;
use App\Models\Staff;
use App\Support\AssigneeDropdownStaff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;

class AssigneeDropdownStaffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Once::flush();
        $this->createSchema();
    }

    public function test_loads_active_staff_with_office_in_two_queries_and_memoizes(): void
    {
        $office = Branch::query()->create([
            'office_name' => 'Adelaide',
        ]);

        Staff::query()->create([
            'first_name' => 'Ann',
            'last_name' => 'Active',
            'email' => 'ann-active@test.com',
            'password' => Hash::make('password'),
            'role' => 2,
            'status' => 1,
            'office_id' => $office->id,
        ]);
        Staff::query()->create([
            'first_name' => 'Inactive',
            'last_name' => 'Staff',
            'email' => 'inactive-staff@test.com',
            'password' => Hash::make('password'),
            'role' => 2,
            'status' => 0,
            'office_id' => $office->id,
        ]);

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $first = AssigneeDropdownStaff::activeWithOffice();
        $afterFirst = $queryCount;
        $second = AssigneeDropdownStaff::activeWithOffice();

        $this->assertSame(1, $first->count());
        $this->assertSame('Ann', $first->first()->first_name);
        $this->assertSame('Adelaide', $first->first()->office?->office_name);
        $this->assertSame($first, $second);
        $this->assertSame(2, $afterFirst, 'Expected one staff query and one office eager load');
        $this->assertSame($afterFirst, $queryCount, 'Request memoization should not query again');
    }

    private function createSchema(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('office_name')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('role')->nullable();
            $table->unsignedInteger('status')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->timestamps();
        });
    }
}
