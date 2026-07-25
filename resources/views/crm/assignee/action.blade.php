@extends('layouts.crm_client_detail')
@section('title', 'Action')

@section('content')
<style>
    /* Reset and base styles */
    * { box-sizing: border-box; }
    
    /* Prevent horizontal overflow globally */
    html, body {
        overflow-x: hidden;
        max-width: 100vw;
    }
    
    /* Main content containers */
    .main-content {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    .section {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    .section-body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    /* Header styles */
    .client-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 30px; 
        padding: 20px 0; 
        border-bottom: 2px solid #e9ecef;
        flex-wrap: wrap;
        gap: 15px;
        max-width: 100%;
    }
    
    .client-header h1 { 
        font-size: 2em; 
        font-weight: 700; 
        color: #2c3e50; 
        margin: 0; 
        word-wrap: break-word;
    }

    .client-header .action-page-title {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .client-header .action-page-subtitle {
        font-size: 0.7rem;
        font-weight: 500;
        color: #0d6efd;
        margin: 0;
        line-height: 1.2;
        letter-spacing: 0.01em;
    }
    
    .client-status {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        min-width: 0;
    }
    
    /* Button styles */
    .btn { 
        padding: 10px 20px; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-size: 0.9em; 
        font-weight: 600; 
        transition: all 0.3s ease;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Add New Task button specific styling */
    .add_my_task {
        margin-right: 15px !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: unset !important;
        min-width: auto !important;
        max-width: none !important;
        flex-shrink: 0 !important;
        width: auto !important;
    }
    
    .btn-primary { 
        background-color: #3498db; 
        color: white; 
        box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
    }
    
    .btn-primary:hover { 
        background-color: #2980b9; 
        box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        transform: translateY(-1px);
    }

    .action-status-btn.active {
        background-color: #1f1655 !important;
        border-color: #1f1655 !important;
        box-shadow: 0 2px 6px rgba(31, 22, 85, 0.35);
        pointer-events: none;
    }

    /* Tab styles */
    .tabs { 
        display: flex; 
        gap: 8px; 
        margin-bottom: 25px; 
        flex-wrap: wrap;
        max-width: 100%;
        background-color: #f8f9fa;
        padding: 8px;
        border-radius: 10px;
    }
    
    .tab-button { 
        padding: 12px 20px; 
        border: 2px solid transparent; 
        border-radius: 8px; 
        background-color: #6c757d; 
        color: white; 
        font-size: 0.9em; 
        font-weight: 600; 
        cursor: pointer; 
        transition: all 0.3s ease;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .tab-button.active, .tab-button:hover { 
        background-color: #3498db; 
        color: white !important;
        border-color: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
    }
    
    .tab-button .badge { 
        background-color: #ffffff; 
        color: #3498db; 
        border-radius: 12px; 
        padding: 4px 8px; 
        margin-left: 8px; 
        font-size: 0.75em; 
        font-weight: 700;
    }
    
    /* Search controls */
    .header-controls {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
        max-width: 100%;
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
    }
    
    .search-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
        min-width: 0;
    }
    
    .search-bar label { 
        font-size: 0.9em; 
        color: #2c3e50; 
        white-space: nowrap;
        font-weight: 600;
    }
    
    .search-bar input {
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.9em;
        width: 250px;
        max-width: 100%;
        flex-shrink: 0;
        transition: all 0.3s ease;
        background-color: white;
    }
    
    .search-bar input:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    /* Table container */
    .table-responsive {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }
    
    /* Table styles */
    .table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 20px; 
        min-width: 800px; /* Ensure minimum width for readability */
        background-color: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .table th, .table td { 
        padding: 15px 12px; 
        text-align: left; 
        border-bottom: 1px solid #e9ecef; 
        font-size: 0.9em; 
        word-wrap: break-word;
        overflow-wrap: break-word;
        vertical-align: middle;
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .table th { 
        background-color: #2c3e50; 
        font-weight: 700; 
        color: white; 
        text-transform: uppercase; 
        cursor: pointer;
        letter-spacing: 0.5px;
        font-size: 0.8em;
    }
    
    .table tbody tr { 
        transition: background-color 0.2s ease;
    }
    
    .table tbody tr:hover { 
        background-color: #f8f9fa; 
    }
    
    .table tbody tr:nth-child(even) {
        background-color: #fafbfc;
    }
    
    .table tbody tr:nth-child(even):hover {
        background-color: #f1f3f4;
    }
    
    .table tbody td, .table thead th { 
        color: #2c3e50 !important; 
    }
    
    /* Column width constraints to prevent overflow */
    .table th:nth-child(1), .table td:nth-child(1) { 
        width: 60px; 
        min-width: 60px; 
        max-width: 60px; 
        text-align: center; /* Center align SNO column */
    } /* Sno */
    .table th:nth-child(2), .table td:nth-child(2) { width: 80px; min-width: 80px; max-width: 80px; } /* Done */
    .table th:nth-child(3), .table td:nth-child(3) { width: 120px; min-width: 120px; max-width: 120px; } /* Assigner Name */
    .table th:nth-child(4), .table td:nth-child(4) { width: 150px; min-width: 150px; max-width: 150px; } /* Client Reference */
    .table th:nth-child(5), .table td:nth-child(5) { width: 100px; min-width: 100px; max-width: 100px; } /* Assign Date */
    .table th:nth-child(6), .table td:nth-child(6) { width: 100px; min-width: 100px; max-width: 100px; } /* Type */
    .table th:nth-child(7), .table td:nth-child(7) { width: 200px; min-width: 200px; } /* Note - flexible */
    .table th:nth-child(8), .table td:nth-child(8) { width: 120px; min-width: 120px; max-width: 120px; } /* Action */
    
    /* Action buttons */
    .action-buttons .btn { 
        margin-right: 5px; 
        margin-bottom: 3px;
        font-size: 0.8em;
        padding: 8px 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .action-buttons .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Icon-only edit buttons in the Action column (AJAX DataTable rows) */
    .dataTables_scrollBody .yajra-datatable .update_task,
    .yajra-datatable .update_task {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto;
        min-width: 36px;
        min-height: 36px;
        padding: 8px 10px !important;
        margin-top: 0;
        text-transform: none;
        letter-spacing: normal;
        line-height: 1;
    }

    .dataTables_scrollBody .yajra-datatable .update_task svg.lucide,
    .dataTables_scrollBody .yajra-datatable .update_task i[data-lucide],
    .yajra-datatable .update_task svg.lucide,
    .yajra-datatable .update_task i[data-lucide] {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    
    /* Pagination */
    .pagination { 
        display: flex; 
        justify-content: flex-end; 
        gap: 10px; 
        margin-top: 20px; 
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span { 
        padding: 8px 12px; 
        border: 1px solid #dee2e6; 
        border-radius: 6px; 
        font-size: 0.9em; 
        text-decoration: none; 
        color: #343a40; 
    }
    
    .pagination a:hover { 
        background-color: #f8f9fa; 
    }
    
    .pagination .active span { 
        background-color: #0d6efd; 
        color: white; 
        border-color: #0d6efd; 
    }
    
    /* DataTables customization */
    .dataTables_wrapper {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    .dataTables_wrapper .dataTables_length { 
        margin-bottom: 0; 
    }
    
    .dataTables_wrapper .dataTables_filter { 
        display: none; 
    }
    
    #DataTables_Table_0_info { 
        margin-top: 20px; 
    }
    
    .bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 25px;
        flex-wrap: wrap;
        gap: 15px;
        max-width: 100%;
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
    }
    
    .dataTables_length {
        flex-shrink: 0;
    }
    
    .dataTables_length select {
        padding: 10px 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.9em;
        background-color: white;
        transition: all 0.3s ease;
    }
    
    .dataTables_length select:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    .dataTables_length label {
        font-size: 0.9em;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        font-weight: 600;
    }
    
    .dataTables_info {
        flex-grow: 1;
        text-align: center;
        font-size: 0.9em;
        color: #2c3e50;
        word-wrap: break-word;
        font-weight: 500;
    }
    
    /* Error messages */
    .error-message {
        color: red;
        font-size: 0.8em;
        margin-top: 5px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .client-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        
        .client-header h1 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .client-status {
            width: 100%;
            justify-content: flex-start;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        .tabs {
            gap: 4px;
            padding: 6px;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 10px 16px;
            font-size: 0.8em;
        }
        
        .header-controls {
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .search-bar {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .search-bar input {
            width: 100%;
            max-width: 300px;
        }
        
        .table {
            min-width: 600px;
            font-size: 0.85em;
        }
        
        .table th, .table td {
            padding: 10px 8px;
            font-size: 0.85em;
        }
        
        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 0.75em;
            margin-right: 3px;
        }
    }
    
    @media (max-width: 480px) {
        .client-header h1 {
            font-size: 1.3em;
        }
        
        .tab-button {
            padding: 8px 12px;
            font-size: 0.75em;
        }
        
        .table th, .table td {
            padding: 8px 6px;
            font-size: 0.8em;
        }
        
        .action-buttons .btn {
            padding: 5px 8px;
            font-size: 0.7em;
        }
    }
    
    /* Hide horizontal scrollbar but keep functionality */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <section class="section" style="padding-top: 70px;">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg"></div>

            <div class="client-header">
                <div class="action-page-title">
                    <h1>Action</h1>
                    <p class="action-page-subtitle">( Incomplete Action Assigned To Me)</p>
                </div>
                <div class="client-status" style="margin-right: 50px;">
                    <a class="btn btn-primary action-status-btn" style="border-radius: 0px;" id="archived-tab" href="{{ URL::to('/action_completed') }}" title="Completed Task List Assigned To Me">Completed</a>
                    @include('components.crm.add-my-task-popover-template')
                    {{-- Must NOT use class "tab-button": global handler binds $('.tab-button') and reloads the DataTable / toggles filter "active". --}}
                    <button type="button" class="btn btn-primary add_my_task" title="Add New Task">
                        @icon('fa-plus') Add My Task
                    </button>
                    <a class="btn btn-primary" style="border-radius: 0px;" id="assigned_by_me" href="{{ URL::to('/assigned_by_me') }}">Assigned by me</a>
                </div>
            </div>

            <!-- Tabs (Filters) -->
            <div class="tabs">
                <button class="tab-button active" data-filter="all">ALL <span class="badge" id="all-count">0</span></button>
                <button class="tab-button" data-filter="call">Call <span class="badge" id="call-count">0</span></button>
                <button class="tab-button" data-filter="checklist">Checklist <span class="badge" id="checklist-count">0</span></button>
                <button class="tab-button" data-filter="review">Review <span class="badge" id="review-count">0</span></button>
                <button class="tab-button" data-filter="query">Query <span class="badge" id="query-count">0</span></button>
                <button class="tab-button" data-filter="urgent">Urgent <span class="badge" id="urgent-count">0</span></button>
                <button class="tab-button" data-filter="personal_action">Personal Action <span class="badge" id="personal-task-count">0</span></button>
                <button class="tab-button" data-filter="client_portal">Client Portal <span class="badge" id="client-portal-count">0</span></button>
                <button class="tab-button" data-filter="eoi_roi_amendment">EOI/ROI Amendment <span class="badge" id="eoi-roi-amendment-count">0</span></button>
                <button class="tab-button" data-filter="follow_up">Follow up <span class="badge" id="follow-up-count">0</span></button>
            </div>

            <!-- Header Controls (Only Search Bar) -->
            <div class="header-controls">
                <div class="search-bar">
                    <label for="searchInput">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search tasks...">
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table yajra-datatable">
                    <thead>
                        <tr>
                            <th data-column="DT_RowIndex">Sno</th>
                            <th data-column="done">Done</th>
                            <th data-column="assigner_name">Assigner Name</th>
                            <th data-column="client_reference">Client Reference</th>
                            <th data-column="assign_date">Assign Date</th>
                            <th data-column="task_group">Type</th>
                            <th data-column="note_description">Note</th>
                            <th data-column="action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Assign Modal -->
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview"></div>
    </div>
</div>

<!-- Task Completion Notes Modal -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3498db; color: white;">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    @icon('fa-check-circle') Complete Task
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="completionNotes" class="font-weight-bold">
                        @icon('fa-comment') Completion Notes/Feedback
                    </label>
                    <textarea 
                        class="form-control" 
                        id="completionNotes" 
                        rows="5" 
                        placeholder="Enter any notes or feedback about completing this task..."
                        style="resize: vertical; border: 2px solid #e9ecef; border-radius: 8px; padding: 12px;"
                    ></textarea>
                    <small class="form-text text-muted">
                        @icon('fa-info-circle') These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    @icon('fa-times') Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmTaskCompletion">
                    @icon('fa-check') Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
@include('components.inputmask-scripts')
<script src="{{URL::to('/')}}/js/popover.js"></script>
<script src="{{URL::to('/')}}/js/components/dropdown-multi-select.js"></script>
<style>
/* Ensure popovers display correctly */

.btn_readmore {
    color: #007bff !important;
    text-decoration: none !important;
    background: none !important;
    border: none !important;
    padding: 0 !important;
    font-size: inherit !important;
    cursor: pointer !important;
}

.btn_readmore:hover {
    color: #0056b3 !important;
    text-decoration: underline !important;
}

    /* Popover styling for better design */
    .popover {
        max-width: 600px !important;
        width: 600px !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12) !important;
        border: none !important;
        z-index: 9999 !important;
        overflow: hidden !important;
    }
    
    /* Center Add My Task popover in the middle of the page */
    .popover.add-my-task-popover {
        position: fixed !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
    }
    
    /* Hide arrow for centered Add My Task popover */
    .popover.add-my-task-popover .arrow,
    .popover.add-my-task-popover .popover-arrow {
        display: none !important;
    }
    
    /* Add backdrop for Add My Task popup */
    .popover-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: none;
    }
    
    .popover-backdrop.show {
        display: block;
    }
    
    /* Fix popover display issues */
    .popover {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Ensure popover shows on click */
    .popover.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

.popover .popover-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-bottom: none !important;
    border-radius: 8px 8px 0 0 !important;
    padding: 16px 20px !important;
    font-weight: 600 !important;
    font-size: 15px !important;
    letter-spacing: 0.5px !important;
}

.popover .popover-body {
    padding: 20px !important;
    word-wrap: break-word !important;
    white-space: normal !important;
}

.popover .popover-body * {
    box-sizing: border-box !important;
}

/* Form styling within popover */
.popover .form-group {
    margin-bottom: 0 !important;
    box-sizing: border-box !important;
}

.popover .modern-popover-content {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 20px !important;
    padding: 5px !important;
}

.popover .modern-popover-content > .form-group {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
}

.popover .modern-popover-content > .form-group-full-width {
    grid-column: 1 / -1 !important;
}

.popover .modern-popover-content > .text-center {
    grid-column: 1 / -1 !important;
    margin-top: 10px !important;
}

.popover .form-group label {
    font-weight: 600 !important;
    color: #2c3e50 !important;
    margin-bottom: 8px !important;
    display: block !important;
    font-size: 13px !important;
}

.popover .form-control {
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
    padding: 10px 12px !important;
    font-size: 14px !important;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    display: block !important;
}

.popover .form-control:focus {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25) !important;
    outline: 0 !important;
}

.popover textarea.form-control {
    min-height: 80px !important;
    resize: vertical !important;
    line-height: 1.5 !important;
}

.popover select.form-control {
    appearance: auto !important;
    -webkit-appearance: auto !important;
    -moz-appearance: auto !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 100% !important;
}

.popover .assignee-mm-select {
    width: 100% !important;
    max-width: 100% !important;
}

.popover .ts-wrapper {
    width: 100% !important;
    max-width: 100% !important;
}

/* Button styling */
.popover .btn {
    padding: 12px 30px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
    letter-spacing: 0.5px !important;
}

.popover .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    color: white !important;
}

.popover .btn-primary:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
}

.popover .btn-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    color: white !important;
}

.popover .btn-info:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
}

/* Error message styling */
.popover .error-message {
    color: #dc3545 !important;
    font-size: 11px !important;
    margin-top: 4px !important;
    font-weight: 500 !important;
    display: block !important;
    min-height: 16px !important;
}

/* Box header styling */
.popover .box-header {
    border-bottom: 1px solid #e9ecef !important;
    padding-bottom: 15px !important;
    margin-bottom: 15px !important;
}

.popover .box-header:last-child {
    border-bottom: none !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Box footer styling */
.popover .box-footer {
    border-top: 1px solid #e9ecef !important;
    padding-top: 15px !important;
    margin-top: 15px !important;
    text-align: center !important;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .popover {
        max-width: 90vw !important;
        width: 90vw !important;
        left: 5vw !important;
    }
    
    .popover .popover-body {
        padding: 15px !important;
    }
    
    .popover .form-group {
        margin-bottom: 12px !important;
    }
    
    .popover .modern-popover-content {
        grid-template-columns: 1fr !important;
    }
}

/* Add My Task specific styling */
.popover .dropdown-multi-select {
    position: relative;
    display: block;
    width: 100%;
}

.popover .dropdown-multi-select .btn {
    width: 100%;
    text-align: left;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    color: #495057;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.popover .dropdown-multi-select .btn:hover,
.popover .dropdown-multi-select .btn:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    outline: 0;
}

.popover .dropdown-multi-select .dropdown-menu {
    width: 100%;
    max-height: 300px;
    overflow: hidden;
    border: 1px solid #ced4da;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 0;
    margin-top: 2px;
}

/* Bootstrap manages the show class on dropdown-menu directly */
.popover .dropdown-multi-select .dropdown-menu:not(.show) {
    display: none;
}

.popover .dropdown-multi-select .dropdown-search-wrapper {
    padding: 8px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.popover .dropdown-multi-select .assignee-search-input {
    font-size: 13px;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e0;
    width: 100%;
}

.popover .dropdown-multi-select .assignee-search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.1);
    outline: none;
}

.popover .dropdown-multi-select .assignee-list {
    max-height: 200px;
    overflow-y: auto;
    padding: 8px;
}

.popover .dropdown-multi-select .dropdown-item {
    display: flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

/* Override display for hidden items - CRITICAL for search to work */
.popover .dropdown-multi-select .assignee-item[style*="display: none"],
.popover .dropdown-multi-select .assignee-item.hidden {
    display: none !important;
}

.popover .dropdown-multi-select .dropdown-item:hover {
    background-color: #f8f9fa;
}

.popover .dropdown-multi-select .dropdown-item input[type="checkbox"] {
    margin-right: 8px;
    margin-bottom: 0;
}

.popover .form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 8px;
    display: block;
    font-size: 14px;
}

.popover .form-group {
    margin-bottom: 20px;
}

.popover .form-group:last-child {
    margin-bottom: 0;
}

/* Client search select styling */
.popover .js-data-example-ajaxccsearch__addmytask {
    width: 100%;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    background-color: #fff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

    .popover .js-data-example-ajaxccsearch__addmytask:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        outline: 0;
    }
    
    /* Final overflow prevention */
    /*body > * {
        max-width: 100vw;
        overflow-x: hidden;
    }*/
    
    /* Ensure no horizontal scroll on the page */
    /*.main-wrapper {
        overflow-x: hidden;
        max-width: 100vw;
    }*/
    
    /* Flatpickr z-index fix to appear above popovers */
    .flatpickr-calendar {
        z-index: 99999 !important;
    }
</style>
<script type="text/javascript">
$(function () {
    function getActionAddTaskPopoverHtml() {
        var tpl = document.getElementById('add-my-task-popover-template');
        return tpl ? tpl.innerHTML : '';
    }

    function hideAddMyTaskPopover() {
        document.querySelectorAll('.add_my_task').forEach(function(btn) {
            var inst = typeof bootstrap !== 'undefined' && bootstrap.Popover ? bootstrap.Popover.getInstance(btn) : null;
            if (inst) {
                inst.hide();
            }
        });
    }

    // Add My Task: Bootstrap 5.3.x native Popover (matches compiled BS5 CSS; no jQuery popover / data-content)
    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
        document.querySelectorAll('.add_my_task').forEach(function(btn) {
            var existing = bootstrap.Popover.getInstance(btn);
            if (existing) {
                existing.dispose();
            }
            new bootstrap.Popover(btn, {
                html: true,
                sanitize: false,
                trigger: 'click',
                placement: 'top',
                container: 'body',
                customClass: 'add-my-task-popover',
                title: (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-plus-circle') : '<i class="fa fa-plus-circle"></i>') + ' Add New Task',
                content: getActionAddTaskPopoverHtml
            });
        });
    } else {
        console.error('Add My Task: bootstrap.Popover is not available.');
    }

    var actionDataTable = null;

    function getActionTableIconRoot() {
        if (actionDataTable && actionDataTable.table) {
            return actionDataTable.table().node();
        }
        var scrollBodyTable = document.querySelector('.dataTables_scrollBody .yajra-datatable');
        if (scrollBodyTable) {
            return scrollBodyTable;
        }
        return document.querySelector('.yajra-datatable');
    }

    function refreshActionTableIcons() {
        if (typeof refreshLucideIcons !== 'function') {
            return false;
        }
        var root = getActionTableIconRoot();
        if (root) {
            refreshLucideIcons(root);
        }
        return true;
    }

    function refreshActionTableIconsWhenReady() {
        if (typeof refreshLucideIcons !== 'function') {
            window.addEventListener('load', refreshActionTableIcons, { once: true });
            return;
        }
        refreshActionTableIcons();
    }
    
    var table = $('.yajra-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('action.list') }}",
            data: function(d) {
                var $activeTab = $('.tab-button.active');
                d.filter = $activeTab.length ? ($activeTab.data('filter') || 'all') : 'all';
                d.search.value = $('#searchInput').val() || ''; // Pass the search term to the server in DataTables format
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables Ajax Error:', error, thrown);
                // Handle UTF-8 encoding errors gracefully
                if (xhr && xhr.responseText && xhr.responseText.includes('Malformed UTF-8')) {
                    console.warn('UTF-8 encoding issue detected. Please refresh the page.');
                }
            }
        },
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'done_action', name: 'done', orderable: false, searchable: false},
            {data: 'assigner_name', name: 'assigner_name', orderable: true, searchable: true},
            {data: 'client_reference', name: 'client_reference', orderable: true, searchable: true},
            {data: 'assign_date', name: 'assign_date', orderable: true, searchable: true},
            {data: 'task_group', name: 'task_group', orderable: true, searchable: true},
            {data: 'note_description', name: 'note_description', orderable: true, searchable: true},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ],
        "fnDrawCallback": function() {
            // Initialize any non-update_task popovers using Bootstrap 5 native API
            document.querySelectorAll('[data-bs-toggle="popover"]:not(.update_task)').forEach(function(el) {
                var existing = bootstrap.Popover.getInstance(el);
                if (existing) { existing.dispose(); }
                new bootstrap.Popover(el, {
                    html: true,
                    sanitize: false,
                    trigger: 'click',
                    placement: 'bottom',
                    container: 'body'
                });
            });

            // Update badge counts
            updateBadgeCounts();

            refreshActionTableIconsWhenReady();
        },
        "bAutoWidth": false,
        "scrollX": true,
        "scrollCollapse": true,
        "dom": 'rt<"bottom"lip><"clear">', // Move length menu (l) to bottom with info (i) and pagination (p)
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100], // Options for entries dropdown
        "order": [[4, 'desc']], // Default sorting by assign_date descending
        "responsive": false,
        "autoWidth": false
    });
    actionDataTable = table;

    // Search functionality
    $('#searchInput').on('keyup', function() {
        table.ajax.reload(); // Trigger DataTables reload with the new search term
    });

    // Helper function to escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function decodeBase64Utf8(b64) {
        if (!b64) return '';
        try {
            var binary = atob(b64);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            if (typeof TextDecoder !== 'undefined') {
                return new TextDecoder('utf-8').decode(bytes);
            }
            return decodeURIComponent(escape(binary));
        } catch (e) {
            return '';
        }
    }

    function getTaskNoteFromButton($button) {
        var raw = $button.attr('data-noteid') || '';
        if ($button.attr('data-note-b64') === '1') {
            return decodeBase64Utf8(raw);
        }
        return raw || $button.data('noteid') || '';
    }

    function disposeBsPopover(el) {
        var inst = bootstrap.Popover.getInstance(el);
        if (inst) {
            inst.dispose();
        }
    }

    function buildTaskGroupOptions(selectedGroup) {
        var groups = [
            { value: 'Call', label: '📞 Call' },
            { value: 'Checklist', label: '✓ Checklist' },
            { value: 'Review', label: '📋 Review' },
            { value: 'Query', label: '❓ Query' },
            { value: 'Urgent', label: '🔥 Urgent' },
            { value: 'Personal Action', label: '👤 Personal Action' },
            { value: 'Client Portal', label: '🌐 Client Portal' },
            { value: 'EOI/ROI Amendment', label: '📝 EOI/ROI Amendment' },
            { value: 'Follow Up', label: '📅 Follow Up' }
        ];
        var selected = String(selectedGroup || '');
        var html = '<option value="">Select Group...</option>';
        groups.forEach(function(g) {
            var isSelected = selected === g.value ? ' selected' : '';
            html += '<option value="' + escapeHtml(g.value) + '"' + isSelected + '>' + g.label + '</option>';
        });
        return html;
    }

    // Function to generate Update Task popover content
    function getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId) {
        assignedTo = String(assignedTo || '');
        var safeNote = escapeHtml(noteId || '');
        var safeTaskId = escapeHtml(taskId || '');
        var safeClientId = escapeHtml(clientId || '');
        var followupDateVal = '';
        if (followupDate) {
            followupDateVal = escapeHtml(String(followupDate).trim().split(/\s+/)[0] || '');
        }
        var taskGroupOptions = buildTaskGroupOptions(taskGroup);

        return (
            '<div id="popover-content" class="modern-popover-content update-task-layout">' +
                '<div class="form-group">' +
                    '<label class="control-label">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-user') : '<i class="fa fa-user"></i>') + ' Select Assignee</label>' +
                    '<select class="assignee-mm-select form-control" id="rem_cat" name="rem_cat">' +
                        '<option value="">Select Assignee...</option>' +
                        @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                            <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                            '<option value="{{ $admin->id }}"' + (assignedTo === '{{ $admin->id }}' ? ' selected' : '') + '>' +
                                {!! json_encode(trim($admin->first_name . ' ' . $admin->last_name . ' (' . (@$branchname->office_name ?? '') . ')')) !!} +
                            '</option>' +
                        @endforeach
                    '</select>' +
                    '<div id="assignee-error" class="error-message"></div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="control-label">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-tag') : '<i class="fa fa-tag"></i>') + ' Task Group</label>' +
                    '<select class="assignee-mm-select form-control" id="task_group" name="task_group">' +
                        taskGroupOptions +
                    '</select>' +
                    '<div id="task-group-error" class="error-message"></div>' +
                '</div>' +
                '<div class="form-group form-group-full-width">' +
                    '<label class="control-label">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-comment') : '<i class="fa fa-comment"></i>') + ' Task Description</label>' +
                    '<textarea id="assignnote" class="form-control" rows="3" placeholder="Enter task description...">' + safeNote + '</textarea>' +
                    '<div id="note-error" class="error-message"></div>' +
                '</div>' +
                '<div class="form-group form-group-full-width">' +
                    '<label class="control-label">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-calendar') : '<i class="fa fa-calendar"></i>') + ' Follow-up date</label>' +
                    '<input type="date" class="form-control" id="popoverdatetime" name="popoverdate" value="' + followupDateVal + '">' +
                    '<div id="date-error" class="error-message"></div>' +
                '</div>' +
                '<input id="assign_note_id" type="hidden" value="' + safeTaskId + '">' +
                '<input id="assign_client_id" type="hidden" value="' + safeClientId + '">' +
                '<div class="text-center">' +
                    '<button class="btn btn-primary" id="updateTask">' +
                        (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-save') : '<i class="fa fa-save"></i>') + ' Update Task' +
                    '</button>' +
                '</div>' +
            '</div>'
        );
    }

    // Initialize client select for Add My Task popover (combined handler)
    $(document).on('shown.bs.popover', '.add_my_task', function() {
        var $popover = $('.popover.add-my-task-popover.show').last();
        if ($popover.length === 0) {
            $popover = $('.popover.show').last();
            $popover.addClass('add-my-task-popover');
        }
        
        // Center the popover in the middle of the screen
        $popover.css({
            'position': 'fixed',
            'left': '50%',
            'top': '50%',
            'transform': 'translate(-50%, -50%)',
            'margin': '0',
            'z-index': '9999'
        });
        
        // Create and show backdrop
        if (!$('.popover-backdrop').length) {
            $('body').append('<div class="popover-backdrop"></div>');
        }
        $('.popover-backdrop').addClass('show');
        
        // Close popup when clicking backdrop
        $('.popover-backdrop').off('click').on('click', function() {
            hideAddMyTaskPopover();
        });
        
        // Initialize client search select with AJAX after positioning
        setTimeout(function() {
            initializeClientMmSelect();
        }, 100);

        // Hydrate Lucide SVG placeholders injected via tpl.innerHTML
        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($popover[0]);
        }
    });
    
    // Hide backdrop when Add My Task popover is hidden
    $(document).on('hidden.bs.popover', '.add_my_task', function() {
        $('.popover-backdrop').removeClass('show');
    });
    
    // Function to initialize client search (mmSelect) in Add My Task popover
    function initializeClientMmSelect() {
        var attempts = 0;
        var maxAttempts = 10;
        
        function tryInitialize() {
            attempts++;
            var $popoverCtx = $('.popover.add-my-task-popover.show').last();
            if (!$popoverCtx.length) {
                $popoverCtx = $('.popover.show').last();
            }
            var $clientSelect = $popoverCtx.find('#assign_client_id');
            
            if ($clientSelect.length && $clientSelect.is(':visible')) {
                if ($clientSelect.hasClass('mm-select-initialized')) {
                    $clientSelect.mmSelect('destroy');
                }
                
                try {
                    $clientSelect.mmSelect({
                        closeOnSelect: true,
                        placeholder: 'Search client...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $popoverCtx.length ? $popoverCtx : $('.popover.show').last(),
                        ajax: {
                            url: '{{URL::to('/clients/get-allclients')}}',
                            dataType: 'json',
                            delay: 350,
                            data: function (params) {
                                return { q: params.term || '' };
                            },
                            processResults: function (data) {
                                if (!data || typeof data !== 'object') {
                                    return { results: [] };
                                }
                                return {
                                    results: data.items || []
                                };
                            },
                            cache: true,
                            error: function(xhr, status, error) {
                                console.error('Error fetching clients:', error);
                            }
                        },
                        templateResult: formatRepomainMYTask,
                        templateSelection: formatRepoSelectionmainMYTask,
                        minimumInputLength: 2
                    });
                    
                    // Handle change event for client selection
                    /* $clientSelect.off('change.mmselectclient').on('change.mmselectclient', function () {
                        var v = $(this).val(); 
                        console.log('Client selected:', v);
                        var s = v.split('/');
                        if(s[1] == 'Matter' && s[2] != ''){
                            window.location = '{{URL::to('/clients/detail/')}}/'+s[0]+'/'+s[2];
                        } else {
                            if(s[1] == 'Client'){
                                window.location = '{{URL::to('/clients/detail/')}}/'+s[0];
                            } else {
                                window.location = '{{URL::to('/leads/history/')}}/'+s[0];
                            }
                        }
                        return false;
                    });*/
                    
                    return true; // Success
                } catch (error) {
                    console.error('Error initializing mmSelect:', error);
                    return false;
                }
            } else if (attempts < maxAttempts) {
                setTimeout(tryInitialize, 50);
            }
        }
        
        tryInitialize();
    }
    
    // Helper templates for client search results
    function formatRepomainMYTask (repo) {
        if (repo.loading) {
            return repo.text;
        }

        var cidSafe = String(repo.cid || '').replace(/'/g, '&#39;').replace(/&/g, '&amp;');
        var $container = $(
            "<div dataid='" + cidSafe + "' class='selectclient mm-result-repository ag-flex ag-space-between ag-align-center'>" +
            "<div class='ag-flex ag-align-start'>" +
                "<div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span class='mm-result-repository__title text-semi-bold'></span>\u00A0</div>" +
                "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small></div>" +
            "</div>" +
            "</div>" +
            "<div class='ag-flex ag-flex-column ag-align-end'>" +
                "<span class='mm-result-repository-stats'></span>" +
            "</div>" +
            "</div>"
        );

        $container.find(".mm-result-repository__title").text(repo.name || '');
        $container.find(".mm-result-repository__description").text(repo.email || '');
        if(repo.status == 'Archived'){
            $container.find(".mm-result-repository-stats").append('<span class="ui label  mm-result-repository__statistics">'+(repo.status || '')+'</span>');
        } else if(repo.status) {
            $container.find(".mm-result-repository-stats").append('<span class="ui label yellow mm-result-repository__statistics">'+(repo.status || '')+'</span>');
        }
        return $container;
    }

    function formatRepoSelectionmainMYTask (repo) {
        return (repo && repo.name) || (repo && repo.text) || '';
    }
    
    // Ensure Add My Task popover works correctly (backup initialization on click)
    // Initialize Update Task popover (placeholder for future enhancements)
    $(document).on('shown.bs.popover', '.update_task', function() {
        // Reserved for future enhancements (e.g. mmSelect inside update task popover)
    });

    // Update badge counts
    function updateBadgeCounts() {
        $.ajax({
            url: "{{ route('action.counts') }}",
            method: "GET",
            success: function(data) {
                if (data && typeof data === 'object') {
                    $('#all-count').text(data.all || 0);
                    $('#call-count').text(data.call || 0);
                    $('#checklist-count').text(data.checklist || 0);
                    $('#review-count').text(data.review || 0);
                    $('#query-count').text(data.query || 0);
                    $('#urgent-count').text(data.urgent || 0);
                    $('#personal-task-count').text(data.personal_action || 0);
                    $('#client-portal-count').text(data.client_portal || 0);
                    $('#eoi-roi-amendment-count').text(data.eoi_roi_amendment || 0);
                    $('#follow-up-count').text(data.follow_up || 0);
                } else {
                    console.warn('Invalid badge count data received');
                }
            },
            error: function(xhr) {
                console.error('Error fetching badge counts:', xhr.responseText);
            }
        });
    }

    // Filter by tabs only (exclude unrelated controls that might use similar button styles)
    $('.tabs').on('click', '.tab-button', function() {
        $(this).closest('.tabs').find('.tab-button').removeClass('active');
        $(this).addClass('active');
        table.ajax.reload();
    });

    // Handle Update Task button click — Bootstrap 5 native Popover (no $.fn.popover)
    $('.yajra-datatable').on('click', '.update_task', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var btn = this;
        var $button = $(btn);
        var assignedTo = $button.attr('data-assignedto') || $button.data('assignedto') || '';
        var noteId     = getTaskNoteFromButton($button);
        var taskId     = $button.attr('data-taskid') || $button.data('taskid') || '';
        var taskGroup  = $button.attr('data-taskgroupid') || $button.data('taskgroupid') || '';
        var followupDate = $button.attr('data-actiondate') || $button.data('actiondate') || '';
        var clientId   = $button.attr('data-clientid') || $button.data('clientid') || '';

        // Close other open popovers
        document.querySelectorAll('.update_task, .btn_readmore').forEach(function(el) {
            if (el !== btn) {
                disposeBsPopover(el);
            }
        });

        // Always recreate so each click opens a fresh popup with current row data
        disposeBsPopover(btn);

        var pop = new bootstrap.Popover(btn, {
            html: true,
            sanitize: false,
            title: 'Update Task',
            content: getUpdateTaskContent(assignedTo, noteId, taskId, taskGroup, followupDate, clientId),
            trigger: 'manual',
            placement: 'auto',
            container: 'body'
        });
        pop.show();
    });

    // Close update/readmore popovers when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.popover').length &&
            !$(e.target).closest('.update_task').length &&
            !$(e.target).closest('.btn_readmore').length) {
            document.querySelectorAll('.update_task, .btn_readmore').forEach(function(el) {
                disposeBsPopover(el);
            });
        }
    });

    // Handle Read More button clicks — Bootstrap 5 native Popover
    $(document).on('click', '.btn_readmore', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var btn = this;
        var $btn = $(btn);
        var fullContent = $btn.attr('data-full-content') || '';
        if ($btn.attr('data-full-content-b64') === '1') {
            fullContent = decodeBase64Utf8(fullContent);
        }
        if (!fullContent) { return; }
        fullContent = '<div class="readmore-popover-body">' + escapeHtml(fullContent) + '</div>';

        document.querySelectorAll('.update_task, .btn_readmore').forEach(function(el) {
            if (el !== btn) {
                disposeBsPopover(el);
            }
        });

        disposeBsPopover(btn);

        var pop = new bootstrap.Popover(btn, {
            html: true,
            sanitize: false,
            content: fullContent,
            trigger: 'manual',
            placement: 'top',
            container: 'body'
        });
        pop.show();
    });

    // Dispose update/readmore popovers after DataTable redraw so stale instances don't accumulate
    $(document).on('draw.dt', '.yajra-datatable', function() {
        document.querySelectorAll('.btn_readmore, .update_task').forEach(function(el) {
            disposeBsPopover(el);
        });
        refreshActionTableIconsWhenReady();
    });

    // Handle Update Task submission
    $(document).on('click', '#updateTask', function() {
        var $popover = $(this).closest('.popover');
        
        if (!$popover.length) {
            console.error('Popover not found');
            return;
        }
        
        var taskId = $popover.find('#assign_note_id').val() || '';
        var clientId = $popover.find('#assign_client_id').val() || '';
        var assignee = $popover.find('#rem_cat').val() || '';
        var note = $popover.find('#assignnote').val() || '';
        var taskGroup = $popover.find('#task_group').val() || '';

        // Clear previous error messages
        $popover.find('.error-message').text('');

        // Client-side validation
        var isValid = true;
        if (!assignee) {
            $popover.find('#assignee-error').text('Please select an assignee.');
            isValid = false;
        }
        if (!note || note.trim() === '') {
            $popover.find('#note-error').text('Please enter a note.');
            isValid = false;
        }
        if (!taskGroup) {
            $popover.find('#task-group-error').text('Please select a task group.');
            isValid = false;
        }

        if (!isValid) {
            return; // Stop submission if validation fails
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            type: 'post',
            url: "{{ URL::to('/') }}/clients/action/update",
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                note_id: taskId,
                note_type: 'follow_up',
                description: note,
                client_id: clientId,
                followup_datetime: $popover.find('#popoverdatetime').val() || '',
                assignee_name: $popover.find('#rem_cat :selected').text(),
                rem_cat: assignee,
                task_group: taskGroup
            },
            success: function(response) {
                $btn.prop('disabled', false);
                var obj = (typeof response === 'string') ? jQuery.parseJSON(response) : response;
                if (!obj || !obj.success) {
                    alert((obj && obj.message) ? obj.message : 'Update failed.');
                    return;
                }
                document.querySelectorAll('.update_task').forEach(function(el) {
                    disposeBsPopover(el);
                });
                table.draw(false);
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                console.error('Error updating task:', xhr.responseText);
                var msg = 'An error occurred while updating the task.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });

    // Delete record
    $('.yajra-datatable').on('click', '.deleteNote', function(e) {
        e.preventDefault();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        var url = $(this).data('remote');
        
        if (!url) {
            console.error('No delete URL found');
            alert('Unable to delete: missing URL');
            return;
        }
        
        var deleteConfirm = confirm("Are you sure?");
        if (deleteConfirm) {
            $.ajax({
                url: url,
                type: 'DELETE',
                dataType: 'json',
                data: {method: '_DELETE', submit: true}
            }).always(function(data) {
                table.draw(false);
            });
        }
    });

    // Complete task - open modal
    var currentTaskId = null;
    var currentTaskGroupId = null;
    
    $('.yajra-datatable').on('click', '.complete_task', function() {
        var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id') || '';
        
        if (!row_id) {
            console.error('No task ID found');
            return;
        }
        
        // Store task IDs for later use
        currentTaskId = row_id;
        currentTaskGroupId = row_unique_group_id;
        
        // Clear previous notes
        $('#completionNotes').val('');
        
        // Show the completion notes modal
        $('#completionNotesModal').modal('show');
    });
    
    // Handle task completion with notes
    $(document).on('click', '#confirmTaskCompletion', function() {
        var completionNotes = $('#completionNotes').val().trim();
        
        if (!currentTaskId) {
            console.error('No task ID found');
            return;
        }
        
        // Disable button to prevent double submission
        var $button = $(this);
        $button.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Completing...');
        
        $.ajax({
            type: 'post',
            url: "{{URL::to('/')}}/update-action-completed",
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                id: currentTaskId, 
                unique_group_id: currentTaskGroupId,
                completion_notes: completionNotes
            },
            success: function(response) {
                // Close modal
                $('#completionNotesModal').modal('hide');
                
                // Reset button
                $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
                
                // Clear stored IDs
                currentTaskId = null;
                currentTaskGroupId = null;
                
                // Reload table
                table.draw(false);

                var obj = response;
                if (typeof response === 'string') {
                    try { obj = $.parseJSON(response); } catch (e) { obj = {}; }
                }
                var message = (obj && obj.message) ? obj.message : 'Action completed successfully';
                if (obj && obj.status === false) {
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: message || 'Please try again', position: 'topRight', timeout: 4000 });
                    } else {
                        alert(message || 'Please try again');
                    }
                    return;
                }
                if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                    iziToast.success({ message: message, position: 'topRight', timeout: 4000 });
                } else {
                    alert(message);
                }
            },
            error: function(xhr) {
                console.error('Error completing task:', xhr.responseText);
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: 'An error occurred while completing the task.', position: 'topRight', timeout: 4000 });
                } else {
                    alert('An error occurred while completing the task.');
                }
                
                // Reset button
                $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
            }
        });
    });

    // Add My Task submission (scope fields to the open popover — avoids wrong #id when Update Task popover uses same ids)
    $(document).on('click', '#add_my_task', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(".popuploader").show();
        var flag = true;
        var error = "";
        $(".custom-error").remove();

        var $popover = $(this).closest('.popover');
        if ($popover.length === 0) {
            $popover = $('.popover.show').last();
        }

        var selectedRemCat = [];
        $popover.find(".checkbox-item:checked").each(function() {
            selectedRemCat.push($(this).val());
        });

        if (selectedRemCat.length === 0) {
            $('.popuploader').hide();
            error = "Assignee field is required.";
            $popover.find('#dropdownMenuButton').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }

        var noteVal = $popover.find('#assignnote').val();
        if (!noteVal || noteVal.trim() === '') {
            $('.popuploader').hide();
            error = "Note field is required.";
            $popover.find('#assignnote').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }

        if (flag) {
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/clients/action/personal/store",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                dataType: 'json',
                data: {
                    note_type: 'follow_up',
                    description: noteVal,
                    client_id: $popover.find('#assign_client_id').val(),
                    rem_cat: selectedRemCat,
                    task_group: $popover.find('#task_group').val()
                },
                success: function(response) {
                    $('.popuploader').hide();
                    if (response && response.success) {
                        hideAddMyTaskPopover();
                        $('.popover-backdrop').removeClass('show');
                        $('.custom-error').remove();
                        table.draw(false);
                    } else {
                        alert(response && response.message ? response.message : 'An error occurred');
                        table.draw(false);
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('Error adding task:', error);
                    alert('Failed to add task. Please try again.');
                }
            });
        } else {
            $(".popuploader").hide();
        }
    });
});
</script>
@endpush
