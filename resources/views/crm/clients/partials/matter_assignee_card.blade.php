<?php
$matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id', $fetchedData->id)->where('matter_status', 1)->count();
if ($matter_cnt > 0) {
?>
    <div class="card" id="matter-assignee-card">
        <h3>@icon('fa-user') Matter Assignee  <a style="margin-left: 110px;" class="changeMatterAssignee" href="javascript:;" role="button">Change Assignee</a></h3>

        <?php
        $matter_dis_ref_info_arr = [];
        if (isset($id1) && $id1) {
            $matter_dis_ref_info_arr = \App\Models\ClientMatter::select('sel_migration_agent', 'sel_person_responsible', 'sel_person_assisting', 'office_id')
                ->where('client_id', $fetchedData->id)
                ->where('client_unique_matter_no', $id1)
                ->first();
        } else {
            $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id', $fetchedData->id)->where('matter_status', 1)->count();
            if ($matter_cnt > 0) {
                $matter_dis_ref_info_arr = \App\Models\ClientMatter::select('sel_migration_agent', 'sel_person_responsible', 'sel_person_assisting', 'office_id')
                    ->where('client_id', $fetchedData->id)
                    ->where('matter_status', 1)
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }
        ?>

        <div class="field-group">
            <span class="field-label">Migration Agent</span>
            <span class="field-value" id="matter_assignee_migration_agent">
                <?php
                if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_migration_agent != '') {
                    $mig_agent_info_arr = \App\Models\Staff::select('first_name', 'last_name')->where('id', $matter_dis_ref_info_arr->sel_migration_agent)->first();
                    if ($mig_agent_info_arr) {
                        echo $mig_agent_info_arr->first_name . ' ' . $mig_agent_info_arr->last_name;
                    }
                } else {
                    echo 'N/A';
                }
                ?>
            </span>
        </div>
        <div class="field-group">
            <span class="field-label">Person Responsible</span>
            <span class="field-value" id="matter_assignee_person_responsible">
                <?php
                if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_responsible != '') {
                    $sel_person_responsible_info_arr = \App\Models\Staff::select('first_name', 'last_name')->where('id', $matter_dis_ref_info_arr->sel_person_responsible)->first();
                    if ($sel_person_responsible_info_arr) {
                        echo $sel_person_responsible_info_arr->first_name . ' ' . $sel_person_responsible_info_arr->last_name;
                    }
                } else {
                    echo 'N/A';
                }
                ?>
            </span>
        </div>

        <div class="field-group">
            <span class="field-label">Person Assisting</span>
            <span class="field-value" id="matter_assignee_person_assisting">
                <?php
                if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->sel_person_assisting != '') {
                    $sel_person_assisting_info_arr = \App\Models\Staff::select('first_name', 'last_name')->where('id', $matter_dis_ref_info_arr->sel_person_assisting)->first();
                    if ($sel_person_assisting_info_arr) {
                        echo $sel_person_assisting_info_arr->first_name . ' ' . $sel_person_assisting_info_arr->last_name;
                    }
                } else {
                    echo 'N/A';
                }
                ?>
            </span>
        </div>

        <div class="field-group">
            <span class="field-label">Handling Office</span>
            <span class="field-value" id="matter_assignee_office">
                <?php
                if (isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->office_id != '') {
                    $office_info = \App\Models\Branch::select('office_name')->where('id', $matter_dis_ref_info_arr->office_id)->first();
                    if ($office_info) {
                        echo $office_info->office_name;
                    }
                } else {
                    echo 'No Office Assigned';
                }
                ?>
            </span>
        </div>
    </div>
<?php
}
?>
