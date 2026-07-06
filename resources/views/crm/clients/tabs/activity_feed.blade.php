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
        <li class="feed-item feed-item--loading" id="activity-feed-loading" style="text-align: center; padding: 20px; color: #6c757d;">
            @icon('fa-spinner', ['class' => 'fa-spin', 'style' => 'font-size: 1.5em; margin-bottom: 8px;'])
            <p class="mb-0 small">Loading activities...</p>
        </li>
        <li class="feed-item feed-item-no-results" style="display: none; text-align: center; padding: 20px; color: #6c757d;">
            @icon('fa-filter', ['style' => 'font-size: 1.5em; margin-bottom: 8px; opacity: 0.5;'])
            <p class="mb-0 small">No activities match your filters</p>
        </li>
        <li class="feed-item feed-item--empty" style="display: none; text-align: center; padding: 20px; color: #6c757d;">
            @icon('fa-inbox', ['style' => 'font-size: 2em; margin-bottom: 10px; opacity: 0.5;'])
            <p>No activities found</p>
        </li>
    </ul>

    <div class="activity-feed-load-more-wrap" id="activity-feed-load-more-wrap" style="display: none; text-align: center; padding: 10px 0 4px;">
        <button type="button" class="btn btn-sm btn-outline-primary" id="activity-feed-load-more">
            Load more
        </button>
    </div>
</aside>
