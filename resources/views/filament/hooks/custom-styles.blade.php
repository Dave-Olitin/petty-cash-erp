<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">

<style>
    .fi-tabs,
    .voucher-preview-section .fi-section-content {
        padding: 0 !important
    }

    .font-sans,
    .font-mono,
    .font-serif,
    body {
        font-family: "Manrope", "Public Sans", sans-serif, -apple-system, blinkmacsystemfont, "Segoe UI", roboto, "Helvetica Neue", arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important
    }

    .fi-section,
    .fi-modal-window,
    .fi-ta-ctn {
        border-radius: 10px !important;
    }

    .voucher-preview-section>.fi-section-header {
        border-bottom: none !important
    }

    .fi-sidebar-nav {
        border-inline-end: 1px solid #00000014;
        box-shadow: 4px 0 12px -2px rgba(0, 0, 0, .08)
    }

    .fi-tabs,
    .fi-tabs-item {
        background: 0 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important
    }

    .dark .fi-sidebar-nav {
        border-inline-end: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 4px 0 12px -2px rgba(0, 0, 0, .4)
    }

    .fi-ta-content table td.fi-ta-cell {
        padding-top: .25rem !important;
        padding-bottom: .25rem !important;
        border-bottom: 1px solid #f1f5f9
    }

    .fi-ta-header {
        flex-direction: column !important;
        align-items: flex-start !important
    }

    .fi-tabs {
        display: flex !important;
        justify-content: flex-start !important;
        width: 100% !important;
        max-width: 100% !important;
        gap: .5rem !important;
        margin-bottom: 0 !important;
        ring: 0 !important
    }

    .fi-tabs-item {
        padding: .75rem 1rem !important;
        margin: 0 !important;
        color: #aaadb1ff !important;
        font-weight: 600 !important;
        position: relative;
        transition: .2s;
        font-size: 16px !important
    }

    .fi-tabs-item:hover {
        color: #09090b !important;
        background: #f8fafc !important
    }

    .dark .fi-tabs-item:hover {
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.05) !important
    }

    .fi-tabs-item[aria-selected=true] {
        color: #09090b !important;
        background: 0 0 !important;
        box-shadow: inset 0 -3px 0 0 #09090b !important;
        border-bottom: 2px solid #09090b !important
    }

    .dark .fi-tabs-item[aria-selected=true] {
        color: #ffffff !important;
        box-shadow: inset 0 -3px 0 0 #ffffff !important;
        border-bottom: 2px solid #ffffff !important
    }

    .fi-tabs-item[aria-selected=true] span {
        color: #09090b !important;
        font-size: 20px !important
    }

    .dark .fi-tabs-item[aria-selected=true] span {
        color: #ffffff !important
    }

    .fi-tabs-item[aria-selected=true] .fi-badge {
        background: #f4f4f5 !important;
        color: #09090b !important
    }

    .dark .fi-tabs-item[aria-selected=true] .fi-badge {
        background: #27272a !important;
        color: #ffffff !important
    }

    .fi-main {
        margin-left: auto !important;
        margin-right: auto !important;
        background-color: #f8fafc !important
    }

    @media (max-width:768px) {
        .fi-wi-header {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            overflow-x: auto !important;
            gap: .5rem !important;
            padding-bottom: .5rem !important
        }

        .fi-wi-header>li {
            margin: 0 !important;
            border: none !important;
            padding-bottom: 0 !important;
            flex: 1 1 0;
            display: flex;
            justify-content: center
        }

        .fi-wi-header>li>button {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: .25rem !important;
            text-align: center !important;
            padding: 0 !important
        }

        .fi-wi-step-title {
            display: block !important;
            font-size: .75rem !important;
            white-space: normal !important;
            line-height: 1.1 !important
        }

        .fi-wi-header>li::after,
        .fi-wi-header>li>div[role=separator],
        .fi-wi-step-description {
            display: none !important
        }
    }

    .fi-wi-step-description {
        display: none !important
    }

    .fi-wi-header>li>button[aria-current=step] .fi-wi-step-description {
        display: block !important
    }

    /* Professional Widget Typography Refinements */
    .fi-wi-stats-overview-stat-label {
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.025em !important;
        text-transform: uppercase !important;
        color: #64748b !important;
    }

    .fi-wi-stats-overview-stat-value {
        font-size: 1.5rem !important;
        /* Denser text-2xl vs 3xl */
        font-weight: 800 !important;
        letter-spacing: -0.025em !important;
        color: #0f172a !important;
        /* Slate 900 */
    }

    .fi-wi-stats-overview-stat-description {
        font-size: 0.75rem !important;
        /* text-xs */
        color: #64748b !important;
    }

    .fi-wi-stats-overview-stat {
        padding: 1.25rem !important;
        gap: 0.5rem !important;
    }

    /* Completed Wizard Step Icon UI to Green */
    .fi-fo-wizard-header-step.fi-completed .fi-fo-wizard-header-step-icon-ctn {
        background-color: #22c55e !important;
        color: #ffffff !important;
        border-color: #22c55e !important;
    }
    .fi-fo-wizard-header-step.fi-completed .fi-fo-wizard-header-step-label {
        color: #22c55e !important;
    }

    /* Notification Bell Badge to Green */
    .fi-topbar-database-notifications-btn .fi-icon-btn-badge,
    .fi-topbar-database-notifications-btn .fi-badge,
    .fi-topbar-database-notifications-btn span[class*="badge"],
    .fi-icon-btn-badge {
        background-color: #22c55e !important;
        color: #ffffff !important;
    }

    /* Distinct Pagination Active/Current Page Button Styling */
    .fi-ta-pagination button[aria-current="page"],
    .fi-pagination button[aria-current="page"],
    .fi-pagination-item-active,
    button[aria-current="page"] {
        background-color: #22c55e !important; /* Success Green */
        color: #ffffff !important;
        border-color: #22c55e !important;
        font-weight: 800 !important;
        box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2), 0 2px 4px -2px rgba(34, 197, 94, 0.2) !important;
    }

    /* Active/Selected Sidebar Navigation Menu Item styling */
    .fi-sidebar-item-button-active,
    .fi-sidebar-item-button[aria-current="page"],
    .fi-sidebar-item-active,
    .fi-sidebar-item-button[class*="active"],
    a[class*="sidebar-item-button-active"],
    a[class*="sidebar-item-button"][aria-current="page"] {
        background-color: rgba(34, 197, 94, 0.12) !important; /* Elegant light green tint */
        border-inline-start: 4px solid #000000ff !important; /* Vibrant green left border */
        color: #22c55e !important;
        font-weight: 700 !important;
        border-top-left-radius: 0px !important;
        border-bottom-left-radius: 0px !important;
        border-top-right-radius: 6px !important;
        border-bottom-right-radius: 6px !important;
    }
    
    .dark .fi-sidebar-item-button-active,
    .dark .fi-sidebar-item-button[aria-current="page"],
    .dark .fi-sidebar-item-active,
    .dark .fi-sidebar-item-button[class*="active"],
    .dark a[class*="sidebar-item-button-active"],
    .dark a[class*="sidebar-item-button"][aria-current="page"] {
        background-color: rgba(34, 197, 94, 0.2) !important; /* Slightly darker green tint for dark mode */
        border-inline-start: 4px solid #000000ff !important;
        color: #22c55e !important;
    }

    /* Change icons and labels color of active/selected sidebar items */
    .fi-sidebar-item-button-active .fi-sidebar-item-icon,
    .fi-sidebar-item-button-active .fi-sidebar-item-label,
    .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-icon,
    .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label,
    .fi-sidebar-item-button[class*="active"] .fi-sidebar-item-icon,
    .fi-sidebar-item-button[class*="active"] .fi-sidebar-item-label {
        color: #22c55e !important;
        font-weight: 700 !important;
    }
</style>