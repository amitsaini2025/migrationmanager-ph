<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frozen workflow stages (Admin Console)
    |--------------------------------------------------------------------------
    |
    | Stages whose names match these rules cannot be renamed or deleted.
    | Exact matches are compared case-insensitively after trimming.
    | "Contains" rules match if the stage name contains the substring (any case).
    |
    */
    'frozen_stage_names' => [
        'Checklist',
        'Decision Received',
        'Ready to Close',
        'File Closed',
    ],

    /*
     * Stage names that start with this text (any case) are frozen.
     * Matches e.g. "Verification: Payment, Service Agreement, Forms"
     * without locking unrelated names like "Pre-verification review".
     */
    'frozen_stage_name_starts_with' => [
        'verification',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope protected stages to the General workflow only
    |--------------------------------------------------------------------------
    |
    | When true, stages matching the frozen rules above are only locked on the
    | General workflow. Custom workflows may rename or delete those stages.
    |
    */
    'freeze_protected_stages_only_on_general_workflow' => true,

    /*
    |--------------------------------------------------------------------------
    | Default workflow for new client matters (by matter type title)
    |--------------------------------------------------------------------------
    |
    | Applied only when creating a new client_matters row. Existing matters are
    | not changed. matters.workflow_id (Admin → Matter List) still overrides this.
    | Unmapped matter types use default_workflow_name (General).
    |
    */
    'matter_default_workflows' => [
        'Administrative Review Tribunal' => 'Administrative Review Tribunal',
        'Bridging Visa B- (020)' => 'Bridging visa B (BV-B) and Work rights',
        'Expression Of Interest' => 'EOI /ROI',
        'Skill assessment - Australian Physiotherapy Council' => 'Skill assessment',
    ],

    'default_workflow_name' => 'General',

];
