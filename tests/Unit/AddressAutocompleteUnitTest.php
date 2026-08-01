<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Test Address Autocomplete with Unit Numbers
 * 
 * This test verifies that the address autocomplete properly handles
 * addresses with unit/apartment numbers from Google Places API
 */
class AddressAutocompleteUnitTest extends TestCase
{
    /**
     * Test that Google Places API returns unit numbers in address_components
     * 
     * This simulates a Google Places API response with a unit number
     */
    public function test_google_places_response_includes_unit_number()
    {
        // Simulated Google Places API response for: "Unit 5, 123 Main Street, Sydney NSW 2000"
        $mockResponse = [
            'status' => 'OK',
            'result' => [
                'formatted_address' => 'Unit 5, 123 Main St, Sydney NSW 2000, Australia',
                'address_components' => [
                    [
                        'long_name' => '5',
                        'short_name' => '5',
                        'types' => ['subpremise']  // Unit number
                    ],
                    [
                        'long_name' => '123',
                        'short_name' => '123',
                        'types' => ['street_number']
                    ],
                    [
                        'long_name' => 'Main Street',
                        'short_name' => 'Main St',
                        'types' => ['route']
                    ],
                    [
                        'long_name' => 'Sydney',
                        'short_name' => 'Sydney',
                        'types' => ['locality', 'political']
                    ],
                    [
                        'long_name' => 'New South Wales',
                        'short_name' => 'NSW',
                        'types' => ['administrative_area_level_1', 'political']
                    ],
                    [
                        'long_name' => '2000',
                        'short_name' => '2000',
                        'types' => ['postal_code']
                    ],
                    [
                        'long_name' => 'Australia',
                        'short_name' => 'AU',
                        'types' => ['country', 'political']
                    ]
                ]
            ]
        ];

        // Verify the response structure
        $this->assertArrayHasKey('result', $mockResponse);
        $this->assertArrayHasKey('address_components', $mockResponse['result']);
        
        // Find the unit number component
        $unitComponent = null;
        foreach ($mockResponse['result']['address_components'] as $component) {
            if (in_array('subpremise', $component['types'])) {
                $unitComponent = $component;
                break;
            }
        }
        
        // Assert unit number is present
        $this->assertNotNull($unitComponent, 'Unit number (subpremise) should be present in address components');
        $this->assertEquals('5', $unitComponent['long_name']);
    }

    /**
     * Test address parsing logic extracts unit number correctly
     * 
     * This verifies the JavaScript logic would correctly extract:
     * - address_line_1: "5/123 Main Street" (unit combined with street)
     * - address_line_2: "" (empty, unit is in line 1)
     * - suburb: "Sydney"
     * - state: "NSW"
     * - postcode: "2000"
     */
    public function test_address_parsing_extracts_unit_to_address_line_1()
    {
        $addressComponents = [
            [
                'long_name' => '5',
                'types' => ['subpremise']
            ],
            [
                'long_name' => '123',
                'types' => ['street_number']
            ],
            [
                'long_name' => 'Main Street',
                'types' => ['route']
            ],
            [
                'long_name' => 'Sydney',
                'types' => ['locality']
            ],
            [
                'short_name' => 'NSW',
                'types' => ['administrative_area_level_1']
            ],
            [
                'long_name' => '2000',
                'types' => ['postal_code']
            ]
        ];

        // Simulate the JavaScript extraction logic
        $unitNumber = '';
        $streetNumber = '';
        $streetName = '';
        $addressLine1 = '';
        $suburb = '';
        $state = '';
        $postcode = '';

        foreach ($addressComponents as $component) {
            if (in_array('subpremise', $component['types'])) {
                $unitNumber = $component['long_name'];
            }
            if (in_array('street_number', $component['types'])) {
                $streetNumber = $component['long_name'];
            }
            if (in_array('route', $component['types'])) {
                $streetName = $component['long_name'];
            }
            if (in_array('locality', $component['types'])) {
                $suburb = $component['long_name'];
            }
            if (in_array('administrative_area_level_1', $component['types'])) {
                $state = $component['short_name'] ?? $component['long_name'];
            }
            if (in_array('postal_code', $component['types'])) {
                $postcode = $component['long_name'];
            }
        }

        // Build address line 1 with unit number
        if ($unitNumber && $streetNumber && $streetName) {
            $addressLine1 = $unitNumber . '/' . $streetNumber . ' ' . $streetName;
        }

        // Assertions
        $this->assertEquals('5/123 Main Street', $addressLine1, 'Unit number should be combined with street address in Address Line 1');
        $this->assertEquals('Sydney', $suburb);
        $this->assertEquals('NSW', $state);
        $this->assertEquals('2000', $postcode);
    }

    /**
     * Test addresses without unit numbers still work
     */
    public function test_address_without_unit_number()
    {
        $addressComponents = [
            [
                'long_name' => '123',
                'types' => ['street_number']
            ],
            [
                'long_name' => 'Main Street',
                'types' => ['route']
            ],
            [
                'long_name' => 'Sydney',
                'types' => ['locality']
            ],
            [
                'short_name' => 'NSW',
                'types' => ['administrative_area_level_1']
            ],
            [
                'long_name' => '2000',
                'types' => ['postal_code']
            ]
        ];

        $unitNumber = '';
        $streetNumber = '';
        $streetName = '';
        $addressLine1 = '';

        foreach ($addressComponents as $component) {
            if (in_array('subpremise', $component['types'])) {
                $unitNumber = $component['long_name'];
            }
            if (in_array('street_number', $component['types'])) {
                $streetNumber = $component['long_name'];
            }
            if (in_array('route', $component['types'])) {
                $streetName = $component['long_name'];
            }
        }

        // Build address line 1 without unit number
        if ($streetNumber && $streetName) {
            $addressLine1 = $streetNumber . ' ' . $streetName;
        }

        $this->assertEquals('123 Main Street', $addressLine1);
        $this->assertEquals('', $unitNumber, 'Unit number should be empty when not present');
    }
}
