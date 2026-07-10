<?php
namespace App\Models;

use App\Support\WorkflowStageFreeze;
use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class WorkflowStage extends Model
{
	use Sortable;
	
	protected $table = 'workflow_stages';
	
	protected $fillable = [
        'id', 'name', 'workflow_id', 'sort_order', 'is_protected', 'created_at', 'updated_at'
    ];

	protected $casts = [
		'is_protected' => 'boolean',
	];
  
	public $sortable = ['id', 'name', 'created_at', 'updated_at'];

	/**
	 * Get the workflow this stage belongs to.
	 */
	public function workflow()
	{
		return $this->belongsTo(Workflow::class, 'workflow_id');
	}

	/**
	 * Whether this stage is locked by system config rules (name + workflow).
	 */
	public function isConfigFrozen(): bool
	{
		$workflowName = null;
		if ($this->workflow_id) {
			if ($this->relationLoaded('workflow') && $this->workflow) {
				$workflowName = $this->workflow->name;
			} else {
				$workflowName = $this->workflow()->value('name');
			}
		}

		return WorkflowStageFreeze::isFrozen($this->name, $workflowName);
	}

	/**
	 * Whether this stage is locked (cannot rename/delete in Admin Console).
	 */
	public function isFrozen(): bool
	{
		return (bool) $this->is_protected || $this->isConfigFrozen();
	}
}
