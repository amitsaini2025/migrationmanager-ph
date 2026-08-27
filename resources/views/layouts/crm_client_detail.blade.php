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
            transform: translateX(450px);
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            background-color: #f8f9fa; 
            color: #343a40; 
            line-height: 1.6; 
            overflow-x: hidden;
        }
        .main-wrapper { 
            position: relative; 
            min-height: 100vh;
        }
        .main-navbar { 
            position: fixed; 
            top: 0; 
            left: 0;
            right: 0;
            width: 100%; 
            z-index: 1000; 
            background-color: #fff; 
            height: 70px;
        }
        .crm-container {
            display: flex;
            margin-top: 0;
            min-height: calc(100vh - 70px);
            padding: 15px;
            gap: 20px;
            align-items: flex-start;
        }
        /* Override existing main-sidebar styles for fixed positioning */
        .main-sidebar {
            /* Hidden to move navigation to top menu */
            display: none !important;
        }
        .sidebar-expanded {
            width: 220px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        /* Client Navigation Sidebar — flex column + scrollable nav so last items (e.g. Client Portal) stay reachable */
        .client-navigation-sidebar {
            flex: 0 0 260px;
            background: #fafbfc;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            max-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
            min-height: 0;
            position: sticky;
            top: 20px;
            border: 1px solid #e8eaed;
        }
        
        
        .sidebar-header {
            flex-shrink: 0;
            padding: 16px 14px 12px;
            border-bottom: 1px solid #e8eaed;
            background: #ffffff;
            border-radius: 10px 10px 0 0;
        }
        
        .client-info {
            margin-bottom: 0;
            text-align: left;
        }
        
        .client-id {
            margin: 0 0 8px 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: #4a5568;
            line-height: 1.3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            letter-spacing: 0.5px;
        }
        
        .client-matter {
            margin: 0 0 3px 0;
            font-size: 0.85rem;
            color: #718096;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .client-name {
            margin: 0 0 10px 0;
            font-size: 1.15rem;
            color: #1a202c;
            font-weight: 600;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .client-name-edit {
            color: #10b981 !important;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .client-name-edit:hover {
            color: #059669 !important;
            transform: scale(1.1);
        }
        
        /* Sidebar Actions Row - Icons left, Toggle right */
        .sidebar-actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding: 8px 0 12px;
            border-bottom: 1px solid #e8eaed;
        }
        
        .client-actions {
            display: flex;
            gap: 14px;
            align-items: center;
            justify-content: flex-start;
        }
        
        .client-actions a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: auto;
            height: auto;
            transition: all 0.2s ease;
            text-decoration: none;
            background: transparent;
            border: none;
        }
        
        .client-actions a:hover {
            transform: scale(1.15);
        }
        
        .client-actions a i {
            font-size: 18px;
            color: #64748b;
        }
        
        .client-actions a:hover i {
            color: #6366f1;
        }
        
        .client-actions a.archived-active i {
            color: #6366f1;
        }
        
        /* Sidebar Portal Toggle */
        .sidebar-portal-toggle {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
        }
        
        /* Tooltip on hover */
        .sidebar-portal-toggle::before {
            content: 'Client Portal';
            position: absolute;
            right: 0;
            top: -30px;
            background-color: #1a202c;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            pointer-events: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            z-index: 10;
        }
        
        .sidebar-portal-toggle::after {
            content: '';
            position: absolute;
            right: 15px;
            top: -8px;
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid #1a202c;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            pointer-events: none;
            z-index: 10;
        }
        
        .sidebar-portal-toggle:hover::before,
        .sidebar-portal-toggle:hover::after {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar-portal-toggle .portal-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #4a5568;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: none;
        }
        
        /* Sidebar Lead Badge (shown only for leads; hidden once converted to client) */
        .sidebar-client-lead-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 14px;
            margin-bottom: 12px;
        }
        
        .status-btn {
            padding: 7px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .status-btn-lead {
            background-color: #e2e8f0;
            color: #475569;
        }
        
        .status-btn-lead.active {
            background-color: #64748b;
            color: #ffffff;
        }

        .status-btn-client {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-btn-client.active {
            background-color: #2563eb;
            color: #ffffff;
        }
        
        .status-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .lead-status-badge {
            cursor: default;
            pointer-events: none;
        }

        .lead-status-badge:hover {
            transform: none;
            opacity: 1;
        }

        .client-status-badge {
            cursor: default;
            pointer-events: none;
        }

        .client-status-badge:hover {
            transform: none;
            opacity: 1;
        }
        
        /* Sidebar matter selector: room for chevron, menu not clipped */
        .sidebar-matter-selection {
            margin: 0 0 10px 0;
            min-width: 0;
            width: 100%;
            position: relative;
            z-index: 4;
            overflow: visible;
        }
        
        .visa-dropdown {
            width: 100%;
            padding: 8px 10px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #1a202c;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #ffffff;
            cursor: pointer;
            transition: border-color 0.2s ease;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .visa-dropdown:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .matter-dropdown-wrap .option {
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            line-height: 1.4 !important;
        }
        
        .sidebar-matter-selection .ts-wrapper {
            width: 100% !important;
            max-width: 100% !important;
            position: relative;
            z-index: 4;
        }
        /* Tom Select strips chevron on .form-control; restore + keep label clear of icon */
        .sidebar-matter-selection .ts-wrapper.single {
            --ts-pr-caret: 2.25rem;
            height: auto;
        }
        .sidebar-matter-selection .ts-wrapper.single .ts-control {
            height: auto !important;
            min-height: 38px;
            align-items: flex-start;
            padding: 8px 2.25rem 8px 10px !important;
            background-color: #ffffff !important;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23374151' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3E%3C/svg%3E") !important;
            background-position: right 0.65rem center !important;
            background-repeat: no-repeat !important;
            background-size: 16px 12px !important;
            overflow: hidden;
        }
        .sidebar-matter-selection .ts-wrapper.single .ts-control > .item {
            display: block !important;
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
            max-width: calc(100% - 1.5rem);
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word;
            line-height: 1.35;
        }
        /* Sidebar matter list stacks over nav; z-index higher than default .ts-dropdown */
        .sidebar-matter-selection .ts-dropdown {
            z-index: 2000;
        }
        
        /* Application Status Badge */
        .matter-status-badge {
            display: inline-block;
            background-color: #f3f4f6;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            margin: 10px 0;
            width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        /* Divider before navigation */
        .sidebar-header {
            border-bottom: 1px solid #e8eaed;
        }
        
        .initial-consultation-heading {
            margin: 12px 0 0 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            text-align: center;
            padding: 6px 0;
            border-top: 1px solid #e8eaed;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .client-sidebar-nav {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 6px;
        }
        
        .client-nav-button {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 8px 12px;
            margin-bottom: 2px;
            border: none;
            background: transparent;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 8px;
            text-align: left;
            position: relative;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .client-nav-button:hover {
            background: rgba(102, 126, 234, 0.08);
            color: #667eea;
            transform: translateX(1px);
        }
        
        .client-nav-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
            border-left: 3px solid #764ba2;
        }
        
        .client-nav-button i {
            font-size: 14px;
            width: 18px;
            text-align: center;
            opacity: 0.8;
        }
        
        .client-nav-button.active i {
            opacity: 1;
            color: white;
        }
        
        .client-nav-button.active span {
            color: white;
        }
        
        .client-nav-button span {
            font-size: 0.85rem;
            line-height: 1.3;
            font-weight: inherit;
        }

        /* Document toolbar tab triggers (e.g. Not Used Documents): not full-width like sidebar */
        .client-nav-button.client-nav-button--inline {
            width: auto;
            flex: 0 0 auto;
            min-width: 0;
            margin-bottom: 0;
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
            position: relative;
            /* Do not set z-index here: it creates a stacking context below .main-topbar (z-index: 1000),
               which traps fixed Bootstrap modals (e.g. Move Document) under the navbar. */
            height: auto;
            max-height: none;
        }
        .activity-feed {
            flex: 0 0 300px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-y: auto;
            /* Height will be set dynamically via JavaScript to match main-content */
        }
        .client-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #dee2e6; }
        .client-header h1 { font-size: 1.8em; font-weight: 600; color: #212529; margin: 0; }
        .client-rating { display: inline-block; background-color: #e9ecef; color: #495057; padding: 3px 8px; font-size: 0.8em; border-radius: 4px; margin-left: 10px; vertical-align: middle; }
        .client-status { display: flex; align-items: center; gap: 15px; }
        .status-badge { background-color: #cfe2ff; color: #0d6efd; padding: 5px 10px; border-radius: 15px; font-weight: 500; font-size: 0.9em; }
        .btn { padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; font-weight: 500; transition: background-color 0.2s ease, box-shadow 0.2s ease; }
        .btn i { margin-right: 5px; }
        .btn-primary { background-color: #3498db; border-color: #3498db; color: #ffffff; font-weight: 500; transition: all 0.2s ease; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3); transform: translateY(-1px); }
        .btn-secondary { background-color: #95a5a6; border-color: #95a5a6; color: #ffffff; font-weight: 500; }
        .btn-secondary:hover { background-color: #7f8c8d; border-color: #7f8c8d; }
        .btn-success { background-color: #27ae60; border-color: #27ae60; color: #ffffff; font-weight: 500; }
        .btn-success:hover { background-color: #229954; border-color: #229954; }
        .btn-warning { background-color: #f39c12; border-color: #f39c12; color: #ffffff; font-weight: 500; }
        .btn-warning:hover { background-color: #e67e22; border-color: #e67e22; }
        .btn-danger { background-color: #e74c3c; border-color: #e74c3c; color: #ffffff; font-weight: 500; }
        .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5c636a; }
        .btn-block { display: block; width: 100%; margin-top: 15px; }
        .content-tabs { margin-bottom: 25px; border-bottom: 1px solid #dee2e6; display: flex; gap: 5px; }
        .tab-button { background-color: transparent; border: none; border-bottom: 3px solid transparent; padding: 10px 18px; cursor: pointer; font-size: 0.95em; color: #6c757d; transition: color 0.2s ease, border-color 0.2s ease; margin-bottom: -1px; }
        .tab-button:hover { color: #0d6efd; }
        .tab-button.active { color: #0d6efd; border-bottom-color: #0d6efd; font-weight: 600; }
        
        /* Vertical Tabs Styles */
        .main-content-with-tabs {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .vertical-tabs {
            display: flex;
            flex-direction: column;
            width: 280px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
            flex-shrink: 0;
        }
        
        .vertical-tabs::-webkit-scrollbar {
            width: 4px;
        }
        
        .vertical-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }
        
        .vertical-tabs::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .vertical-tabs::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .vertical-tab-button {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 4px;
            border: none;
            background: transparent;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 6px;
            text-align: left;
            position: relative;
        }
        
        .vertical-tab-button:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            transform: translateX(2px);
        }
        
        .vertical-tab-button.active {
            background: #ecf0f1;
            color: #2c3e50;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(52, 73, 94, 0.15);
            border-left: 3px solid #3498db;
        }
        
        .vertical-tab-button i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .vertical-tab-button span {
            font-size: 0.9em;
            line-height: 1.2;
        }
        
        .tab-content {
            flex: 1;
            min-width: 0;
        }
        .content-grid { 
            display: grid; 
            grid-template-columns: 1fr; 
            gap: 20px; 
        }
        
        /* Medium to large screens - 2 tiles per row */
        @media (min-width: 768px) and (max-width: 1600px) {
            .content-grid { 
                grid-template-columns: repeat(2, 1fr); 
            }
        }
        
        /* Very large screens only - 3 tiles per row */
        @media (min-width: 1601px) {
            .content-grid { 
                grid-template-columns: repeat(3, 1fr); 
            }
        }
        .card { background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
        .card h3 { font-size: 1.1em; font-weight: 600; margin-bottom: 15px; color: #343a40; display: flex; align-items: center; }
        .card h3 i { margin-right: 8px; color: #6c757d; }
        .field-group { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #f1f3f5; font-size: 0.9em; }
        .field-group:last-child { border-bottom: none; }
        .field-label { color: #6c757d; font-weight: 500; padding-right: 10px; }
        .field-value { color: #212529; text-align: right; }
        .activity-feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .activity-feed-header-actions { display: flex; align-items: center; gap: 8px; }
        .activity-feed-refresh { color: #6c757d; }
        .activity-feed-refresh:hover { color: #0d6efd; }
        .activity-feed h2 { font-size: 1.3em; margin: 0 0 20px 0; font-weight: 600; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; display: flex; align-items: center; flex: 1; }
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
            .client-navigation-sidebar { flex: 0 0 250px; }
        }
        
        @media (max-width: 768px) {
            .client-navigation-sidebar {
                flex: 0 0 auto;
                width: 100%;
                max-height: none;
                position: relative;
                top: auto;
                margin-bottom: 15px;
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
            
            .sidebar-header {
                padding: 15px 12px 12px;
            }
            
            .client-info {
                margin-bottom: 12px;
            }
            
            .client-id {
                font-size: 1rem;
            }
            
            .client-matter {
                font-size: 0.85rem;
            }
            
            .client-name {
                font-size: 0.9rem;
            }
            
            .client-actions {
                gap: 8px;
            }
            
            .client-actions a {
                width: 28px;
                height: 28px;
            }
            
            .client-actions a i {
                font-size: 12px;
            }
            
            .initial-consultation-heading {
                font-size: 0.9rem;
                margin: 12px 0 0 0;
                padding: 6px 0;
            }
            
            .client-sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 12px;
                flex: none;
                min-height: unset;
                overflow-x: visible;
                overflow-y: visible;
                overscroll-behavior: auto;
            }
            
            .client-nav-button {
                flex: 1;
                min-width: calc(50% - 4px);
                margin-bottom: 0;
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .client-nav-button.client-nav-button--inline {
                flex: 0 0 auto;
                min-width: 0;
            }
            
            .client-nav-button i {
                font-size: 14px;
                width: 16px;
            }
        }
        @media (max-width: 992px) {
            .crm-container { 
                flex-direction: column; 
                margin-top: 0; 
            }
            .main-sidebar { 
                position: fixed; 
                top: 70px; 
                left: -100%; 
                width: 250px; 
                height: calc(100vh - 70px);
                transition: left 0.3s ease;
                z-index: 999;
            }
            .main-sidebar.show {
                left: 0;
            }
            .sidebar-expanded { width: 250px; }
            .main-content { margin-left: 0; width: 100%; }
            .activity-feed { flex: 0 0 auto; width: 100%; max-height: none; }
            .client-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .client-status { width: 100%; justify-content: space-between; }
            .content-tabs { flex-wrap: wrap; }
            .content-grid { grid-template-columns: 1fr !important; }
            .main-footer { position: relative; }
            
            /* Client navigation sidebar responsive */
            .client-navigation-sidebar {
                flex: 0 0 auto;
                width: 100%;
                max-height: none;
                min-height: unset;
                display: flex;
                flex-direction: column;
                position: relative;
                top: auto;
                margin-bottom: 20px;
                order: 1;
            }
            
            .main-content {
                order: 2;
            }
            
            .activity-feed {
                order: 3;
            }
            
            .teams-notification-container {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
        .sidebar-mini .main-content { 
            padding-left: 25px !important; 
            margin-left: 0 !important;
        }
        
        /* Override sidebar-mini styles */
        .sidebar-mini .main-sidebar { display: none !important; }
        
        /* Additional sidebar styles for better scrolling */
        .main-sidebar .sidebar-menu {
            height: calc(100vh - 120px) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            max-height: calc(100vh - 120px) !important;
        }
        
        /* Table Header Color Fixes - Critical for readability */
        .table thead th {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-weight: 600 !important;
            border-bottom: 2px solid #dee2e6 !important;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
            padding: 12px 8px !important;
        }
        
        .table thead th:first-child {
            border-top-left-radius: 6px;
        }
        
        .table thead th:last-child {
            border-top-right-radius: 6px;
        }
        
        /* Table body improvements */
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        /* Table body text improvements - Critical for readability */
        .table tbody td {
            color: #2c3e50 !important;
            font-weight: 500 !important;
            padding: 12px 8px !important;
            border-bottom: 1px solid #e9ecef !important;
        }
        
        .table tbody tr:hover td {
            color: #1a202c !important;
        }
        
        /* Card header improvements */
        .card-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%) !important;
            color: #ffffff !important;
            border-bottom: none !important;
            padding: 15px 20px !important;
        }
        
        .card-header h4 {
            color: #ffffff !important;
            font-weight: 600 !important;
            margin: 0 !important;
        }
        
        .card-header-action .btn-primary {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: #ffffff !important;
        }
        
        .card-header-action .btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
        }
        
        /* Dropdown menu improvements */
        .dropdown-menu {
            border: 1px solid #e9ecef !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            border-radius: 6px !important;
        }
        
        .dropdown-item {
            padding: 8px 16px !important;
            transition: all 0.2s ease !important;
        }
        
        .dropdown-item:hover {
            background-color: #3498db !important;
            color: #ffffff !important;
        }
        
        .dropdown-item i {
            margin-right: 8px !important;
            width: 16px !important;
            text-align: center !important;
        }
        
        /* Action button improvements */
        .btn.dropdown-toggle {
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 6px 12px !important;
        }
        
        /* Status badges and indicators */
        .badge {
            font-weight: 500 !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
        }
        
        .badge-success {
            background-color: #27ae60 !important;
        }
        
        .badge-warning {
            background-color: #f39c12 !important;
        }
        
        .badge-danger {
            background-color: #e74c3c !important;
        }
        
        .badge-info {
            background-color: #3498db !important;
        }
        
        .badge-secondary {
            background-color: #95a5a6 !important;
        }
        
        /* Form improvements */
        .form-control:focus {
            border-color: #3498db !important;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25) !important;
        }
        
        .form-group label {
            color: #2c3e50 !important;
            font-weight: 600 !important;
            margin-bottom: 6px !important;
        }
        
        /* Pagination improvements */
        .pagination .page-link {
            color: #3498db !important;
            border-color: #dee2e6 !important;
        }
        
        .pagination .page-link:hover {
            color: #2980b9 !important;
            background-color: #ecf0f1 !important;
            border-color: #3498db !important;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #3498db !important;
            border-color: #3498db !important;
            color: #ffffff !important;
        }
        
        /* Alert improvements */
        .alert-success {
            background-color: #d5f4e6 !important;
            border-color: #27ae60 !important;
            color: #155724 !important;
        }
        
        .alert-warning {
            background-color: #fff3cd !important;
            border-color: #f39c12 !important;
            color: #856404 !important;
        }
        
        .alert-danger {
            background-color: #f8d7da !important;
            border-color: #e74c3c !important;
            color: #721c24 !important;
        }
        
        .alert-info {
            background-color: #d1ecf1 !important;
            border-color: #3498db !important;
            color: #0c5460 !important;
        }
        
        /* Custom switches and toggles */
        .custom-switch .custom-switch-input:checked ~ .custom-switch-indicator {
            background: #3498db !important;
        }
        
        /* Loading states */
        .btn:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
        }
        
        /* Focus improvements for accessibility */
        .btn:focus,
        .form-control:focus,
        .dropdown-toggle:focus {
            outline: 2px solid #3498db !important;
            outline-offset: 2px !important;
        }
        
        /* Responsive table improvements */
        @media (max-width: 768px) {
            .table thead th {
                font-size: 0.75em !important;
                padding: 8px 4px !important;
            }
        }
        
        .main-sidebar .sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }
        
        .main-sidebar .sidebar-menu::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .main-sidebar .sidebar-menu::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .main-sidebar .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Force sidebar to stay fixed */
        .main-sidebar { display: none !important; }
        
        /* Simple fix for dropdown menus - let the existing theme handle most styling */
        .sidebar-mini .main-sidebar .sidebar-menu li ul.dropdown-menu {
            position: absolute !important;
            left: 65px !important;
            top: 10px !important;
            width: 200px !important;
            z-index: 1001 !important;
            display: none !important;
        }
        
        /* Ensure hover works for dropdown menus */
        .sidebar-mini .main-sidebar .sidebar-menu li:hover > ul.dropdown-menu {
            display: block !important;
        }
        
        /* Ensure menu-toggle works */
        .sidebar-mini .main-sidebar .sidebar-menu li .menu-toggle.toggled + ul.dropdown-menu {
            display: block !important;
        }
        
        /* Allow dropdown menus to extend outside sidebar */
        .main-sidebar {
            overflow: visible !important;
        }
        
        .main-sidebar .sidebar-menu {
            overflow: visible !important;
        }
        
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
        /* Top quick icons – improve visibility and spacing */
        .top-quick-icons { 
            gap: 10px !important; 
            padding-left: 10px !important; 
            overflow-x: auto !important; 
            white-space: nowrap !important; 
            scrollbar-width: thin !important; 
        }
        .top-quick-icons .nav-link { 
            color: #495057 !important; 
            opacity: 1 !important; 
            padding: 6px 8px !important; 
            border-radius: 6px !important; 
            transition: background-color .15s ease, color .15s ease, transform .1s ease !important; 
            display: inline-flex !important; 
            align-items: center !important; 
        }
        .top-quick-icons .nav-link i { 
            font-size: 18px !important; 
            line-height: 1 !important; 
        }
        .top-quick-icons .nav-link:hover { 
            background-color: rgba(0,123,255,.1) !important; 
            color: #007bff !important; 
        }
        .top-quick-icons .nav-link.text-danger { 
            color: #dc3545 !important; 
        }
        .top-quick-icons .nav-link.text-danger:hover { 
            background-color: rgba(220,53,69,.1) !important; 
            color: #c82333 !important; 
        }
        /* Keep icons crisp on light navbar */
        .main-navbar .nav-link i { 
            filter: none !important; 
        }
        /* Modern topbar layout */
        .main-topbar { 
            display: grid !important; 
            grid-template-columns: 1fr minmax(400px, 640px) auto !important; 
            align-items: center !important; 
            gap: 16px !important; 
            position: sticky !important; 
            top: 0 !important; 
            z-index: 1000 !important; 
            height: 70px !important; 
            padding: 10px 16px !important; 
            background: #ffffff !important; 
            border-bottom: 1px solid #dfe3e6 !important; 
            box-shadow: 0 2px 8px rgba(0,0,0,.06) !important;
            transition: transform .2s ease-in-out !important;
        }
        /* Remove blank space: topbar is sticky so navbar-bg spacer is not needed */
        .main-wrapper .navbar-bg {
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        /* Content after topbar: no extra top space (listing/sheet pages) */
        .main-wrapper > .listing-container {
            margin-top: 0 !important;
            padding-top: 0 !important;
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
            width: 44px !important; height: 44px !important; border-radius: 10px !important; 
            color: #495057 !important; text-decoration: none !important; 
            transition: background-color .15s ease, color .15s ease !important; 
        }
        .icon-btn:hover { background: rgba(0,123,255,.1) !important; color: #0d6efd !important; }
        .icon-btn .countbell { position: relative !important; top: -10px !important; left: -6px !important; background: #1f1655 !important; color:#fff !important; border-radius: 10px !important; padding: 0 5px !important; font-size: 11px !important; }
        /* Flash animation when notification count updates */
        .notification-bell-flash { animation: notificationBellFlash 0.6s ease-out !important; }
        @keyframes notificationBellFlash {
            0% { background: rgba(13, 110, 253, 0.4) !important; transform: scale(1.05); }
            100% { background: transparent !important; transform: scale(1); }
        }
        .topbar-center .topbar-search { 
            display: flex !important; align-items: center !important; gap: 8px !important; 
            padding: 8px 12px !important; border: 1px solid #e9ecef !important; border-radius: 10px !important; 
            background: #f8f9fb !important; 
        }
        .topbar-center .topbar-search svg.lucide,
        .topbar-center .topbar-search i { color: #868e96 !important; }
        .topbar-center .topbar-search .form-control { border: 0 !important; background: transparent !important; width: 100% !important; }
        .topbar-right { display: flex !important; align-items: center !important; gap: 10px !important; }
        /* Dropdowns */
        .icon-dropdown { position: relative !important; }
        .icon-dropdown-menu { 
            position: absolute !important; top: 44px !important; left: 0 !important; min-width: 240px !important; 
            background: #fff !important; border: 1px solid #e9ecef !important; border-radius: 8px !important; 
            padding: 6px 0 !important; display: none !important; box-shadow: 0 12px 24px rgba(0,0,0,.08) !important; 
        }
        /* click-driven dropdowns, JS toggles .show */
        .icon-dropdown .icon-dropdown-menu.show { display: block !important; }
        /* Bell in topbar-right: global left:0 would push wide panel off-screen */
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
        .profile-trigger img { width: 36px !important; height: 36px !important; border-radius: 50% !important; object-fit: cover !important; }
        .profile-trigger { display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 44px !important; height: 44px !important; border-radius: 50% !important; }
        .profile-menu { position: absolute !important; right: 0 !important; top: 48px !important; background: #fff !important; border: 1px solid #e9ecef !important; border-radius: 8px !important; min-width: 200px !important; padding: 6px 0 !important; display: none !important; box-shadow: 0 12px 24px rgba(0,0,0,.08) !important; }
        .profile-dropdown .profile-menu.show { display: block !important; }
        .profile-menu a { display: block !important; padding: 8px 12px !important; color: #343a40 !important; text-decoration: none !important; }
        .profile-menu a:hover { background: #f1f5ff !important; color: #0d6efd !important; }

        /* When topbar is hidden, reclaim space for content (leave 6px gap) */
        body.topbar-hidden .crm-container { margin-top: 6px !important; }
        
        /* Datepicker styling improvements */
        .datepicker {
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid #e9ecef !important;
            font-family: 'Inter', sans-serif !important;
        }
        
        .datepicker table tr td.active,
        .datepicker table tr td.active:hover,
        .datepicker table tr td.active.disabled,
        .datepicker table tr td.active.disabled:hover {
            background-color: #3498db !important;
            background-image: none !important;
            color: #ffffff !important;
        }
        
        .datepicker table tr td.today {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            font-weight: 600 !important;
        }
        
        .datepicker table tr td:hover {
            background-color: #f8f9fa !important;
        }
        
        .datepicker table tr td.old,
        .datepicker table tr td.new {
            color: #adb5bd !important;
        }
        
        .datepicker table tr td.day {
            border-radius: 4px !important;
            margin: 1px !important;
        }
        
        .datepicker table tr th {
            background-color: #f8f9fa !important;
            color: #495057 !important;
            font-weight: 600 !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .datepicker-switch,
        .datepicker .datepicker-switch:hover,
        .datepicker .prev:hover,
        .datepicker .next:hover {
            background-color: #3498db !important;
            color: #ffffff !important;
            border-radius: 6px !important;
        }
        
        .datepicker .datepicker-switch {
            font-weight: 600 !important;
        }
    </style>
    @yield('styles')
</head>
<body class="sidebar-mini" data-crm-layout="detail">
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
            <div class="navbar-bg" style="height: 0 !important; min-height: 0 !important; padding: 0 !important; margin: 0 !important; display: block !important; overflow: hidden !important;"></div>
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
    <script src="{{ asset('js/custom-form-validation.js') }}?v={{ @filemtime(public_path('js/custom-form-validation.js')) ?: time() }}"></script>
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
                    url: '{{route('clients.getallclients')}}',
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
                    "<div dataid="+repo.cid+" class='selectclient mm-result-repository ag-flex ag-space-between ag-align-center')'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
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
                if(repo.status == 'Archived'){
                    $container.find(".mm-result-repository-stats").append('<span class="ui label  mm-result-repository__statistics">'+repo.status+'</span>');
                } else {
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
                    window.location = '{{ url("/clients/detail") }}/' + s[0] + '/' + s[2];
                } else {
                    if(s[1] == 'Client'){
                        window.location = '{{ url("/clients/detail") }}/' + s[0];
                    }  else{
                        window.location = '{{ url("/history") }}/' + s[0];
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
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
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
                if(repo.status == 'Archived'){
                    $container.find(".mm-result-repository-stats").append('<span class="ui label  mm-result-repository__statistics">'+repo.status+'</span>');
                }else{
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
                window.__checkinOptionListsPromise = $.getJSON('{{ url("/get-checkin-option-lists") }}')
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
                            url: '{{route('clients.getrecipients')}}',
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

        $(document).ready(function(){
            (function () {
                var bell = document.getElementById('countbell_notification');
                if (!bell || bell.closest('#crm-access-notification-dropdown')) return;
                var p = bell.parentNode;
                if (!p) return;
                p.addEventListener('click', function () {
                    window.location = "/all-notifications";
                });
            })();

        /*function load_unseen_notification(view = '')  {
            $.ajax({
                url:"{{url('/fetch-notification')}}",
                method:"GET",
                dataType:"json",
                success:function(data) {
                    //$('.showallnotifications').html(data.notification);
                    if(data.unseen_notification > 0){
                        $('.countbell').html(data.unseen_notification);
                    }
                }
            });
        }*/

        /*function load_unseen_messages(view = '')  {
            load_unseen_notification();
            var playing = false;
            $.ajax({
                url:"{{url('/fetch-messages')}}",
                method:"GET",
                success:function(data) {
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

        /*function load_InPersonWaitingCount(view = '') {
            $.ajax({
                url:"{{url('/fetch-InPersonWaitingCount')}}",
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
                url:"{{url('/fetch-TotalActivityCount')}}",
                method:"GET",
                dataType:"json",
                success:function(data) {
                    if(data.assigneesCount > 0){
                        $('.countTotalActivityAction').html(data.assigneesCount);
                    }
                }
            });
        }*/


        setInterval(function(){
            //load_unseen_notification();
            //load_unseen_messages();
            //load_InPersonWaitingCount();
            //load_TotalActivityCount();
        },120000);
        
        // Teams-like notification UI. HTTP poll lives in resources/js/app.js (Echo + fallback).
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
                    url: "{{url('/check-checkin-status')}}",
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
                    url: "{{url('/mark-notification-seen')}}",
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
    });
    </script>

    <div id="checkinmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientModalLabel">Create In Person Client</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="post" name="checkinmodalsave" id="checkinmodalsave" action="{{url('/checkin')}}" autocomplete="off" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="email_from">Search Contact <span class="span_req">*</span></label>
                                    <select data-valid="required" class="form-control js-data-example-ajax-check" name="contact" placeholder="Search by phone, email, name, or client ID..."></select>
                                    @if ($errors->has('email_from'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('email_from') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <input type="hidden" id="utype" name="utype" value="">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="email_from">Office <span class="span_req">*</span></label>
                                    <select data-valid="required" class="form-control" name="office">
                                        <option value="">Select</option>
                                    </select>

                                </div>
                            </div>

                            <div class="col-12 col-md-12 col-lg-12">
                                <div class="form-group">
                                    <label for="message">Visit Purpose <span class="span_req">*</span></label>
                                    <textarea class="form-control" name="message"></textarea>
                                    @if ($errors->has('message'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('message') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-12 col-lg-12">
                                <div class="form-group">
                                    <label for="message">Select In Person Assignee <span class="span_req">*</span></label>
                                    <select class="form-control assignee-mm-select" name="assignee">
                                    </select>
                                    @if ($errors->has('message'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('message') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-12 col-lg-12">
                                <button onclick="customValidate('checkinmodalsave')" type="button" class="btn btn-primary">Send</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="checkindetailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientModalLabel">In Person Details</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body showchecindetail">

                </div>
            </div>
        </div>
    </div>

    <!-- Microsoft Teams-like Notification Container -->
    <div class="teams-notification-container" id="teamsNotificationContainer">
        <!-- Notifications will be dynamically added here -->
    </div>

    @include('crm.partials.cross-access-modal', ['crossAccessLeadBase' => url('/history')])
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

    @include('partials.email-from-sendgrid-script')
    @yield('scripts')
</body>
</html>
