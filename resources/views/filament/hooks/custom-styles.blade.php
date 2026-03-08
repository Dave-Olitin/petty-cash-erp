<style>
    :root, body, .font-sans {
        font-family: Cairo, "Public Sans", sans-serif, -apple-system, blinkmacsystemfont, "Segoe UI", roboto, "Helvetica Neue", arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important;
    }
    .voucher-preview-section .fi-section-content {
        padding: 0 !important;
    }
    .voucher-preview-section > .fi-section-header {
        border-bottom: none !important;
    }

    /* Custom Sidebar Styles */
    .fi-sidebar-nav {
        border-inline-end: 1px solid #00000014;
        box-shadow: 4px 0 12px -2px rgba(0, 0, 0, 0.08);
    }

    /* Dark mode support */
    .dark .fi-sidebar-nav {
        border-inline-end: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 4px 0 12px -2px rgba(0, 0, 0, 0.4);
    }
    
    /* ── Table Spacing Enhancements ── */
    .fi-ta-content table td.fi-ta-cell {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        border-bottom: 1px solid #f1f5f9;
    }

    /* ── Tabs Layout Redesign (Left-aligned above table) ── */
    
    /* Override Filament's default tab container wrapping and centering */
    .fi-ta-header {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    
    /* The actual wrapper that holds the tabs */
    .fi-tabs {
        display: flex !important;
        justify-content: flex-start !important; /* Force Left Alignment */
        width: 100% !important;
        max-width: 100% !important;
        gap: 0.5rem !important;
        /* border-bottom: 2px solid #e2e8f0 !important; */
        background: transparent !important;
        padding: 0 !important;
        margin-bottom: 0 !important; /* Sit flush above table */
        border-radius: 0 !important;
        box-shadow: none !important;
        ring: 0 !important;
    }

    /* Individual Tab Styling - Clean Text */
    .fi-tabs-item {
        background: transparent !important;
        border-radius: 0 !important;
        padding: 0.75rem 1rem !important;
        margin: 0 !important;
        color: #aaadb1ff !important;
        font-weight: 600 !important;
        box-shadow: none !important;
        position: relative;
        transition: all 0.2s ease;
        font-size: 16px !important;
    }

    /* Hover effect */
    .fi-tabs-item:hover {
        color: #1e293b !important;
        background: #f8fafc !important;
    }

    /* Active Tab (Clear Indicator) */
    .fi-tabs-item[aria-selected="true"] {
        color: #4f46e5 !important; /* Theme Primary Color */
        background: transparent !important;
        /* Thick, distinct bottom border marker */
        box-shadow: inset 0 -3px 0 0 #4f46e5 !important;
        border-bottom: 2px solid #4f46e5 !important;
    }

    /* Ensure text inside active tab is also primary color */
    .fi-tabs-item[aria-selected="true"] span {
        color: #4f46e5 !important;
        font-size: 20px !important;
    }

    /* Keep badges styled cleanly inside tabs */
    .fi-tabs-item[aria-selected="true"] .fi-badge {
        background: #e0e7ff !important;
        color: #4f46e5 !important;
    }

    /* ── Wide Screen Maximum Width ── */
    .fi-main {
        max-width: 1600px !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }

    /* ── Mobile Wizard Horizontal Icons-Only Layout ── */
    @media (max-width: 768px) {
        .fi-wi-header {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            overflow-x: auto !important;
            gap: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        .fi-wi-header > li {
            margin: 0 !important;
            border: none !important;
            padding-bottom: 0 !important;
            flex: 1 1 0;
            display: flex;
            justify-content: center;
        }
        /* Target the button inside the li */
        .fi-wi-header > li > button {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 0.25rem !important;
            text-align: center !important;
            padding: 0 !important;
        }
        .fi-wi-step-title {
            display: block !important;
            font-size: 0.75rem !important; /* Smaller font for mobile */
            white-space: normal !important;
            line-height: 1.1 !important;
        }
        .fi-wi-step-description {
            display: none !important; /* Hide description */
        }
        /* Optional: Hide the separator line between steps on mobile if it exists */
        .fi-wi-header > li > div[role="separator"],
        .fi-wi-header > li::after {
            display: none !important;
        }
    }

    /* ── Mobile Dashboard Stats 2-Column Grid ──
    @media (max-width: 640px) {
        .fi-wi-stats-overview-stats-ctn {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    } */
</style>
