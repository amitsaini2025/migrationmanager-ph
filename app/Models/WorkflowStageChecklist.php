<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStageChecklist extends Model
{
    protected $table = 'workflow_stage_checklists';

    protected $fillable = [
        'workflow_id',
        'workflow_stage_id',
        'name',
        'description',
        'allow_client',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'allow_client' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function stage()
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }
}
