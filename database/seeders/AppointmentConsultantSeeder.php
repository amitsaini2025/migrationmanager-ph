<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\AppointmentConsultant;
use App\Models\BookingAppointment;

class AppointmentConsultantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Map old IDs to new IDs before deletion
        // This will help us update appointments that reference old consultant IDs
        $idMapping = [
            6 => 1,  // Arun Kumar (paid) - old ID 6 -> new ID 1
            7 => 2,  // Shubham/Yadwinder (jrp) - old ID 7 -> new ID 2
            8 => 3,  // Education Team - old ID 8 -> new ID 3
            9 => 4,  // Tourist Visa Team - old ID 9 -> new ID 4
            10 => 5, // Adelaide Office - old ID 10 -> new ID 5
            11 => 6, // Ajay Calendar - old ID 11 -> new ID 6
            12 => 7, // Kunal Calendar - old ID 12 -> new ID 7 (if exists)
        ];
        
        // Update appointments to use temporary IDs (1000+) to avoid conflicts
        foreach ($idMapping as $oldId => $newId) {
            BookingAppointment::where('consultant_id', $oldId)
                ->update(['consultant_id' => 1000 + $newId]);
        }
        
        // Delete all existing consultants
        DB::table('appointment_consultants')->truncate();
        
        // Reset the auto-increment sequence for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER SEQUENCE appointment_consultants_id_seq RESTART WITH 1");
        } else {
            // For MySQL, reset auto increment
            DB::statement("ALTER TABLE appointment_consultants AUTO_INCREMENT = 1");
        }
        
        $consultants = [
            [
                'name' => 'Arun Kumar (Employer Sponsored Calendar)',
                'email' => 'arun@bansalimmigration.com',
                'calendar_type' => 'paid',
                'location' => 'melbourne',
                'specializations' => json_encode([1, 6, 7, 8]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Shubham/Yadwinder (JRP)',
                'email' => 'shubham@bansalimmigration.com',
                'calendar_type' => 'jrp',
                'location' => 'melbourne',
                'specializations' => json_encode([2, 3]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Education Team',
                'email' => 'education@bansalimmigration.com',
                'calendar_type' => 'education',
                'location' => 'melbourne',
                'specializations' => json_encode([5]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Tourist Visa Team',
                'email' => 'tourist@bansalimmigration.com',
                'calendar_type' => 'tourist',
                'location' => 'melbourne',
                'specializations' => json_encode([4]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Adelaide Office',
                'email' => 'adelaide@bansalimmigration.com',
                'calendar_type' => 'adelaide',
                'location' => 'adelaide',
                'specializations' => json_encode([1, 2, 3, 4, 5, 6, 7, 8]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Ajay Calendar',
                'email' => 'ajay@bansalimmigration.com',
                'calendar_type' => 'ajay',
                'location' => 'melbourne',
                'specializations' => json_encode([]), // Transfer-only calendar, no specializations needed
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Kunal Calendar',
                'email' => 'kunal@bansalimmigration.com',
                'calendar_type' => 'kunal',
                'location' => 'melbourne',
                'specializations' => json_encode([]),
                'is_active' => false,
                'show_in_filter' => false,
            ],
            [
                'name' => 'Arun Calendar',
                'email' => null,
                'calendar_type' => 'arun',
                'location' => 'melbourne',
                'specializations' => json_encode([]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
            [
                'name' => 'Adelaide Education',
                'email' => null,
                'calendar_type' => 'adelaide_education',
                'location' => 'adelaide',
                'specializations' => json_encode([4, 5]),
                'is_active' => true,
                'show_in_filter' => true,
            ],
        ];

        // Insert consultants in order so they get IDs 1-9 (append-only so legacy idMapping for 1-8 stays valid)
        foreach ($consultants as $index => $consultant) {
            AppointmentConsultant::create([
                'id' => $index + 1, // Explicitly set ID: 1, 2, 3, 4, 5, 6, 7, 8, 9
                'name' => $consultant['name'],
                'email' => $consultant['email'],
                'calendar_type' => $consultant['calendar_type'],
                'location' => $consultant['location'],
                'specializations' => json_decode($consultant['specializations'], true),
                'is_active' => $consultant['is_active'],
                'show_in_filter' => $consultant['show_in_filter'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update appointments back to correct new IDs
        foreach ($idMapping as $oldId => $newId) {
            BookingAppointment::where('consultant_id', 1000 + $newId)
                ->update(['consultant_id' => $newId]);
        }
        
        $this->command->info('✓ Created 8 appointment consultants with IDs 1-8 (including Ajay, Kunal, and Arun Calendar)');
        $this->command->info('✓ Updated all appointments to reference new consultant IDs');
    }
}

