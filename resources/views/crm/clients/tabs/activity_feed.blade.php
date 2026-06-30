<!-- Activity Feed (Personal Details, Company Details, Activity nav; single #activity-feed instance) -->
<aside class="activity-feed" id="activity-feed">
    <div class="activity-feed-header">
        <h2>@icon('fa-history') Activity Feed</h2>
        <div class="activity-feed-header-actions">
            <button type="button" class="btn btn-sm btn-link p-0 activity-feed-refresh" id="activity-feed-refresh" title="Refresh">
                @icon('fa-sync-alt')
            </button>
            <label for="increase-activity-feed-width">
                <input type="checkbox" id="increase-activity-feed-width" title="Expand Width">
            </label>
        </div>
    </div>
    
    <!-- Extended Filters (visible only when checkbox is ticked / wide-mode) -->
    <div class="activity-feed-filter-bar" id="activity-feed-filter-bar" style="display: none;">
        <div class="activity-feed-filter-row">
            <input type="text" 
                   class="form-control form-control-sm activity-feed-search" 
                   id="activity-feed-search" 
                   placeholder="Search activities..." 
                   autocomplete="off">
        </div>
        <div class="activity-feed-filter-row">
            <input type="text" 
                   class="form-control form-control-sm activity-feed-date" 
                   id="activity-feed-date-from" 
                   placeholder="From" 
                   autocomplete="off">
            <input type="text" 
                   class="form-control form-control-sm activity-feed-date" 
                   id="activity-feed-date-to" 
                   placeholder="To" 
                   autocomplete="off">
        </div>
        <div class="activity-feed-filter-actions">
            <button type="button" class="btn btn-sm btn-primary activity-feed-apply" id="activity-feed-apply">
                @icon('fa-search') Apply
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary activity-feed-reset" id="activity-feed-reset">
                @icon('fa-redo') Reset
            </button>
        </div>
    </div>
    
    <!-- Activity Type Filters -->
    <div class="activity-filters">
        <button class="activity-filter-btn active" data-filter="all">
            @icon('fa-list') All
        </button>
        <button class="activity-filter-btn" data-filter="activity">
            @icon('fa-bolt') Activity
        </button>
        <button class="activity-filter-btn" data-filter="note">
            @icon('fa-sticky-note') Notes
        </button>
        <button class="activity-filter-btn" data-filter="email">
            @icon('fa-envelope') Emails
        </button>
        <button class="activity-filter-btn" data-filter="document">
            @icon('fa-file-alt') Documents
        </button>
        <button class="activity-filter-btn" data-filter="signature">
            @icon('fa-file-signature') Signatures
        </button>
        <button class="activity-filter-btn" data-filter="financial">
            @icon('fa-dollar-sign') Financial
        </button>
    </div>
    
    <ul class="feed-list">
        @php
        // Handle search parameters
        $staff_search = $_REQUEST['staff'] ?? $_REQUEST['user'] ?? ''; // 'user' kept for backward compatibility
        $keyword_search = $_REQUEST['keyword'] ?? '';
        
        // Query activities based on search parameters
        if ($staff_search != "" || $keyword_search != "") {
            if ($staff_search != "" && $keyword_search != "") {
                // Both staff and keyword search
                $activities = \App\Models\ActivitiesLog::select('activities_logs.*')
                    ->leftJoin('staff', 'activities_logs.created_by', '=', 'staff.id')
                    ->where('activities_logs.client_id', $fetchedData->id)
                    ->where(function($query) use ($staff_search) {
                        $staffSearchLower = strtolower($staff_search);
                        $query->whereRaw('LOWER(staff.first_name) LIKE ?', ['%'.$staffSearchLower.'%']);
                    })
                    ->where(function($query) use ($keyword_search) {
                        $query->where('activities_logs.description', 'like', '%'.$keyword_search.'%');
                        $query->orWhere('activities_logs.subject', 'like', '%'.$keyword_search.'%');
                    })
                    ->orderby('activities_logs.created_at', 'DESC')
                    ->get();
            } else if ($staff_search == "" && $keyword_search != "") {
                // Keyword search only
                $activities = \App\Models\ActivitiesLog::select('activities_logs.*')
                    ->where('activities_logs.client_id', $fetchedData->id)
                    ->where(function($query) use ($keyword_search) {
                        $query->where('activities_logs.description', 'like', '%'.$keyword_search.'%');
                        $query->orWhere('activities_logs.subject', 'like', '%'.$keyword_search.'%');
                    })
                    ->orderby('activities_logs.created_at', 'DESC')
                    ->get();
            } else if ($staff_search != "" && $keyword_search == "") {
                // Staff search only
                $activities = \App\Models\ActivitiesLog::select('activities_logs.*','staff.first_name','staff.last_name','staff.email')
                    ->leftJoin('staff', 'activities_logs.created_by', '=', 'staff.id')
                    ->where('activities_logs.client_id', $fetchedData->id)
                    ->where(function($query) use ($staff_search) {
                        $staffSearchLower = strtolower($staff_search);
                        $query->whereRaw('LOWER(staff.first_name) LIKE ?', ['%'.$staffSearchLower.'%']);
                    })
                    ->orderby('activities_logs.created_at', 'DESC')
                    ->get();
            }
        } else {
            // No search - get all activities
            $activities = \App\Models\ActivitiesLog::where('client_id', $fetchedData->id)
                ->orderby('created_at', 'DESC')
                ->get();
        }
        @endphp
        
        @if($activities->count() > 0)
            @foreach($activities as $activit)
                @php
                    $admin = \App\Models\Staff::where('id', $activit->created_by)->first();
                @endphp
                @include('crm.clients.tabs.partials._activity_item', [
                    'activity' => $activit,
                    'admin' => $admin,
                    'clientId' => $fetchedData->id
                ])
            @endforeach
            <li class="feed-item feed-item-no-results" style="display: none; text-align: center; padding: 20px; color: #6c757d;">
                @icon('fa-filter', ['style' => 'font-size: 1.5em; margin-bottom: 8px; opacity: 0.5;'])
                <p class="mb-0 small">No activities match your filters</p>
            </li>
        @else
            <li class="feed-item feed-item--empty" style="text-align: center; padding: 20px; color: #6c757d;">
                @icon('fa-inbox', ['style' => 'font-size: 2em; margin-bottom: 10px; opacity: 0.5;'])
                <p>No activities found</p>
            </li>
        @endif
    </ul>
</aside>
