<?php

namespace Tests\Unit;

use App\Models\CostAssignmentForm;
use Tests\TestCase;

class CostAssignmentFormDiscountTest extends TestCase
{
    public function test_unchecked_or_missing_discount_is_zero(): void
    {
        $this->assertSame(0.0, CostAssignmentForm::appliedDiscountFromRow(null));
        $this->assertSame(0.0, CostAssignmentForm::appliedDiscountFromRow(['discount_enabled' => false, 'discount' => 50]));
        $this->assertSame(0.0, CostAssignmentForm::appliedDiscountFromRow((object) ['discount' => 25]));
    }

    public function test_enabled_discount_returns_amount(): void
    {
        $this->assertSame(100.5, CostAssignmentForm::appliedDiscountFromRow([
            'discount_enabled' => true,
            'discount' => '100.50',
        ]));
        $this->assertSame(40.0, CostAssignmentForm::appliedDiscountFromRow((object) [
            'discount_enabled' => 1,
            'discount' => 40,
        ]));
    }

    public function test_unchecked_request_clears_discount_amount(): void
    {
        $this->assertSame(
            ['discount_enabled' => false, 'discount' => 0.0],
            CostAssignmentForm::discountFieldsFromRequest(false, 80)
        );
    }

    public function test_checked_request_keeps_discount_amount(): void
    {
        $this->assertSame(
            ['discount_enabled' => true, 'discount' => 80.25],
            CostAssignmentForm::discountFieldsFromRequest(true, '80.25')
        );
    }

    public function test_total_cost_unchanged_without_discount(): void
    {
        $this->assertSame(2214.0, CostAssignmentForm::calculateTotalCost(540, 1674, 0, 0, 0));
    }

    public function test_total_cost_subtracts_discount(): void
    {
        $this->assertSame(2114.0, CostAssignmentForm::calculateTotalCost(540, 1674, 0, 0, 100));
    }

    public function test_discount_cannot_exceed_gross_total(): void
    {
        $this->assertSame(0.0, CostAssignmentForm::calculateTotalCost(100, 50, 0, 0, 999));
    }
}
