<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAssignmentForm extends Model
{
    protected $table = 'cost_assignment_forms';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'agent_id',
        'surcharge',
        'Dept_Base_Application_Charge',
        'Dept_Base_Application_Charge_no_of_person',
        'Dept_Base_Application_Charge_after_person',
        'Dept_Base_Application_Charge_after_person_surcharge',

        'Dept_Non_Internet_Application_Charge',
        'Dept_Non_Internet_Application_Charge_no_of_person',
        'Dept_Non_Internet_Application_Charge_after_person',
        'Dept_Non_Internet_Application_Charge_after_person_surcharge',

        'Dept_Additional_Applicant_Charge_18_Plus',
        'Dept_Additional_Applicant_Charge_18_Plus_no_of_person',
        'Dept_Additional_Applicant_Charge_18_Plus_after_person',
        'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge',

        'Dept_Additional_Applicant_Charge_Under_18',
        'Dept_Additional_Applicant_Charge_Under_18_no_of_person',
        'Dept_Additional_Applicant_Charge_Under_18_after_person',
        'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge',

        'Dept_Subsequent_Temp_Application_Charge',
        'Dept_Subsequent_Temp_Application_Charge_no_of_person',
        'Dept_Subsequent_Temp_Application_Charge_after_person',
        'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge',

        'Dept_Second_VAC_Instalment_Charge_18_Plus',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge',

        'Dept_Second_VAC_Instalment_Under_18',
        'Dept_Second_VAC_Instalment_Under_18_no_of_person',
        'Dept_Second_VAC_Instalment_Under_18_after_person',
        'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge',

        'Dept_Nomination_Application_Charge',
        'Dept_Sponsorship_Application_Charge',
        'saf_levy',
        'Block_1_Ex_Tax',
        'Block_2_Ex_Tax',
        'Block_3_Ex_Tax',
        'additional_fee_1',
        'discount_enabled',
        'discount',
        'TotalDoHACharges',
        'TotalDoHASurcharges',
        'TotalBLOCKFEE',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount_enabled' => false,
        'discount' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_enabled' => 'boolean',
            'discount' => 'decimal:2',
        ];
    }

    /**
     * Discount applied to totals. Unchecked or missing discount is always 0 so existing records stay unchanged.
     */
    public static function appliedDiscountFromRow(object|array|null $row): float
    {
        if ($row === null) {
            return 0.0;
        }

        if (is_array($row)) {
            $rawEnabled = $row['discount_enabled'] ?? false;
            $amount = $row['discount'] ?? 0;
        } else {
            $rawEnabled = $row->discount_enabled ?? false;
            $amount = $row->discount ?? 0;
        }
        $enabled = $rawEnabled === true || $rawEnabled === 1 || $rawEnabled === '1' || $rawEnabled === 'true';
        if (! $enabled) {
            return 0.0;
        }

        return max(0.0, floatval($amount));
    }

    /**
     * @return array{discount_enabled: bool, discount: float}
     */
    public static function discountFieldsFromRequest(bool $enabled, mixed $rawAmount): array
    {
        $amount = $enabled ? max(0.0, floatval($rawAmount ?? 0)) : 0.0;

        return [
            'discount_enabled' => $enabled,
            'discount' => round($amount, 2),
        ];
    }

    public static function calculateTotalCost(
        float $blockFee,
        float $deptCharges,
        float $surcharges,
        float $additionalFee,
        float $discount = 0.0
    ): float {
        $gross = $blockFee + $deptCharges + $surcharges + $additionalFee;
        $applied = max(0.0, $discount);
        if ($applied > $gross) {
            $applied = $gross;
        }

        return $gross - $applied;
    }

    /**
     * Get the Admin that owns the form.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the agent that owns the form.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'agent_id');
    }

    /**
     * Get the client matter associated with the form.
     */
    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }
}
