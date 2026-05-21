<style>
    .fi-tabs,
    .voucher-preview-section .fi-section-content {
        padding: 0 !important
    }

    .font-sans,
    .font-mono,
    .font-serif,
    body {
        font-family: Manrope, "Public Sans", sans-serif, -apple-system, blinkmacsystemfont, "Segoe UI", roboto, "Helvetica Neue", arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important
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
        color: #1e293b !important;
        background: #f8fafc !important
    }

    .fi-tabs-item[aria-selected=true] {
        color: #4f46e5 !important;
        background: 0 0 !important;
        box-shadow: inset 0 -3px 0 0 #4f46e5 !important;
        border-bottom: 2px solid #4f46e5 !important
    }

    .fi-tabs-item[aria-selected=true] span {
        color: #4f46e5 !important;
        font-size: 20px !important
    }

    .fi-tabs-item[aria-selected=true] .fi-badge {
        background: #e0e7ff !important;
        color: #4f46e5 !important
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
</style>