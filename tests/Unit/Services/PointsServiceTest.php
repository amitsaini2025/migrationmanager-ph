<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PointsService;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;

class PointsServiceTest extends TestCase
{

    protected PointsService $pointsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointsService = new PointsService();
        Cache::flush(); // Clear cache before each test
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(PointsService::class, $this->pointsService);
    }

    /** @test */
    public function it_calculates_points_for_subclass_189()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '189', 6);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsInt($result['total']);
        
        // Subclass 189 should have 0 nomination bonus
        $this->assertEquals(0, $result['breakdown']['nomination']['points']);
    }

    /** @test */
    public function it_calculates_points_for_subclass_190()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '190', 6);
        
        // Subclass 190 should have 5 point nomination bonus
        $this->assertEquals(5, $result['breakdown']['nomination']['points']);
        
        // Total should be at least 5 (nomination bonus)
        $this->assertGreaterThanOrEqual(5, $result['total']);
    }

    /** @test */
    public function it_calculates_points_for_subclass_491()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '491', 6);
        
        // Subclass 491 should have 15 point nomination bonus
        $this->assertEquals(15, $result['breakdown']['nomination']['points']);
        
        // Total should be at least 15 (nomination bonus)
        $this->assertGreaterThanOrEqual(15, $result['total']);
    }

    /** @test */
    public function it_calculates_age_points_correctly()
    {
        // Age 25-32 should get 30 points
        $client = $this->createTestClient(['dob' => now()->subYears(30)->format('Y-m-d')]);
        
        $result = $this->pointsService->compute($client, null, 6);
        
        $this->assertEquals(30, $result['breakdown']['age']['points']);
        $this->assertStringContainsString('years', $result['breakdown']['age']['detail']);
        $this->assertStringContainsString('months', $result['breakdown']['age']['detail']);
    }

    /** @test */
    public function it_calculates_zero_age_points_for_age_over_45()
    {
        // Age 45+ should get 0 points
        $client = $this->createTestClient(['dob' => now()->subYears(46)->format('Y-m-d')]);
        
        $result = $this->pointsService->compute($client, null, 6);
        
        $this->assertEquals(0, $result['breakdown']['age']['points']);
    }

    /** @test */
    public function it_has_all_required_breakdown_categories()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '190', 6);
        
        $expectedCategories = ['age', 'english', 'australian_work_experience', 'overseas_work_experience', 'education', 'partner', 'nomination'];
        
        foreach ($expectedCategories as $category) {
            $this->assertArrayHasKey($category, $result['breakdown'], "Missing category: {$category}");
            $this->assertArrayHasKey('points', $result['breakdown'][$category]);
            $this->assertArrayHasKey('detail', $result['breakdown'][$category]);
        }
    }

    /** @test */
    public function it_caches_points_calculation()
    {
        $client = $this->createTestClient();
        
        // First call
        $result1 = $this->pointsService->compute($client, '190', 6);
        
        // Second call should use cache
        $result2 = $this->pointsService->compute($client, '190', 6);
        
        $this->assertEquals($result1['total'], $result2['total']);
        
        // Check cache exists
        $cacheKey = "points_{$client->id}_190_6";
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_can_clear_cache()
    {
        $client = $this->createTestClient();
        
        // Compute and cache
        $this->pointsService->compute($client, '190', 6);
        
        $cacheKey = "points_{$client->id}_190_6";
        $this->assertTrue(Cache::has($cacheKey));
        
        // Clear cache
        $this->pointsService->clearCache($client->id);
        
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_generates_warnings_array()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '190', 6);
        
        $this->assertIsArray($result['warnings']);
        
        // Each warning should have required fields
        foreach ($result['warnings'] as $warning) {
            $this->assertArrayHasKey('type', $warning);
            $this->assertArrayHasKey('message', $warning);
            $this->assertArrayHasKey('severity', $warning);
        }
    }

    /** @test */
    public function it_calculates_different_totals_for_different_subclasses()
    {
        $client = $this->createTestClient();
        
        $result189 = $this->pointsService->compute($client, '189', 6);
        $result190 = $this->pointsService->compute($client, '190', 6);
        $result491 = $this->pointsService->compute($client, '491', 6);
        
        // 190 should be 5 points more than 189
        $this->assertEquals($result189['total'] + 5, $result190['total']);
        
        // 491 should be 15 points more than 189
        $this->assertEquals($result189['total'] + 15, $result491['total']);
    }

    /** @test */
    public function it_returns_consistent_structure()
    {
        $client = $this->createTestClient();
        
        $result = $this->pointsService->compute($client, '190', 6);
        
        // Check top-level structure
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('warnings', $result);
        
        // Check that total matches sum of breakdown points
        $calculatedTotal = array_sum(array_column($result['breakdown'], 'points'));
        $this->assertEquals($calculatedTotal, $result['total']);
    }

    /**
     * Helper method to create a test client
     */
    protected function createTestClient(array $overrides = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'type' => 'client',
            'dob' => now()->subYears(30)->format('Y-m-d'),
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'test@example.com',
        ], $overrides));
    }
}

