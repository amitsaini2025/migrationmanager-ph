<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClientExperience extends Model
{
    protected $table = 'client_experiences'; // The name of the table

    /**
     * CRM Work Experience list / "Current" order:
     * ongoing (null finish) first, then latest finish_date, then latest start_date, then id.
     */
    public const ORDER_BY_DISPLAY_SQL = 'CASE WHEN job_finish_date IS NULL THEN 0 ELSE 1 END ASC, job_finish_date DESC NULLS LAST, job_start_date DESC NULLS LAST';

    protected $fillable = [
        'client_id',
        'admin_id',
        'job_title',
        'job_code',
        'job_country',
        'job_start_date',
        'job_finish_date',
        'relevant_experience',
        'job_emp_name',
        'job_state',
        'job_type',
        // Points calculation field
        'fte_multiplier'
    ];

    /**
     * Same ordering as the client edit Work Experience section (first row = canonical "current").
     */
    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->orderByRaw(static::ORDER_BY_DISPLAY_SQL)
            ->orderByDesc($table.'.id');
    }
}
