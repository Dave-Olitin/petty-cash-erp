<style>
    :root, body, .font-sans {
        font-family: Cairo, "Public Sans", sans-serif, -apple-system, blinkmacsystemfont, "Segoe UI", roboto, "Helvetica Neue", arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important;
    }

    /* ── Table Spacing Enhancements ── */
    .fi-ta-content table td.fi-ta-cell {
        padding-top: 0.50rem !important;
        padding-bottom: 0.50rem !important;
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

    /* Active Tab "Palatandaan" (Clear Indicator) */
    .fi-tabs-item[aria-selected="true"] {
        color: #4f46e5 !important; /* Theme Primary Color */
        /* background: transparent !important; */
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
</style>
