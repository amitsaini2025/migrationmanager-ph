<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="keyword" content="CRM">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="current-user-id" content="{{ optional(auth('admin')->user())->id }}">
    <title>CRM | @yield('title', 'Client Details')</title>
    <link rel="icon" type="image/png" href="{{asset('img/favicon.png')}}">
    <link rel="stylesheet" href="{{asset('css/app.min.css')}}">
    <!-- TinyMCE is self-hosted and loaded per page as needed -->
    @vite(['resources/css/vendor-libs.css'])
    <link rel="stylesheet" href="{{ asset('css/tom-select-layout-compat.css') }}?v={{ file_exists(public_path('css/tom-select-layout-compat.css')) ? filemtime(public_path('css/tom-select-layout-compat.css')) : 1 }}">
    <link rel="stylesheet" href="{{asset('css/style.css')}}">
    <link rel="stylesheet" href="{{asset('css/components.css')}}">
    <link rel="stylesheet" href="{{asset('css/custom.css')}}">
    @vite(['resources/css/icons.css'])
    @include('components.icons.config-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/datetime-display.js') }}"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        /* overflow-x: hidden matches crm_client_detail layout — stops page-level horizontal scroll from topbar min-width */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
            line-height: 1.6;
            overflow-x: hidden;
            max-width: 100%;
        }
        .main-wrapper {
            position: relative;
            max-width: 100%;
        }
        .main-navbar { position: fixed; top: 0; width: 100%; z-index: 1000; background-color: #fff; height: 70px; }
        
        /* Modern topbar layout — minmax(0, …) lets columns shrink so the sticky topbar cannot widen the page */
        .main-topbar { 
            display: grid !important; 
            grid-template-columns: minmax(0, 1fr) minmax(0, 640px) auto !important; 
            align-items: center !important; 
            gap: 16px !important; 
            position: sticky !important; 
            top: 0 !important; 
            z-index: 1000 !important; 
            height: 70px !important; 
            width: 100% !important;
            max-width: 100% !important;
            padding: 10px 16px !important; 
            background: #ffffff !important; 
            border-bottom: 1px solid #dfe3e6 !important; 
            box-shadow: 0 2px 8px rgba(0,0,0,.06) !important;
            transition: transform .2s ease-in-out !important;
            box-sizing: border-box !important;
        }
        .main-topbar .topbar-left,
        .main-topbar .topbar-center {
            min-width: 0 !important;
        }
        /* Hide on scroll (keep a small top gap visible) */
        .main-topbar.is-hidden {
            transform: translateY(calc(-100% + 6px)) !important;
        }
        /* Collapsed state */
        .main-topbar.is-collapsed { 
            grid-template-columns: auto !important; 
            height: 48px !important; 
            padding: 6px 12px !important; 
        }
        .main-topbar.is-collapsed .topbar-left,
        .main-topbar.is-collapsed .topbar-center,
        .main-topbar.is-collapsed .topbar-right { display: none !important; }
        .main-topbar .topbar-toggle { 
            display: inline-flex !important; align-items: center !important; justify-content: center !important; 
            width: 36px !important; height: 36px !important; border-radius: 8px !important; 
            background: #f3f5f7 !important; border: 1px solid #e9ecef !important; color: #495057 !important; 
        }
        .main-topbar:not(.is-collapsed) .topbar-toggle { display: none !important; }
        .topbar-left .icon-group { display: flex !important; gap: 10px !important; align-items: center !important; }
        .icon-btn { 
            display: inline-flex !important; align-items: center !important; justify-content: center !important; 
            width: 36px !important; height: 36px !important; border-radius: 8px !important; 
            background: transparent !important; border: none !important; color: #495057 !important; 
            transition: all .15s ease !important; text-decoration: none !important; 
        }
        .icon-btn:hover { background: #f3f5f7 !important; color: #0d6efd !important; }
        .icon-btn i { font-size: 18px !important; }
        /* Flash animation when notification count updates */
        .notification-bell-flash { animation: notificationBellFlash 0.6s ease-out !important; }
        @keyframes notificationBellFlash {
            0% { background: rgba(13, 110, 253, 0.4) !important; transform: scale(1.05); }
            100% { background: transparent !important; transform: scale(1); }
        }
        /* Search */
        .topbar-center .search-container { position: relative !important; width: 100% !important; max-width: 480px !important; }
        .search-input { 
            width: 100% !important; height: 40px !important; padding: 0 16px 0 44px !important; 
            border: 1px solid #e9ecef !important; border-radius: 20px !important; 
            background: #f8f9fa !important; font-size: 14px !important; 
            transition: all .15s ease !important; 
        }
        .search-input:focus { 
            outline: none !important; border-color: #0d6efd !important; 
            background: #fff !important; box-shadow: 0 0 0 3px rgba(13,110,253,.1) !important; 
        }
        .search-icon { 
            position: absolute !important; left: 14px !important; top: 50% !important; 
            transform: translateY(-50%) !important; color: #6c757d !important; pointer-events: none !important; 
        }
        .topbar-center .topbar-search {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 8px 12px !important;
            border: 1px solid #e9ecef !important;
            border-radius: 10px !important;
            background: #f8f9fb !important;
            width: 100% !important;
        }
        .topbar-center .topbar-search svg.lucide,
        .topbar-center .topbar-search i { color: #868e96 !important; flex-shrink: 0 !important; }
        .topbar-center .topbar-search .form-control,
        .topbar-center .topbar-search .ts-wrapper {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            border: 0 !important;
            background: transparent !important;
            width: 100% !important;
        }
        /* Global search dropdown results */
        .topbar-search .ts-dropdown .mm-result-repository,
        .custom_modal .ts-dropdown .mm-result-repository {
            display: block !important;
            width: 100% !important;
            padding: 2px 0 !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository .ag-flex-column,
        .custom_modal .ts-dropdown .mm-result-repository .ag-flex-column {
            display: flex !important;
            flex-direction: column !important;
            min-width: 0 !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository-stats,
        .custom_modal .ts-dropdown .mm-result-repository-stats {
            flex-shrink: 0 !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository__title,
        .custom_modal .ts-dropdown .mm-result-repository__title {
            font-weight: 600 !important;
            color: #343a40 !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository__description,
        .custom_modal .ts-dropdown .mm-result-repository__description {
            color: #6c757d !important;
            font-size: 12px !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository-stats,
        .custom_modal .ts-dropdown .mm-result-repository-stats {
            display: block !important;
            margin-top: 4px !important;
        }
        .topbar-search .ts-dropdown .ui.label.mm-result-repository__statistics,
        .custom_modal .ts-dropdown .ui.label.mm-result-repository__statistics {
            display: inline-block !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            padding: 4px 10px !important;
            border-radius: 4px !important;
            text-transform: lowercase !important;
            margin: 0 !important;
            border: none !important;
        }
        /* Match client detail search: blue badge for client/lead */
        .topbar-search .ts-dropdown .ui.label.yellow.mm-result-repository__statistics,
        .custom_modal .ts-dropdown .ui.label.yellow.mm-result-repository__statistics {
            background-color: #6777ef !important;
            color: #fff !important;
        }
        .topbar-search .ts-dropdown .ui.label.mm-result-repository__statistics:not(.yellow),
        .custom_modal .ts-dropdown .ui.label.mm-result-repository__statistics:not(.yellow) {
            background-color: #e9ecef !important;
            color: #495057 !important;
        }
        .topbar-search .ts-dropdown .mm-result-repository-stats:empty,
        .custom_modal .ts-dropdown .mm-result-repository-stats:empty {
            display: none !important;
            margin-top: 0 !important;
        }
        .topbar-right { display: flex !important; align-items: center !important; gap: 12px !important; }
        /* Dropdown */
        .icon-dropdown { position: relative !important; }
        .icon-dropdown-menu { 
            position: absolute !important; top: 48px !important; left: 0 !important; 
            background: #fff !important; border: 1px solid #e9ecef !important; 
            border-radius: 8px !important; min-width: 200px !important; 
            padding: 6px 0 !important; display: none !important; 
            box-shadow: 0 12px 24px rgba(0,0,0,.08) !important; 
        }
        .icon-dropdown .icon-dropdown-menu.show { display: block !important; }
        #crm-access-notification-dropdown .icon-dropdown-menu {
            left: auto !important;
            right: 0 !important;
            min-width: 300px !important;
            max-width: min(400px, calc(100vw - 16px)) !important;
            max-height: 420px !important;
            overflow: auto !important;
        }
        .icon-dropdown-menu .dropdown-item { padding: 8px 12px !important; color: #343a40 !important; }
        .icon-dropdown-menu .dropdown-item:hover { background: #f1f5ff !important; color: #0d6efd !important; }
        /* Profile */
        .profile-dropdown { position: relative !important; }
        .profile-trigger img { width: 36px !important; height: 36px !important; border-radius: 50% !important; object-fit: cover !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .profile-trigger { display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 44px !important; height: 44px !important; border-radius: 50% !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .profile-trigger:hover { background: #f3f5f7 !important; }
        .profile-menu { 
            position: absolute !important; 
            right: 0 !important; 
            top: 48px !important; 
            background: #fff !important; 
            border: 1px solid #e9ecef !important; 
            border-radius: 8px !important; 
            min-width: 200px !important; 
            padding: 6px 0 !important; 
            display: none !important; 
            box-shadow: 0 12px 24px rgba(0,0,0,.08) !important; 
            z-index: 1000 !important;
            opacity: 0 !important;
            transform: translateY(-10px) !important;
            transition: all 0.2s ease !important;
        }
        .profile-dropdown .profile-menu.show { 
            display: block !important; 
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        .profile-menu a { 
            display: flex !important; 
            align-items: center !important;
            padding: 10px 16px !important; 
            color: #343a40 !important; 
            text-decoration: none !important; 
            transition: all 0.2s ease !important;
            font-size: 14px !important;
        }
        .profile-menu a:hover { background: #f1f5ff !important; color: #0d6efd !important; }
        .profile-menu a i { 
            margin-right: 10px !important; 
            width: 16px !important; 
            text-align: center !important;
        }
        .profile-menu .dropdown-divider {
            height: 1px !important;
            margin: 6px 0 !important;
            background-color: #e9ecef !important;
        }

        /* When topbar is hidden, reclaim space for content (leave 6px gap) */
        body.topbar-hidden .crm-container { margin-top: 6px !important; }
        .crm-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 0;
            min-height: calc(100vh - 70px);
            padding: 15px;
            gap: 20px;
        }
        .main-sidebar {
            /* Hidden to move navigation to top menu */
            display: none !important;
        }
        .sidebar-expanded {
            width: 220px !important;
        }
        .main-content {
            flex: 1;
            background-color: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            min-width: 0;
            margin-left: 0 !important;
            transition: margin-left 0.3s ease;
        }
        .activity-feed {
            flex: 0 0 300px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-height: calc(100vh);
            overflow-y: auto;
        }
        .client-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #dee2e6; }
        .client-header h1 { font-size: 1.8em; font-weight: 600; color: #212529; margin: 0; }
        .client-rating { display: inline-block; background-color: #e9ecef; color: #495057; padding: 3px 8px; font-size: 0.8em; border-radius: 4px; margin-left: 10px; vertical-align: middle; }
        .client-status { display: flex; align-items: center; gap: 15px; }
        .status-badge { background-color: #cfe2ff; color: #0d6efd; padding: 5px 10px; border-radius: 15px; font-weight: 500; font-size: 0.9em; }
        .btn { padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; font-weight: 500; transition: background-color 0.2s ease, box-shadow 0.2s ease; }
        .btn i { margin-right: 5px; }
        .btn-primary { background-color: #0d6efd; color: white; }
        .btn-primary:hover { background-color: #0b5ed7; box-shadow: 0 2px 5px rgba(13, 110, 253, 0.3); }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5c636a; }
        .btn-block { display: block; width: 100%; margin-top: 15px; }
        .content-tabs { margin-bottom: 25px; border-bottom: 1px solid #dee2e6; display: flex; gap: 5px; }
        .tab-button { background-color: transparent; border: none; border-bottom: 3px solid transparent; padding: 10px 18px; cursor: pointer; font-size: 0.95em; color: #6c757d; transition: color 0.2s ease, border-color 0.2s ease; margin-bottom: -1px; }
        .tab-button:hover { color: #0d6efd; }
        .tab-button.active { color: #0d6efd; border-bottom-color: #0d6efd; font-weight: 600; }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
        .card h3 { font-size: 1.1em; font-weight: 600; margin-bottom: 15px; color: #343a40; display: flex; align-items: center; }
        .card h3 i { margin-right: 8px; color: #6c757d; }
        .field-group { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #f1f3f5; font-size: 0.9em; }
        .field-group:last-child { border-bottom: none; }
        .field-label { color: #6c757d; font-weight: 500; padding-right: 10px; }
        .field-value { color: #212529; text-align: right; }
        
        /* Microsoft Teams-like Notification Styles */
        .teams-notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .teams-notification {
            background: linear-gradient(135deg, #6264a7 0%, #464775 100%);
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 10px;
            overflow: hidden;
            transform: translateX(400px);
            transition: transform 0.3s ease-in-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .teams-notification.show {
            transform: translateX(0);
        }
        
        .teams-notification.hide {
            transform: translateX(400px);
        }
        
        .teams-notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .teams-notification-title {
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .teams-notification-title svg.lucide,
        .teams-notification-title i {
            margin-right: 8px;
            width: 16px;
            height: 16px;
        }
        
        .teams-notification-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .teams-notification-btn {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            font-size: 12px;
        }
        
        .teams-notification-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .teams-notification-body {
            padding: 16px;
            color: #ffffff;
        }
        
        .teams-notification-sender {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .teams-notification-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            color: #6264a7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .teams-notification-sender-info {
            flex: 1;
        }
        
        .teams-notification-sender-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .teams-notification-message {
            font-size: 13px;
            line-height: 1.4;
            opacity: 0.9;
            margin-bottom: 12px;
        }
        
        .teams-notification-reply {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 8px;
        }
        
        .teams-notification-reply input {
            flex: 1;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 13px;
            outline: none;
        }
        
        .teams-notification-reply input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .teams-notification-reply-btn {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        
        .teams-notification-reply-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .teams-notification-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .teams-notification-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .teams-notification-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .teams-notification-action-btn.primary {
            background: #ffffff;
            color: #6264a7;
            font-weight: 600;
        }
        
        .teams-notification-action-btn.primary:hover {
            background: rgba(255, 255, 255, 0.9);
        }
        
        @media (max-width: 1200px) {
            .activity-feed { flex: 0 0 280px; }
            .main-content { margin-left: 70px; }
        }
        @media (max-width: 992px) {
            .crm-container { flex-direction: column; margin-top: 0; }
            .main-sidebar { display: none !important; }
            .sidebar-expanded { width: 100%; }
            .main-content { margin-left: 0; width: 100%; }
            .activity-feed { flex: 0 0 auto; width: 100%; max-height: none; }
            .client-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .client-status { width: 100%; justify-content: space-between; }
            .content-tabs { flex-wrap: wrap; }
            .content-grid { grid-template-columns: 1fr; }
            .main-footer { position: relative; }
            
            .teams-notification-container {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
        .sidebar-mini .main-content { padding-left: 25px !important; }
        .activity-feed h2 { font-size: 1.3em; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; display: flex; align-items: center; }
        .activity-feed h2 i { margin-right: 10px; color: #6c757d; }
        .feed-list { list-style: none; }
        .feed-item { display: flex; gap: 15px; padding: 14px 0; border-bottom: 1px solid #e9ecef; align-items: flex-start; }
        .feed-item--stage { padding: 14px 0; }
        .feed-item:last-child { border-bottom: none; }
        .feed-icon { font-size: 1.1em; color: #6c757d; width: 25px; text-align: center; flex-shrink: 0; padding-top: 2px; }
        .feed-item--email .feed-icon { color: #0d6efd; }
        .feed-item--call .feed-icon { color: #198754; }
        .feed-item--doc .feed-icon { color: #ffc107; }
        .feed-item--note .feed-icon { color: #6f42c1; }
        .feed-item--system .feed-icon { color: #adb5bd; }
        .feed-icon-stage, .feed-item--stage .feed-icon { color: #0d6efd; background: rgba(13, 110, 253, 0.08); width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .feed-item-stage { padding: 2px 0; }
        .feed-item-stage-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px; }
        .feed-item-stage-header .feed-item-staff { font-weight: 600; color: #212529; font-size: 0.92em; }
        .feed-item-stage-header .feed-timestamp { font-size: 0.78em; color: #6c757d; }
        .feed-item-stage-body { font-size: 0.9em; line-height: 1.5; color: #495057; }
        .feed-item-stage-body b { color: #212529; font-weight: 600; }
        .feed-content p { margin-bottom: 4px; font-size: 0.9em; }
        .feed-content strong { font-weight: 600; }
        .feed-timestamp { font-size: 0.8em; color: #6c757d; }
        .main-footer { position: fixed; bottom: 0; width: 100%; z-index: 800; background: #fff; padding: 10px; }
        @media (max-width: 1200px) {
            .activity-feed { flex: 0 0 280px; }
            .main-content { margin-left: 70px; }
        }
        @media (max-width: 992px) {
            .crm-container { flex-direction: column; margin-top: 0; }
            .main-sidebar { display: none !important; }
            .sidebar-expanded { width: 100%; }
            .main-content { margin-left: 0; width: 100%; }
            .activity-feed { flex: 0 0 auto; width: 100%; max-height: none; }
            .client-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .client-status { width: 100%; justify-content: space-between; }
            .content-tabs { flex-wrap: wrap; }
            .content-grid { grid-template-columns: 1fr; }
            .main-footer { position: relative; }
        }
        .sidebar-mini .main-content { padding-left: 25px !important; }

        /* Ensure submenu items display on single lines */
        .main-sidebar .sidebar-menu .dropdown-menu li {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        
        .main-sidebar .sidebar-menu .dropdown-menu li a {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .main-sidebar .sidebar-menu .dropdown-menu li a span {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            flex: 1 !important;
        }
        /* Broadcast Notification Box - Top Right Corner */
        .broadcast-banner {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            max-width: 380px;
            min-width: 320px;
            display: none;
            background: linear-gradient(135deg, #005792 0%, #00BBF0 100%);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            /* Use 100% of self width (not a fixed 450px) so off-screen slide cannot widen the document */
            transform: translateX(calc(100% + 40px));
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.3s ease;
            opacity: 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .broadcast-banner.is-visible {
            display: block;
            transform: translateX(0);
            opacity: 1;
        }

        .broadcast-banner.is-clickable {
            cursor: pointer;
        }

        .broadcast-banner__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.15);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .broadcast-banner__header-title {
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            margin: 0;
        }

        .broadcast-banner__header-title svg.lucide,
        .broadcast-banner__header-title i {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }

        .broadcast-banner__close-btn {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            font-size: 16px;
            line-height: 1;
            opacity: 0.9;
        }

        .broadcast-banner__close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            opacity: 1;
        }

        .broadcast-banner__content {
            padding: 18px;
            color: #ffffff;
        }

        .broadcast-banner__title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 10px;
            color: #ffffff;
            line-height: 1.4;
        }

        .broadcast-banner__title:not(.has-title) {
            display: none;
        }

        .broadcast-banner__message {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
            color: rgba(255, 255, 255, 0.95);
        }

        .broadcast-banner__meta {
            font-size: 12px;
            opacity: 0.85;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .broadcast-banner__meta svg.lucide,
        .broadcast-banner__meta i {
            font-size: 11px;
        }

        .broadcast-banner__actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .broadcast-banner__btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.95);
            color: #005792;
            flex: 1;
        }

        .broadcast-banner__btn:hover {
            background: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .broadcast-banner__btn--primary {
            background: #ffffff;
            color: #005792;
            border: none;
            font-weight: 700;
        }

        .broadcast-banner__btn--primary:hover {
            background: #e8f4ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .broadcast-banner__btn--ghost {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.6);
            color: #ffffff;
        }

        .broadcast-banner__btn--ghost:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.9);
        }

        @media (max-width: 768px) {
            .broadcast-banner {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                min-width: auto;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="sidebar-mini" data-crm-layout="dashboard">
    <div class="broadcast-banner" data-broadcast-banner>
        <div class="broadcast-banner__header">
            <h5 class="broadcast-banner__header-title">
                @icon('fa-bullhorn')
                Broadcast Message
            </h5>
            <button type="button" class="broadcast-banner__close-btn" data-action="dismiss" aria-label="Close">
                @icon('fa-times')
            </button>
        </div>
        <div class="broadcast-banner__content">
            <div class="broadcast-banner__title" data-broadcast-title></div>
            <div class="broadcast-banner__message" data-broadcast-message></div>
            <div class="broadcast-banner__meta" data-broadcast-meta>
                @icon('fa-info-circle')
                <span data-broadcast-meta-text></span>
            </div>
            <div class="broadcast-banner__actions">
                <button type="button" class="broadcast-banner__btn broadcast-banner__btn--primary" data-action="open-broadcast">View Broadcast</button>
                <button type="button" class="broadcast-banner__btn broadcast-banner__btn--ghost" data-action="dismiss">Dismiss</button>
            </div>
        </div>
    </div>
    <div class="loader"></div>
    @include('components.crm-popuploader')
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('../Elements/CRM/header_client_detail')
            @yield('content')
            @include('../Elements/CRM/footer_client_detail')
        </div>
    </div>

    <!-- Scripts -->
    <?php
    if(@Settings::sitedata('date_format') != 'none'){
        $date_format = @Settings::sitedata('date_format');
        if($date_format == 'd/m/Y'){
            $dataformat = 'DD/MM/YYYY';
        } else if($date_format == 'm/d/Y'){
            $dataformat = 'MM/DD/YYYY';
        } else if($date_format == 'Y-m-d'){
            $dataformat = 'YYYY-MM-DD';
        } else{
            $dataformat = 'YYYY-MM-DD';
        }
    } else{
        $dataformat = 'YYYY-MM-DD';
    }
    ?>
    <script>
    var site_url = '{{URL::to('/')}}';
    var dataformat = '{{$dataformat}}';
    </script>
    <script src="{{asset('js/app.min.js')}}"></script>
    @vite(['resources/js/vendor-libs.js', 'resources/js/vendor-pdfmake.js'])
    <!-- TinyMCE loads on demand via tinymce-loader.js (notes, email, office visits still init) -->
    <script src="{{ asset('js/tinymce-loader.js') }}?v={{ @filemtime(public_path('js/tinymce-loader.js')) ?: time() }}" data-tinymce-src="{{ asset('js/tinymce/js/tinymce/tinymce.min.js') }}"></script>
    <script src="{{asset('js/custom-form-validation.js')}}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{asset('js/bootstrap5-jquery-compat.js')}}"></script>
    <script src="{{asset('js/scripts.js')}}"></script>
    <script src="{{asset('js/custom.js')}}"></script>
    @vite(['resources/js/layouts/crm-layout-shared.js'])
    @auth('admin')
    <script>window.crmLoginUrl = {!! json_encode(route('crm.login')) !!};</script>
    <script src="{{asset('js/cross-tab-logout.js')}}"></script>
    @endauth
    <script>
        $(document).ready(function () {
            $('.js-data-example-ajaxccsearch').mmSelect({
                closeOnSelect: true,
                minimumInputLength: 2,
                ajax: {
                    url: '{{URL::to('/clients/get-allclients')}}',
                    dataType: 'json',
                    delay: 350,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        // Transforms the top-level key of the response object from 'items' to 'results'
                        return {
                            results: data.items || []
                        };
                    },
                    cache: false
                },
                templateResult: formatRepomain,
                templateSelection: formatRepoSelectionmain
            });

            function formatRepomain (repo) {
                if (repo.loading) {
                    return repo.text;
                }

                var $container = $(
                    "<div dataid='" + (repo.cid || '').toString().replace(/'/g, '&#39;').replace(/&/g, '&amp;') + "' class='selectclient mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>\u00A0</div>" +
                        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small ></div>" +

                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                        "<span class='mm-result-repository-stats'>" +

                        "</span>" +
                    "</div>" +
                    "</div>"
                );

                $container.find(".mm-result-repository__title").text(repo.name);
                $container.find(".mm-result-repository__description").text(repo.email);
                if (repo.locked) {
                    $container.addClass('opacity-75');
                    $container.find(".mm-result-repository__title").prepend('<span class="mr-1" title="No access">&#128274;</span> ');
                    var ui = repo.access_ui || {};
                    if (ui.show_quick) {
                        $container.find(".mm-result-repository-stats").append('<span class="ui label tiny">Quick</span> ');
                    }
                    if (ui.show_supervisor) {
                        $container.find(".mm-result-repository-stats").append('<span class="ui label tiny">Supervisor</span> ');
                    }
                }
                if (repo.status == 'Archived') {
                    $container.find(".mm-result-repository-stats").append('<span class="ui label mm-result-repository__statistics">'+repo.status+'</span>');
                } else if (repo.status) {
                    $container.find(".mm-result-repository-stats").append('<span class="ui label yellow mm-result-repository__statistics">'+repo.status+'</span>');
                }
                return $container;
            }

            function formatRepoSelectionmain (repo) {
                return repo.name || repo.text;
            }

            $('.js-data-example-ajaxccsearch').on('mmselect:select', function (e) {
                var data = e.params.data || {};
                if (data.locked && typeof window.openCrmAccessModal === 'function') {
                    $(this).val(null).trigger('change');
                    window.openCrmAccessModal(data);
                    return;
                }
                var v = data.id;
                if (!v) { return; }
                var s = String(v).split('/');
                if(s[1] == 'Matter' && s[2] != ''){
                    window.location = '{{URL::to('/clients/detail/')}}/'+s[0]+'/'+s[2];
                } else {
                    if(s[1] == 'Client'){
                        window.location = '{{URL::to('/clients/detail/')}}/'+s[0];
                    }  else{
                        window.location = '{{URL::to('/leads/history/')}}/'+s[0];
                    }
                }
            });


            function hydrateCheckinDetailIcons() {
                var root = document.querySelector('.showchecindetail');
                if (typeof window.refreshLucideIcons === 'function' && root) {
                    window.refreshLucideIcons(root);
                }
            }

            $(document).delegate('.opencheckin', 'click', function(){
                $('#checkinmodal').modal('show');
            });

            $(document).delegate('.visitpurpose', 'blur', function(){
                var visitpurpose = $(this).val();
                var appliid = $(this).attr('data-id');
                $('.popuploader').show();
                $.ajax({
                    url: site_url+'/update_visit_purpose',
                    type:'POST',
                    data:{id: appliid,visit_purpose:visitpurpose},
                    success: function(responses){
                        $.ajax({
                            url: site_url+'/get-checkin-detail',
                            type:'GET',
                            data:{id: appliid},
                            success: function(res){
                                $('.popuploader').hide();
                                $('.showchecindetail').html(res);
                                hydrateCheckinDetailIcons();
                            },
                            error: function(){
                                $('.popuploader').hide();
                            }
                        });
                    },
                    error: function(){
                        $('.popuploader').hide();
                    }
                });
            });

            $(document).delegate('.savevisitcomment', 'click', function(){
                var visitcomment = $('.visit_comment').val();
                var appliid = $(this).attr('data-id');
                $('.popuploader').show();
                $.ajax({
                    url: site_url+'/update_visit_comment',
                    type:'POST',
                    data:{id: appliid,visit_comment:visitcomment},
                    success: function(responses){
                        // $('.popuploader').hide();
                        $('.visit_comment').val('');
                        $.ajax({
                            url: site_url+'/get-checkin-detail',
                            type:'GET',
                            data:{id: appliid},
                            success: function(res){
                                $('.popuploader').hide();
                                $('.showchecindetail').html(res);
                                hydrateCheckinDetailIcons();
                            },
                            error: function(){
                                $('.popuploader').hide();
                            }
                        });
                    },
                    error: function(){
                        $('.popuploader').hide();
                    }
                });
            });

            $(document).delegate('.attendsession', 'click', function(){
                var appliid = $(this).attr('data-id');
                $('.popuploader').show();
                $.ajax({
                    url: site_url+'/attend_session',
                    type:'POST',
                    data:{id: appliid,waitcountdata: $('#waitcountdata').val()},
                    success: function(response){
                        try {
                            var obj = $.parseJSON(response);
                        } catch (e) {
                            $('.popuploader').hide();
                            return;
                        }
                        if(obj.status){
                            $.ajax({
                                url: site_url+'/get-checkin-detail',
                                type:'GET',
                                data:{id: appliid},
                                success: function(res){
                                    $('.popuploader').hide();
                                    $('.showchecindetail').html(res);
                                    hydrateCheckinDetailIcons();
                                },
                                error: function(){
                                    $('.popuploader').hide();
                                }
                            });
                            $('.checindata #id_'+appliid).remove();
                            alert(obj.message);
                        } else {
                            $('.popuploader').hide();
                        }
                    },
                    error: function(){
                        $('.popuploader').hide();
                    }
                });
            });

            $(document).delegate('.completesession', 'click', function(){
                var appliid = $(this).attr('data-id');
                $('.popuploader').show();
                $.ajax({
                    url: site_url+'/complete_session',
                    type:'POST',
                    data:{id: appliid,attendcountdata: $('#attendcountdata').val()},
                    success: function(response){
                        try {
                            var obj = $.parseJSON(response);
                        } catch (e) {
                            $('.popuploader').hide();
                            return;
                        }
                        if(obj.status){
                            $('.popuploader').hide();
                            $('#checkindetailmodal').modal('hide');
                            $('.checindata #id_'+appliid).remove();
                            alert('Session is completed successfully');
                        } else {
                            $('.popuploader').hide();
                            alert(obj.message);
                        }
                    },
                    error: function(){
                        $('.popuploader').hide();
                    }
                });
            });

            $(document).delegate('.opencheckindetail', 'click', function(){
                $('#checkindetailmodal').modal('show');
                $('.popuploader').show();
                var appliid = $(this).attr('id');
                $.ajax({
                    url: site_url+'/get-checkin-detail',
                    type:'GET',
                    data:{id: appliid},
                    success: function(responses){
                        $('.popuploader').hide();
                        $('.showchecindetail').html(responses);
                        hydrateCheckinDetailIcons();
                    },
                    error: function(){
                        $('.popuploader').hide();
                    }
                });
            });

            /* $(".niceCountryInputSelector").each(function(i,e){
                new NiceCountryInput(e).init();
            }); */
            //$('.country_input').flagStrap();

            $('.drop_table_data button').on('click', function(){
                $('.client_dropdown_list').toggleClass('active');
            });

            $('.client_dropdown_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.client_table_data table tr td').show();
                        $('.client_table_data table tr th').show();
                        $('.client_dropdown_list label.dropdown-option input').prop('checked', true);
                    } else {
                        $('.client_dropdown_list label.dropdown-option input').prop('checked', false);
                        $('.client_table_data table tr td').hide();
                        $('.client_table_data table tr th').hide();
                        $('.client_table_data table tr td:nth-child(1)').show();
                        $('.client_table_data table tr th:nth-child(1)').show();
                        $('.client_table_data table tr td:nth-child(2)').show();
                        $('.client_table_data table tr th:nth-child(2)').show();
                        $('.client_table_data table tr td:nth-child(17)').show();
                        $('.client_table_data table tr th:nth-child(17)').show();
                    }
                }
                else
                {

                    if ($(this).is(":checked")) {
                        $('.client_table_data table tr td:nth-child('+val+')').show();
                        $('.client_table_data table tr th:nth-child('+val+')').show();
                    } else {
                        $('.client_dropdown_list label.dropdown-option.all input').prop('checked', false);
                        $('.client_table_data table tr td:nth-child('+val+')').hide();
                        $('.client_table_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.client_report_list').toggleClass('active');
            });

            $('.client_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.client_report_data table tr td').show();
                        $('.client_report_data table tr th').show();
                        $('.client_report_list label.dropdown-option input').prop('checked', true);
                    } else {
                        $('.client_report_list label.dropdown-option input').prop('checked', false);
                        $('.client_report_data table tr td').hide();
                        $('.client_report_data table tr th').hide();
                        $('.client_report_data table tr td:nth-child(1)').show();
                        $('.client_report_data table tr th:nth-child(1)').show();
                        $('.client_report_data table tr td:nth-child(2)').show();
                        $('.client_report_data table tr th:nth-child(2)').show();
                        $('.client_report_data table tr td:nth-child(11)').show();
                        $('.client_report_data table tr th:nth-child(11)').show();
                    }
                }
                else
                {
                    if ($(this).is(":checked")) {
                        $('.client_report_data table tr td:nth-child('+val+')').show();
                        $('.client_report_data table tr th:nth-child('+val+')').show();
                    } else {
                        $('.client_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.client_report_data table tr td:nth-child('+val+')').hide();
                        $('.client_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.application_report_list').toggleClass('active');
            });

            $('.application_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.application_report_data table tr td').show();
                        $('.application_report_data table tr th').show();
                        $('.application_report_list label.dropdown-option input').prop('checked', true);
                    }else{
                        $('.application_report_list label.dropdown-option input').prop('checked', false);
                        $('.application_report_data table tr td').hide();
                        $('.application_report_data table tr th').hide();
                        $('.application_report_data table tr td:nth-child(1)').show();
                        $('.application_report_data table tr th:nth-child(1)').show();
                        $('.application_report_data table tr td:nth-child(2)').show();
                        $('.application_report_data table tr th:nth-child(2)').show();
                        $('.application_report_data table tr td:nth-child(3)').show();
                        $('.application_report_data table tr th:nth-child(3)').show();
                        $('.application_report_data table tr td:nth-child(5)').show();
                        $('.application_report_data table tr th:nth-child(5)').show();
                        $('.application_report_data table tr td:nth-child(7)').show();
                        $('.application_report_data table tr th:nth-child(7)').show();
                    }
                } else {

                    if ($(this).is(":checked")) {
                        $('.application_report_data table tr td:nth-child('+val+')').show();
                        $('.application_report_data table tr th:nth-child('+val+')').show();
                    }
                    else{
                        $('.application_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.application_report_data table tr td:nth-child('+val+')').hide();
                        $('.application_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.officevisit_report_list').toggleClass('active');
            });

            $('.officevisit_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.officevisit_report_data table tr td').show();
                        $('.officevisit_report_data table tr th').show();
                        $('.officevisit_report_list label.dropdown-option input').prop('checked', true);
                    }else{
                        $('.officevisit_report_list label.dropdown-option input').prop('checked', false);
                        $('.officevisit_report_data table tr td').hide();
                        $('.officevisit_report_data table tr th').hide();
                        $('.officevisit_report_data table tr td:nth-child(1)').show();
                        $('.officevisit_report_data table tr th:nth-child(1)').show();
                        $('.officevisit_report_data table tr td:nth-child(2)').show();
                        $('.officevisit_report_data table tr th:nth-child(2)').show();
                        $('.officevisit_report_data table tr td:nth-child(4)').show();
                        $('.officevisit_report_data table tr th:nth-child(4)').show();
                    }
                } else {
                    if ($(this).is(":checked")) {
                        $('.officevisit_report_data table tr td:nth-child('+val+')').show();
                        $('.officevisit_report_data table tr th:nth-child('+val+')').show();
                    }
                    else{
                        $('.officevisit_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.officevisit_report_data table tr td:nth-child('+val+')').hide();
                        $('.officevisit_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.invoice_report_list').toggleClass('active');
            });

            $('.invoice_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.invoice_report_data table tr td').show();
                        $('.invoice_report_data table tr th').show();
                        $('.invoice_report_list label.dropdown-option input').prop('checked', true);
                    }else{
                        $('.invoice_report_list label.dropdown-option input').prop('checked', false);
                        $('.invoice_report_data table tr td').hide();
                        $('.invoice_report_data table tr th').hide();
                        $('.invoice_report_data table tr td:nth-child(1)').show();
                        $('.invoice_report_data table tr th:nth-child(1)').show();
                        $('.invoice_report_data table tr td:nth-child(2)').show();
                        $('.invoice_report_data table tr th:nth-child(2)').show();
                        $('.invoice_report_data table tr td:nth-child(4)').show();
                        $('.invoice_report_data table tr th:nth-child(4)').show();
                    }
                } else {

                    if ($(this).is(":checked")) {
                        $('.invoice_report_data table tr td:nth-child('+val+')').show();
                        $('.invoice_report_data table tr th:nth-child('+val+')').show();
                    }
                    else{
                        $('.invoice_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.invoice_report_data table tr td:nth-child('+val+')').hide();
                        $('.invoice_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.saleforecast_applic_report_list').toggleClass('active');
            });

            $('.saleforecast_applic_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.saleforecast_application_report_data table tr td').show();
                        $('.saleforecast_application_report_data table tr th').show();
                        $('.saleforecast_applic_report_list label.dropdown-option input').prop('checked', true);
                    }else{
                        $('.saleforecast_applic_report_list label.dropdown-option input').prop('checked', false);
                        $('.saleforecast_application_report_data table tr td').hide();
                        $('.saleforecast_application_report_data table tr th').hide();
                        $('.saleforecast_application_report_data table tr td:nth-child(1)').show();
                        $('.saleforecast_application_report_data table tr th:nth-child(1)').show();
                        $('.saleforecast_application_report_data table tr td:nth-child(2)').show();
                        $('.saleforecast_application_report_data table tr th:nth-child(2)').show();
                        $('.saleforecast_application_report_data table tr td:nth-child(4)').show();
                        $('.saleforecast_application_report_data table tr th:nth-child(4)').show();
                    }
                }else{
                    if ($(this).is(":checked")) {
                        $('.saleforecast_application_report_data table tr td:nth-child('+val+')').show();
                        $('.saleforecast_application_report_data table tr th:nth-child('+val+')').show();
                    }
                    else{
                        $('.saleforecast_applic_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.saleforecast_application_report_data table tr td:nth-child('+val+')').hide();
                        $('.saleforecast_application_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('.drop_table_data button').on('click', function(){
                $('.interest_service_report_list').toggleClass('active');
            });

            $('.interest_service_report_list label.dropdown-option input').on('click', function(){
                var val = $(this).val();
                if(val == 'all'){
                    if ($(this).is(":checked")) {
                        $('.interest_service_report_data table tr td').show();
                        $('.interest_service_report_data table tr th').show();
                        $('.interest_service_report_list label.dropdown-option input').prop('checked', true);
                    }else{
                        $('.interest_service_report_list label.dropdown-option input').prop('checked', false);
                        $('.interest_service_report_data table tr td').hide();
                        $('.interest_service_report_data table tr th').hide();
                        $('.interest_service_report_data table tr td:nth-child(1)').show();
                        $('.interest_service_report_data table tr th:nth-child(1)').show();
                        $('.interest_service_report_data table tr td:nth-child(2)').show();
                        $('.interest_service_report_data table tr th:nth-child(2)').show();
                        $('.interest_service_report_data table tr td:nth-child(10)').show();
                        $('.interest_service_report_data table tr th:nth-child(10)').show();
                        $('.interest_service_report_data table tr td:nth-child(14)').show();
                        $('.interest_service_report_data table tr th:nth-child(14)').show();
                    }

                }else{

                    if ($(this).is(":checked")) {
                        $('.interest_service_report_data table tr td:nth-child('+val+')').show();
                        $('.interest_service_report_data table tr th:nth-child('+val+')').show();
                    }
                    else{
                        $('.interest_service_report_list label.dropdown-option.all input').prop('checked', false);
                        $('.interest_service_report_data table tr td:nth-child('+val+')').hide();
                        $('.interest_service_report_data table tr th:nth-child('+val+')').hide();
                    }
                }
            });

            $('#personal_details .is_business').hide();

            $('.modal-body form#addgroupinvoice .is_superagentinv').hide();

            $('#agentstructure input[name="struture"]').on('change', function(){
                var id = $(this).attr('id');
                if(id == 'individual'){
                    $('#personal_details .is_business').hide();
                    $('#personal_details .is_individual').show();
                    $('#personal_details .is_business input').attr('data-valid', '');
                    $('#personal_details .is_individual input').attr('data-valid', 'required');
                }
                else{
                    $('#personal_details .is_individual').hide();
                    $('#personal_details .is_business').show();
                    $('#personal_details .is_business input').attr('data-valid', 'required');
                    $('#personal_details .is_individual input').attr('data-valid', '');
                }
            });

            $('.modal-body form#addgroupinvoice input[name="partner_type"]').on('change', function(){
                var invid = $(this).attr('id');
                if(invid == 'superagent_inv'){
                    $('.modal-body form#addgroupinvoice .is_partnerinv').hide();
                    $('.modal-body form#addgroupinvoice .is_superagentinv').show();
                    $('.modal-body form#addgroupinvoice .is_partnerinv input').attr('data-valid', '');
                    $('.modal-body form#addgroupinvoice .is_superagentinv input').attr('data-valid', 'required');
                }
                else{
                    $('.modal-body form#addgroupinvoice .is_superagentinv').hide();
                    $('.modal-body form#addgroupinvoice .is_partnerinv').show();
                    $('.modal-body form#addgroupinvoice .is_partnerinv input').attr('data-valid', 'required');
                    $('.modal-body form#addgroupinvoice .is_superagentinv input').attr('data-valid', '');
                }
            });

            $('.modal .modal-body .is_partner').hide();
            $('.modal .modal-body .is_application').hide();
            $('.modal .modal-body input[name="related_to"]').on('change', function(){
                var relid = $(this).attr('id');
                if(relid == 'contact'){
                    $('.modal .modal-body .is_partner').hide();
                    $('.modal .modal-body .is_application').hide();
                    $('.modal .modal-body .is_contact').show();
                    $('.modal .modal-body .is_partner select').attr('data-valid', '');
                    $('.modal .modal-body .is_application select').attr('data-valid', '');
                    $('.modal .modal-body .is_contact select').attr('data-valid', 'required');
                }
                else if(relid == 'partner'){
                    $('.modal .modal-body .is_contact').hide();
                    $('.modal .modal-body .is_application').hide();
                    $('.modal .modal-body .is_partner').show();
                    $('.modal .modal-body .is_contact select').attr('data-valid', '');
                    $('.modal .modal-body .is_application select').attr('data-valid', '');
                    $('.modal .modal-body .is_partner select').attr('data-valid', 'required');
                }
                else if(relid == 'client_portal'){
                    $('.modal .modal-body .is_contact').hide();
                    $('.modal .modal-body .is_partner').hide();
                    $('.modal .modal-body .is_application').show();
                    $('.modal .modal-body .is_contact select').attr('data-valid', '');
                    $('.modal .modal-body .is_partner select').attr('data-valid', '');
                    $('.modal .modal-body .is_application select').attr('data-valid', 'required');
                }
                else{
                    $('.modal .modal-body .is_contact').hide();
                    $('.modal .modal-body .is_partner').hide();
                    $('.modal .modal-body .is_application').hide();
                    $('.modal .modal-body .is_contact input').attr('data-valid', '');
                    $('.modal .modal-body .is_partner input').attr('data-valid', '');
                    $('.modal .modal-body .is_application input').attr('data-valid', '');
                }
            });

            $('#internal select[name="source"]').on('change', function(){
                var sourceval = $(this).val();
                if(sourceval == 'Sub Agent'){
                    $('#internal .is_subagent').show();
                    $('#internal .is_subagent select').attr('data-valid', 'required');
                } else{
                    $('#internal .is_subagent').hide();
                    $('#internal .is_subagent select').attr('data-valid', '');
                }
            });

            $('.card .card-body .grid_data').hide();
            $('.card .card-body .document_layout_type a.list').on('click', function(){
                $('.card .card-body .document_layout_type a').removeClass('active');
                $(this).addClass('active');
                $('.card .card-body .grid_data').hide();
                $('.card .card-body .list_data').show();
            });

            $('.card .card-body .document_layout_type a.grid').on('click', function(){
                $('.card .card-body .document_layout_type a').removeClass('active');
                $(this).addClass('active');
                $('.card .card-body .list_data').hide();
                $('.card .card-body .grid_data').show();
            });

            function formatRepocheck (repo) {
                if (repo.loading) {
                    return repo.text;
                }

                var $container = $(
                    "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>\u00A0</div>" +
                        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small ></div>" +

                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                        "<span class='mm-result-repository-stats'>" +

                        "</span>" +
                    "</div>" +
                    "</div>"
                );

                $container.find(".mm-result-repository__title").text(repo.name);
                $container.find(".mm-result-repository__description").text(repo.email);
                if (repo.status == 'Archived') {
                    $container.find(".mm-result-repository-stats").append('<span class="ui label mm-result-repository__statistics">'+repo.status+'</span>');
                } else if (repo.status) {
                    $container.find(".mm-result-repository-stats").append('<span class="ui label yellow mm-result-repository__statistics">'+repo.status+'</span>');
                }
                return $container;
            }

            function formatRepoSelectioncheck (repo) {
                return repo.name || repo.text;
            }

            function ensureCheckinOptionListsLoaded() {
                if (window.__checkinOptionListsPromise) {
                    return window.__checkinOptionListsPromise;
                }
                var $modal = $('#checkinmodal');
                if (!$modal.length) {
                    return $.Deferred().resolve().promise();
                }
                var $office = $modal.find('select[name="office"]');
                var $assignee = $modal.find('select.assignee-mm-select');
                var hasOffices = $office.find('option').filter(function() { return $(this).val(); }).length > 0;
                var hasAssignees = $assignee.find('option').filter(function() { return $(this).val(); }).length > 0;
                if (hasOffices && hasAssignees) {
                    window.__checkinOptionListsPromise = $.Deferred().resolve().promise();
                    return window.__checkinOptionListsPromise;
                }
                window.__checkinOptionListsPromise = $.getJSON('{{ URL::to("/get-checkin-option-lists") }}')
                    .done(function(res) {
                        if (!hasOffices && res && res.offices) {
                            res.offices.forEach(function(office) {
                                $office.append($('<option></option>').attr('value', office.id).text(office.office_name || ''));
                            });
                        }
                        if (!hasAssignees && res && res.assignees) {
                            $assignee.empty();
                            res.assignees.forEach(function(staff) {
                                $assignee.append(
                                    $('<option></option>').attr('value', staff.id).text((staff.first_name || '') + ' (' + (staff.email || '') + ')')
                                );
                            });
                        }
                    })
                    .fail(function() {
                        window.__checkinOptionListsPromise = null;
                    });
                return window.__checkinOptionListsPromise;
            }

            function initCheckinModalTomSelects() {
                if (typeof $.fn.mmSelect === 'undefined') {
                    return;
                }
                var $modal = $('#checkinmodal');
                if (!$modal.length) {
                    return;
                }

                var $contact = $modal.find('.js-data-example-ajax-check');
                if ($contact.length) {
                    if ($contact.data('mmSelect')) {
                        $contact.mmSelect('destroy');
                    }
                    $contact.mmSelect({
                        closeOnSelect: true,
                        placeholder: 'Search by phone, email, name, or client ID...',
                        allowClear: true,
                        width: '100%',
                        minimumInputLength: 2,
                        dropdownParent: 'body',
                        dropdownCssClass: 'mm-checkin-modal-dropdown',
                        ajax: {
                            url: '{{URL::to('/clients/get-recipients')}}',
                            dataType: 'json',
                            delay: 350,
                            data: function (params) {
                                return { q: params.term || '' };
                            },
                            processResults: function (data) {
                                return {
                                    results: (data && data.items) ? data.items : []
                                };
                            },
                            cache: true
                        },
                        templateResult: formatRepocheck,
                        templateSelection: formatRepoSelectioncheck
                    });
                }

                var $assignee = $modal.find('.assignee-mm-select');
                if ($assignee.length) {
                    if ($assignee.data('mmSelect')) {
                        $assignee.mmSelect('destroy');
                    }
                    $assignee.mmSelect({
                        dropdownParent: 'body',
                        dropdownCssClass: 'mm-checkin-modal-dropdown',
                        minimumResultsForSearch: 0,
                        width: '100%'
                    });
                }
            }

            $('#checkinmodal').on('mmselect:select', '.js-data-example-ajax-check', function(e) {
                var data = e.params.data;
                var contactType = data.status || data.type || 'client';
                contactType = contactType.toLowerCase();
                if (contactType === 'lead') {
                    contactType = 'Lead';
                } else if (contactType === 'client') {
                    contactType = 'Client';
                } else {
                    contactType = 'Client';
                }
                $('#utype').val(contactType);
            });

            $('#checkinmodal').on('mmselect:clear', '.js-data-example-ajax-check', function() {
                $('#utype').val('');
            });

            $(document).on('shown.bs.modal', '#checkinmodal', function () {
                $.when(ensureCheckinOptionListsLoaded()).always(function() {
                    initCheckinModalTomSelects();
                });
            });
            if ($('#checkinmodal').length) {
                ensureCheckinOptionListsLoaded();
            }

            /* $('.timepicker').timepicker({
                minuteStep: 1,
                showSeconds: true,
            }); */
        });

        $(document).ready(function()
        {
            (function () {
                var bell = document.getElementById('countbell_notification');
                if (!bell || bell.closest('#crm-access-notification-dropdown')) return;
                var p = bell.parentNode;
                if (!p) return;
                p.addEventListener('click', function () {
                    window.location = "/all-notifications";
                });
            })();;

            /*function load_unseen_notification(view = '')
            {
                $.ajax({
                    url:"{{URL::to('/fetch-notification')}}",
                    method:"GET",
                    dataType:"json",
                    success:function(data)
                    {
                        //$('.showallnotifications').html(data.notification);
                        if(data.unseen_notification > 0)
                        {
                            $('.countbell').html(data.unseen_notification);
                        }
                    }
                });
            }*/

            /*function load_unseen_messages(view = '')
            {
                load_unseen_notification();
                var playing = false;
                $.ajax({
                    url:"{{URL::to('/fetch-messages')}}",
                    method:"GET",
                    success:function(data)
                    {
                        if(data != 0){
                            iziToast.show({
                                backgroundColor: 'rgba(0,0,255,0.3)',
                                messageColor: 'rgba(255,255,255)',
                                title: '',
                                message: data,
                                position: 'bottomRight'
                            });
                            $(this).toggleClass("down");

                            if (playing == false) {
                                document.getElementById('player').play();
                                playing = true;
                                $(this).text("stop sound");

                            } else {
                                document.getElementById('player').pause();
                                playing = false;
                                $(this).text("restart sound");
                            }
                        }
                    }
                });
            }*/

            /*
            function load_InPersonWaitingCount(view = '') {
                $.ajax({
                    url:"{{URL::to('/fetch-InPersonWaitingCount')}}",
                    method:"GET",
                    dataType:"json",
                    success:function(data) {
                        //$('.showallnotifications').html(data.notification);
                        if(data.InPersonwaitingCount > 0){
                            $('.countInPersonWaitingAction').html(data.InPersonwaitingCount);
                        }
                    }
                });
            }*/

            /*function load_TotalActivityCount(view = '') {
                $.ajax({
                    url:"{{URL::to('/fetch-TotalActivityCount')}}",
                    method:"GET",
                    dataType:"json",
                    success:function(data) {
                        if(data.assigneesCount > 0){
                            $('.countTotalActivityAction').html(data.assigneesCount);
                        }
                    }
                });
            }
            */
            setInterval(function(){
                //load_unseen_notification();
                //load_unseen_messages();
                //load_InPersonWaitingCount();
                //load_TotalActivityCount();
            },5000);
            
            // Teams-like notification functionality
            function loadOfficeVisitNotifications() {
                $.ajax({
                    url: "{{URL::to('/fetch-office-visit-notifications')}}",
                    method: "GET",
                    dataType: "json",
                    success: function(data) {
                        var list = (data && data.notifications) ? data.notifications : [];
                        list.forEach(function(notification) {
                            showTeamsNotification(notification);
                        });
                    }
                });
            }
            
            function showTeamsNotification(notification) {
                // Check if notification already exists
                if ($('#teams-notification-' + notification.id).length > 0) {
                    return;
                }

                var isReception = notification.is_reception_alert ? true : false;
                var primaryLabel = isReception ? 'Client Sent' : 'Pls Send The Client';
                var primaryWaitingType = isReception ? 1 : 0;

                var notificationHtml = `
                    <div class="teams-notification" id="teams-notification-${notification.id}">
                        <div class="teams-notification-header">
                            <div class="teams-notification-title">
                                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-users') : '<i class="fas fa-users"></i>'}
                                Office Visit Assignment
                            </div>
                            <div class="teams-notification-controls">
                                <button class="teams-notification-btn" onclick="minimizeNotification(${notification.id})">
                                    ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-minus') : '<i class="fas fa-minus"></i>'}
                                </button>
                                <button class="teams-notification-btn" onclick="closeNotification(${notification.id})">
                                    ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-times') : '<i class="fas fa-times"></i>'}
                                </button>
                            </div>
                        </div>
                        <div class="teams-notification-body">
                            <div class="teams-notification-sender">
                                <div class="teams-notification-sender-info">
                                    <div class="teams-notification-sender-name">${notification.sender_name}</div>
                                    <div class="teams-notification-message">${notification.message}</div>
                                </div>
                            </div>
                            <div class="teams-notification-message">
                                <strong>Client:</strong> ${notification.client_name}<br>
                                <strong>Purpose:</strong> ${notification.visit_purpose}<br>
                                <strong>Time:</strong> ${typeof formatDisplayDateTime === 'function' ? formatDisplayDateTime(notification.created_at) : (notification.created_at || '')}
                            </div>
                            <div class="teams-notification-actions">
                                <button class="teams-notification-action-btn primary" onclick="attendSession(${notification.checkin_id}, ${notification.id}, ${primaryWaitingType})">
                                    ${primaryLabel}
                                </button>
                                <button class="teams-notification-action-btn" onclick="viewDetails(${notification.checkin_id})">
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#teamsNotificationContainer').append(notificationHtml);
                
                if (typeof window.playOfficeVisitNotificationSound === 'function') {
                    window.playOfficeVisitNotificationSound();
                }
                
                // Show the notification with animation
                setTimeout(function() {
                    $('#teams-notification-' + notification.id).addClass('show');
                }, 100);
                
                // Check database status every 5 seconds to see if task is no longer waiting
                var statusCheckInterval = setInterval(function() {
                    $.ajax({
                        url: "{{URL::to('/check-checkin-status')}}",
                        method: "GET",
                        data: { checkin_id: notification.checkin_id },
                        success: function(response) {
                            if (response.status !== 0) {
                                // Task is no longer waiting, close notification
                                clearInterval(statusCheckInterval);
                                closeNotification(notification.id);
                            }
                        }
                    });
                }, 5000);
            }

            window.showTeamsNotification = showTeamsNotification;
            if (typeof window.drainOfficeVisitNotificationQueue === 'function') {
                window.drainOfficeVisitNotificationQueue();
            }
            
            window.closeNotification = function(notificationId) {
                var notification = $('#teams-notification-' + notificationId);
                if (notification.length > 0) {
                    notification.removeClass('show').addClass('hide');
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                    
                    // Mark notification as seen
                    $.ajax({
                        url: "{{URL::to('/mark-notification-seen')}}",
                        method: "POST",
                        data: {
                            notification_id: notificationId,
                            _token: "{{ csrf_token() }}"
                        }
                    });
                }
            };
            
            window.minimizeNotification = function(notificationId) {
                var notification = $('#teams-notification-' + notificationId);
                notification.toggleClass('minimized');
            };
            
            // Make functions globally accessible
            window.attendSession = function(checkinId, notificationId, waitingtype) {
                waitingtype = (typeof waitingtype !== 'undefined') ? parseInt(waitingtype, 10) : 0;
                // Close notification immediately when button is clicked
                if (notificationId) {
                    closeNotification(notificationId);
                }
                
                $.ajax({
                    url: site_url + '/attend_session',
                    method: "POST",
                    data: {
                        id: checkinId,
                        waitingtype: waitingtype,
                        waitcountdata: '00h:00m:00s',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        try {
                            var parsed = (typeof response === 'string') ? JSON.parse(response) : response;
                        } catch (e) {
                            alert('Error updating checkin status');
                            return;
                        }

                        if (parsed && parsed.status) {
                            // Remove the row from waiting listing page
                            var row = $('tr[did="' + checkinId + '"]');
                            if (row.length > 0) {
                                row.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        } else {
                            alert('Error updating status');
                        }
                    },
                    error: function() {
                        alert('Error updating checkin status');
                    }
                });
            };
            
            window.viewDetails = function(checkinId) {
                // Open checkin details modal
                $('#checkindetailmodal').modal('show');
                $('.popuploader').show();
                $.ajax({
                    url: site_url + '/get-checkin-detail',
                    type: 'GET',
                    data: {id: checkinId},
                    success: function(response) {
                        $('.popuploader').hide();
                        $('.showchecindetail').html(response);
                        hydrateCheckinDetailIcons();
                    }
                });
            };
            
            // Defer office visit notifications until after initial paint (Echo listener is in app.js)
            function deferOfficeVisitNotificationsLoad() {
                if (typeof requestIdleCallback === 'function') {
                    requestIdleCallback(function () { loadOfficeVisitNotifications(); }, { timeout: 2000 });
                } else {
                    setTimeout(loadOfficeVisitNotifications, 300);
                }
            }
            deferOfficeVisitNotificationsLoad();
        
        // Profile dropdown hover functionality
        let profileHoverTimeout;
        const profileTrigger = document.getElementById('profile-trigger');
        const profileMenu = document.getElementById('profile-menu');
        
        if (profileTrigger && profileMenu) {
            // Show dropdown on hover
            profileTrigger.addEventListener('mouseenter', function() {
                clearTimeout(profileHoverTimeout);
                profileMenu.classList.add('show');
            });
            
            // Hide dropdown when mouse leaves
            profileTrigger.addEventListener('mouseleave', function() {
                profileHoverTimeout = setTimeout(function() {
                    profileMenu.classList.remove('show');
                }, 150);
            });
            
            // Keep dropdown open when hovering over menu
            profileMenu.addEventListener('mouseenter', function() {
                clearTimeout(profileHoverTimeout);
            });
            
            // Hide dropdown when mouse leaves menu
            profileMenu.addEventListener('mouseleave', function() {
                profileHoverTimeout = setTimeout(function() {
                    profileMenu.classList.remove('show');
                }, 150);
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!profileTrigger.contains(event.target) && !profileMenu.contains(event.target)) {
                    profileMenu.classList.remove('show');
                }
            });
        }
    });
    </script>

    <script>
    (function() {
        function cleanupStaleOverlays() {
            // popuploader can become "display:none" but still sit above page in some edge cases;
            // ensure it cannot block clicks unless explicitly shown.
            var pl = document.querySelector('.popuploader');
            if (pl) {
                var cs = window.getComputedStyle(pl);
                if (cs.display === 'none') {
                    pl.style.pointerEvents = 'none';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', cleanupStaleOverlays);
        window.addEventListener('load', cleanupStaleOverlays);
    })();
    </script>

    <script>
    (function() {
        function cleanupStaleBackdrops() {
            // Only clean when no modal is visible.
            if (document.querySelector('.modal.show')) return;

            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops.forEach(function(b) {
                    try { b.parentNode && b.parentNode.removeChild(b); } catch (e) {}
                });
            }

            // If a stale modal-open remains it can block page interaction.
            if (document.body.classList.contains('modal-open')) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }
        }

        document.addEventListener('DOMContentLoaded', cleanupStaleBackdrops);
        window.addEventListener('pageshow', cleanupStaleBackdrops);
        window.addEventListener('load', function () {
            cleanupStaleBackdrops();
            // Catch backdrops inserted slightly after load (async scripts / modals).
            requestAnimationFrame(function () {
                cleanupStaleBackdrops();
                setTimeout(cleanupStaleBackdrops, 300);
            });
        });
    })();
    </script>
    
    <!-- Microsoft Teams-like Notification Container -->
    <div class="teams-notification-container" id="teamsNotificationContainer">
        <!-- Notifications will be dynamically added here -->
    </div>
    
    @if (!request()->routeIs(['leads.create', 'leads.edit', 'clients.edit']))
        @include('crm.partials.cross-access-modal', ['crossAccessLeadBase' => url('/leads/history')])
    @endif
    <script src="{{ asset('js/components/task-description-mentions.js') }}?v={{ @filemtime(public_path('js/components/task-description-mentions.js')) ?: time() }}"></script>
    @stack('scripts')
    
    {{-- Define updateNotificationBell before Vite so it's available when Echo/client_portal receive events --}}
    <script>
    (function() {
        window.updateNotificationBell = function(count, options) {
            options = options || {};
            var el = document.getElementById('countbell_notification');
            if (!el) return;
            var prevCount = parseInt(String(el.textContent || '0'), 10) || 0;
            var newCount = (typeof count === 'number') ? count : (parseInt(String(count), 10) || 0);
            el.textContent = newCount > 0 ? String(newCount) : '';
            el.style.display = newCount > 0 ? 'inline' : 'none';
            var parent = el.closest('.notification-toggle') || el.parentElement;
            if (parent) {
                parent.classList.add('notification-bell-flash');
                setTimeout(function() { parent.classList.remove('notification-bell-flash'); }, 600);
            }
            if (options.showToast !== false && newCount > prevCount && typeof iziToast !== 'undefined' && iziToast.show) {
                var toastMsg = options.message || (newCount === 1 ? 'You have a new notification' : 'You have ' + (newCount - prevCount) + ' new notification(s)');
                var toastOpts = { title: 'Notification', message: toastMsg, position: 'topRight', color: 'blue', timeout: 5000, closeOnClick: true };
                if (options.url) toastOpts.onClick = function() { window.location.href = options.url; };
                iziToast.show(toastOpts);
            }
        };
    })();
    </script>
    {{-- Vite: Load Laravel Echo with Reverb for real-time WebSocket notifications --}}
    {{-- Must load BEFORE broadcasts.js so window.Echo is available --}}
    @vite(['resources/js/app.js', 'resources/js/lucide-init.js'])
    @include('components.crm-layout-vite-config')
    @vite(['resources/js/layouts/crm-echo-broadcasts.js'])
</body>
</html>
