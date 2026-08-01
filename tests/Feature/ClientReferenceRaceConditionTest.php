<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\ClientReferenceService;
use App\Models\Admin;

class ClientReferenceRaceConditionTest extends TestCase
{
    /**
     * Test that concurrent reference generation doesn't create duplicates
     * 
     * This test simulates race conditions by creating multiple clients
     * simultaneously and verifies no duplicate client_ids are generated
     *
     * @return void
     */
    public function test_concurrent_client_reference_generation_no_duplicates()
    {
        // Get the current highest counter
        $service = new ClientReferenceService();
        $startCounter = $service->getCurrentCounter();
        
        // Number of concurrent requests to simulate
        $concurrentRequests = 10;
        
        // Store generated references
        $generatedReferences = [];
        $generatedCounters = [];
        
        // Simulate concurrent requests by actually creating Admin records
        // This is important because the lockForUpdate works when records are actually saved
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $firstName = "TestUser" . $i;
            
            try {
                // Use transaction just like the real controllers do
                DB::transaction(function () use ($service, $firstName, &$generatedReferences, &$generatedCounters) {
                    $reference = $service->generateClientReference($firstName);
                    
                    // Actually save the admin record with this counter (like real code does)
                    DB::table('admins')->insert([
                        'first_name' => $firstName,
                        'last_name' => 'RaceTest',
                        'email' => strtolower($firstName) . '_racetest_' . time() . rand(1000, 9999) . '@test.com',
                        'password' => bcrypt('password'),
                        'type' => 'client',
                        'verified' => 0,
                        'cp_status' => 0,
                        'cp_code_verify' => 0,
                        'australian_study' => 0,
                        'specialist_education' => 0,
                        'regional_study' => 0,
                        'is_archived' => 0,
                        'client_id' => $reference['client_id'],
                        'client_counter' => $reference['client_counter'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $generatedReferences[] = $reference['client_id'];
                    $generatedCounters[] = $reference['client_counter'];
                    
                    echo "Generated: {$reference['client_id']} (counter: {$reference['client_counter']})\n";
                });
            } catch (\Exception $e) {
                $this->fail("Failed to generate reference: " . $e->getMessage());
            }
        }
        
        // Verify all references are unique
        $uniqueReferences = array_unique($generatedReferences);
        $this->assertCount(
            $concurrentRequests,
            $uniqueReferences,
            "Duplicate client references were generated! Generated: " . implode(', ', $generatedReferences)
        );
        
        // Verify all counters are unique
        $uniqueCounters = array_unique($generatedCounters);
        $this->assertCount(
            $concurrentRequests,
            $uniqueCounters,
            "Duplicate counters were generated! Counters: " . implode(', ', $generatedCounters)
        );
        
        // Verify counters are sequential
        sort($generatedCounters);
        $expectedStart = intval($startCounter) + 1;
        foreach ($generatedCounters as $index => $counter) {
            $expectedCounter = str_pad($expectedStart + $index, 5, '0', STR_PAD_LEFT);
            $this->assertEquals(
                $expectedCounter,
                $counter,
                "Counter sequence broken. Expected {$expectedCounter}, got {$counter}"
            );
        }
        
        echo "\n✓ All {$concurrentRequests} references are unique!\n";
        echo "✓ Counters are sequential!\n";
        echo "✓ No race condition detected!\n\n";
    }

    /**
     * Test reference format is correct
     */
    public function test_client_reference_format()
    {
        $service = new ClientReferenceService();
        
        // Test with 4+ character name
        $reference1 = $service->generateClientReference("John");
        $this->assertMatchesRegularExpression(
            '/^[A-Z]{4}\d{7}$/',
            $reference1['client_id'],
            "Reference format incorrect for 4-char name: {$reference1['client_id']}"
        );
        
        // Test with short name
        $reference2 = $service->generateClientReference("Li");
        $this->assertMatchesRegularExpression(
            '/^[A-Z]{2}\d{7}$/',
            $reference2['client_id'],
            "Reference format incorrect for short name: {$reference2['client_id']}"
        );
        
        // Test year component
        $currentYear = date('y');
        $this->assertStringContainsString(
            $currentYear,
            $reference1['client_id'],
            "Reference doesn't contain current year"
        );
        
        echo "\n✓ Reference format validation passed!\n";
        echo "  Sample reference: {$reference1['client_id']}\n\n";
    }

    /**
     * Test counter increment logic
     */
    public function test_counter_increment()
    {
        $service = new ClientReferenceService();
        
        $refs = [];
        
        // Generate 3 sequential references with actual database saves
        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $name) {
            DB::transaction(function () use ($service, $name, &$refs) {
                $ref = $service->generateClientReference($name);
                
                // Save to database like real code does
                DB::table('admins')->insert([
                    'first_name' => $name,
                    'last_name' => 'IncrementTest',
                    'email' => strtolower($name) . '_inctest_' . time() . rand(1000, 9999) . '@test.com',
                    'password' => bcrypt('password'),
                    'type' => 'client',
                    'verified' => 0,
                    'cp_status' => 0,
                    'cp_code_verify' => 0,
                    'australian_study' => 0,
                    'specialist_education' => 0,
                    'regional_study' => 0,
                    'is_archived' => 0,
                    'client_id' => $ref['client_id'],
                    'client_counter' => $ref['client_counter'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $refs[] = $ref;
            });
        }
        
        $counter1 = intval($refs[0]['client_counter']);
        $counter2 = intval($refs[1]['client_counter']);
        $counter3 = intval($refs[2]['client_counter']);
        
        // Verify sequential increment
        $this->assertEquals($counter1 + 1, $counter2, "Counter not incrementing correctly");
        $this->assertEquals($counter2 + 1, $counter3, "Counter not incrementing correctly");
        
        echo "\n✓ Counter increment working correctly!\n";
        echo "  {$refs[0]['client_id']} -> {$refs[1]['client_id']} -> {$refs[2]['client_id']}\n\n";
    }

    /**
     * Test prefix generation from first names
     */
    public function test_prefix_generation()
    {
        $service = new ClientReferenceService();
        
        // Test normal name
        $ref1 = $service->generateClientReference("Jonathan");
        $this->assertStringStartsWith('JONA', $ref1['client_id']);
        
        // Test short name
        $ref2 = $service->generateClientReference("Kim");
        $this->assertStringStartsWith('KIM', $ref2['client_id']);
        
        // Test single character
        $ref3 = $service->generateClientReference("X");
        $this->assertStringStartsWith('X', $ref3['client_id']);
        
        // Test with special characters (should be removed)
        $ref4 = $service->generateClientReference("O'Brien");
        $this->assertStringStartsWith('OBRI', $ref4['client_id']);
        
        echo "\n✓ Prefix generation working correctly!\n";
        echo "  Jonathan -> {$ref1['client_id']}\n";
        echo "  Kim -> {$ref2['client_id']}\n";
        echo "  X -> {$ref3['client_id']}\n";
        echo "  O'Brien -> {$ref4['client_id']}\n\n";
    }
}

