<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles allowed to delete CRM email logs (inbox/sent thread rows)
    |--------------------------------------------------------------------------
    |
    | Comma-separated user_roles.id values. Default matches legacy behaviour
    | (Super Admin, Admin, Migration Agent). Set CRM_EMAIL_LOG_DELETE_ROLE_IDS
    | in .env to add roles without code changes, e.g. "1,12,16,20".
    |
    */
    'email_log_delete_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_EMAIL_LOG_DELETE_ROLE_IDS', '1,12,16'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Personal document category delete (CRM client/company documents tab)
    |--------------------------------------------------------------------------
    |
    | Who may delete empty personal document categories (folder tabs). Default:
    | Super Admin (1) and Migration Agent (16). Override via
    | CRM_PERSONAL_DOC_CATEGORY_DELETE_ROLE_IDS e.g. "1,16,12".
    |
    */
    'personal_document_category_delete_role_ids' => (($__personalDocCatDeleteRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_PERSONAL_DOC_CATEGORY_DELETE_ROLE_IDS', '1,16'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__personalDocCatDeleteRoles
        : [1, 16],

    /*
    |--------------------------------------------------------------------------
    | Visa document category delete (CRM client visa documents tab)
    |--------------------------------------------------------------------------
    |
    | Who may delete empty matter-specific visa document categories. Default:
    | Super Admin (1) and Migration Agent (16). System/default rows have
    | client_matter_id NULL and cannot be deleted. Override via
    | CRM_VISA_DOC_CATEGORY_DELETE_ROLE_IDS e.g. "1,16,12".
    |
    */
    'visa_document_category_delete_role_ids' => (($__visaDocCatDeleteRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_VISA_DOC_CATEGORY_DELETE_ROLE_IDS', '1,16'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__visaDocCatDeleteRoles
        : [1, 16],

    /*
    |--------------------------------------------------------------------------
    | Matter discontinue / reopen: allowed staff roles (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | Who may set a matter inactive (discontinue) or active again (reopen / legacy revert).
    | Default: Super Admin (1), Admin (17), Migration Agent (16).
    | Override via CRM_MATTER_DISCONTINUE_ROLE_IDS e.g. "1,17,16,12".
    | If the env value parses to an empty list, falls back to [1, 17, 16] so access is not
    | accidentally locked out for all staff.
    |
    */
    'matter_discontinue_role_ids' => (($__matterDiscontinueRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_MATTER_DISCONTINUE_ROLE_IDS', '1,17,16'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__matterDiscontinueRoles
        : [1, 17, 16],

    /*
    |--------------------------------------------------------------------------
    | Person Assisting role IDs (user_roles.id)
    |--------------------------------------------------------------------------
    |
    | Staff with these roles only see clients/leads where they appear on a matter
    | as migration agent, person responsible, or person assisting (client_matters
    | sel_migration_agent / sel_person_responsible / sel_person_assisting), or
    | are assigned on the lead record (admins.user_id). Super admin (role 1) is
    | never restricted. Override via CRM_PERSON_ASSISTING_ROLE_IDS e.g. "13,21".
    |
    */
    'person_assisting_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_PERSON_ASSISTING_ROLE_IDS', '13'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list / lead record full access (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | These roles see every lead (list + detail). Everyone else sees leads where
    | admins.user_id matches, or any client_matters row for that lead lists them as
    | MA / PR / PA, or they have an active cross-access grant (see StaffClientVisibility).
    |
    | Default mapping: 1 = Super Admin, 17 = Admin, 12 = Person Responsible.
    | Override via CRM_LEAD_FULL_ACCESS_ROLE_IDS e.g. "1,17,12".
    |
    */
    'lead_full_access_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_FULL_ACCESS_ROLE_IDS', '1,12,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list: which user_roles.module_access keys unlock the page
    |--------------------------------------------------------------------------
    |
    | Previously only key "20" (view all clients) was checked, so staff with
    | only 21–23 (add/edit/view assigned clients) saw an empty list. Default
    | includes 20–23. Set CRM_LEAD_LIST_MODULE_ACCESS_KEYS e.g. "20,21,22,23".
    |
    | CRM_LEAD_LIST_EXTRA_ROLE_IDS: optional staff role ids that may open the
    | lead list even without those keys (row visibility still uses
    | restrictLeadListQuery).
    |
    */
    'lead_list_module_access_keys' => array_values(array_filter(
        array_map(
            static fn (string $k) => trim($k),
            array_map('strval', explode(',', (string) env('CRM_LEAD_LIST_MODULE_ACCESS_KEYS', '20,21,22,23')))
        ),
        static fn (string $k) => $k !== ''
    )),

    'lead_list_extra_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_LIST_EXTRA_ROLE_IDS', ''))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list: "assigned leads only" roles (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | These roles may open /leads without client module keys 20–23. They only
    | see rows where admins.user_id = their staff id (via restrictLeadListQuery).
    | Default: PA (13), Calling (14), Accountant (15), Migration Agent (16).
    | Set CRM_LEAD_LIST_ASSIGNED_ONLY_ROLE_IDS to override, e.g. "13,14,15,16".
    |
    */
    'lead_list_assigned_only_role_ids' => (($__leadAssignedRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_LIST_ASSIGNED_ONLY_ROLE_IDS', '13,14,15,16'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__leadAssignedRoles
        : [13, 14, 15, 16],

    /*
    |--------------------------------------------------------------------------
    | Super-admin-only client file IDs (admins.client_id when type = client)
    |--------------------------------------------------------------------------
    |
    | Staff with role 1 (Super Admin) see these clients everywhere. Other staff
    | cannot list, search, or open them. Applies only to type=client, not leads.
    | Override or extend via CRM_SUPER_ADMIN_ONLY_CLIENT_FILE_IDS e.g.
    | "GURP2502080,OASH2505088".
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Company email domains
    |--------------------------------------------------------------------------
    |
    | Used by EmailMatchingService (inbox vs sent detection) and
    | EmailUploadController::autoAssignLabels(). Emails whose sender matches one
    | of these domains are treated as outbound/sent mail. Override via the
    | CRM_COMPANY_EMAIL_DOMAINS env variable as a comma-separated list,
    | e.g. "bansalimmigration.com.au,bansaleducation.com.au".
    |
    */
    'company_email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CRM_COMPANY_EMAIL_DOMAINS',
            'bansalimmigration.com.au,bansaleducation.com.au,bansallawyers.com.au'
        ))
    ), fn (string $d) => $d !== '')),

    'super_admin_only_client_file_ids' => array_values(array_unique(array_filter(
        array_map(
            static fn (string $id) => trim($id),
            explode(',', (string) env(
                'CRM_SUPER_ADMIN_ONLY_CLIENT_FILE_IDS',
                'GURP2502080,OASH2505088,PRAB2504834,PALW2502036'
            ))
        ),
        static fn (string $id) => $id !== ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Google review reminder modal (client/lead detail)
    |--------------------------------------------------------------------------
    |
    | Master switch: when false, the auto-open reminder popup is never shown.
    | Modal markup, routes, SMS/email actions, and “Send Google Review” stay
    | in place. Set CRM_GOOGLE_REVIEW_REMINDER_ENABLED=true to turn it back on.
    |
    | staff.role values (user_roles.id) that never see the reminder popup.
    | Default: 14 = Calling Team, 15 = Accountant (accounts). Override via
    | CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_ROLE_IDS e.g. "14,15,20".
    |
    | staff.office_id values (branches.id) that never see the reminder popup.
    | Default: 8 = INDIA office. Override via
    | CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_OFFICE_IDS e.g. "8" or "" to disable.
    |
    | Delay before the modal opens (milliseconds). Default 60000 = 1 minute.
    | CRM_GOOGLE_REVIEW_REMINDER_DELAY_MS=0 opens immediately.
    | Capped at 30 minutes to avoid accidental huge values in .env.
    |
    */
    'google_review_reminder_enabled' => filter_var(
        env('CRM_GOOGLE_REVIEW_REMINDER_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'google_review_reminder_exclude_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_ROLE_IDS', '14,15'))
    ), static fn (int $id) => $id > 0)),

    'google_review_reminder_exclude_office_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_OFFICE_IDS', '8'))
    ), static fn (int $id) => $id > 0)),

    'google_review_reminder_modal_delay_ms' => min(
        30 * 60 * 1000,
        max(0, (int) env('CRM_GOOGLE_REVIEW_REMINDER_DELAY_MS', 60000))
    ),

    /*
    |--------------------------------------------------------------------------
    | Front-desk check-in wizard (header icon + /front-desk/checkin)
    |--------------------------------------------------------------------------
    |
    | staff.role values (user_roles.id) that may open the wizard. Default includes
    | 1 = Super Admin, 12 = Person Responsible, 14 = Calling / Reception,
    | 17 = Admin. Exempt roles from crm_access (CRM_ACCESS_EXEMPT_ROLE_IDS) are
    | merged in at runtime. Override via CRM_FRONT_DESK_CHECKIN_ROLE_IDS e.g. "1,14,17".
    |
    */
    'front_desk_checkin_role_ids' => (($__fdCheckinRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_FRONT_DESK_CHECKIN_ROLE_IDS', '1,12,14,17'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__fdCheckinRoles
        : [1, 12, 14, 17],

    /*
    |--------------------------------------------------------------------------
    | Checklist create: MA / PR assignment role IDs (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | Used by Staff::assignmentDropdown*Query() on the client checklists tab only.
    | Migration Agent list also includes is_migration_agent = 1 (see Staff model).
    |
    */
    'assignment_dropdown_migration_agent_role_ids' => (($__assignMaRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_ASSIGNMENT_DROPDOWN_MIGRATION_AGENT_ROLE_IDS', '16'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__assignMaRoles
        : [16],

    'assignment_dropdown_person_responsible_role_ids' => (($__assignPrRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_ASSIGNMENT_DROPDOWN_PERSON_RESPONSIBLE_ROLE_IDS', '12'))
    ), static fn (int $id) => $id > 0))) !== [])
        ? $__assignPrRoles
        : [12],

    /*
    |--------------------------------------------------------------------------
    | Outlook email upload allowed extensions (client Emails tab, Smart Import)
    |--------------------------------------------------------------------------
    |
    | Comma-separated extensions without dots. Default: msg,eml
    | Override via EMAIL_UPLOAD_ALLOWED_EXTENSIONS=msg,eml in .env
    |
    */
    'email_upload_allowed_extensions' => array_values(array_unique(array_filter(array_map(
        static function (string $ext): string {
            return strtolower(ltrim(trim($ext), '.'));
        },
        explode(',', (string) env('EMAIL_UPLOAD_ALLOWED_EXTENSIONS', 'msg,eml'))
    )))),

    'email_upload_max_kb' => max(1, (int) env('EMAIL_UPLOAD_MAX_KB', 30720)),

    /*
    |--------------------------------------------------------------------------
    | Staff workload (dashboard strip + Admin Console)
    |--------------------------------------------------------------------------
    |
    | New = admins.created_at within this many days (Melbourne calendar).
    | Returning = not new, and last Call/In-Person file note (any staff) was
    | older than returning_gap_days before today, or never.
    |
    */
    'workload' => [
        'new_record_days' => max(1, (int) env('CRM_WORKLOAD_NEW_RECORD_DAYS', 14)),
        'returning_gap_days' => max(1, (int) env('CRM_WORKLOAD_RETURNING_GAP_DAYS', 365)),
    ],

];
