@if ($activeTab === 'audit_logs')
    <style>
        /* When Audit Logs is active, hide the footer breakdown widget completely */
        .fi-page-footer-widgets,
        .fi-footer-widgets {
            display: none !important;
        }
    </style>
@else
    <style>
        /* When Cash Breakdown is active, hide the main page table container card completely */
        .fi-ta-ctn:not(.fi-page-footer-widgets *) {
            display: none !important;
        }
    </style>
@endif
