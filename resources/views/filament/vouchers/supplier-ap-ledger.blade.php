@php
    $entries = $record->purchaseEntries()->orderByDesc('date')->get();
@endphp

@if($entries->isEmpty())
    <div style="text-align:center; padding:40px; color:#9ca3af; font-size:14px;">
        No Purchase Entries linked to this supplier yet.
    </div>
@else
<div style="overflow-x:auto;">
<table style="width:100%; border-collapse:collapse; font-size:12px;">
    <thead>
        <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Entry No.</th>
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Type</th>
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Bill Date</th>
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Due Date</th>
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Invoice #</th>
            <th style="padding:8px 10px; text-align:left; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">PO #</th>
            <th style="padding:8px 10px; text-align:right; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Bill Amt</th>
            <th style="padding:8px 10px; text-align:right; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Paid</th>
            <th style="padding:8px 10px; text-align:right; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Balance</th>
            <th style="padding:8px 10px; text-align:center; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:.06em; color:#6b7280;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($entries as $entry)
            @php
                $isReturn = $entry->entry_type === 'return';
                $statusColors = match($entry->payment_status) {
                    'paid'    => 'background:#dcfce7; color:#15803d;',
                    'partial' => 'background:#fef9c3; color:#854d0e;',
                    default   => 'background:#fee2e2; color:#b91c1c;',
                };
                $rowBg = $isReturn ? 'background:#fffbeb;' : ($loop->even ? 'background:#f9fafb;' : '');
                $balance = $entry->balance_due ?? ($entry->grand_total - $entry->amount_paid);
                $overdue = $entry->due_date && \Carbon\Carbon::parse($entry->due_date)->isPast() && $entry->payment_status !== 'paid';
            @endphp
            <tr style="border-bottom:1px solid #f3f4f6; {{ $rowBg }}">
                <td style="padding:8px 10px; font-family:monospace; font-weight:600; color:#4f46e5;">
                    {{ $entry->entry_no ?? '—' }}
                </td>
                <td style="padding:8px 10px;">
                    @if($isReturn)
                        <span style="background:#fef9c3; color:#854d0e; border-radius:4px; padding:2px 7px; font-size:10px; font-weight:700;">RETURN</span>
                    @else
                        <span style="background:#ede9fe; color:#5b21b6; border-radius:4px; padding:2px 7px; font-size:10px; font-weight:700;">BILL</span>
                    @endif
                </td>
                <td style="padding:8px 10px; color:#374151;">
                    {{ $entry->date ? \Carbon\Carbon::parse($entry->date)->format('d M Y') : '—' }}
                </td>
                <td style="padding:8px 10px; color:{{ $overdue ? '#dc2626' : '#374151' }}; font-weight:{{ $overdue ? '700' : '400' }};">
                    {{ $entry->due_date ? \Carbon\Carbon::parse($entry->due_date)->format('d M Y') : '—' }}
                    @if($overdue) <span style="font-size:10px;">⚠</span> @endif
                </td>
                <td style="padding:8px 10px; font-family:monospace; font-size:11px; color:#374151;">{{ $entry->supplier_invoice_number ?? '—' }}</td>
                <td style="padding:8px 10px; font-family:monospace; font-size:11px; color:#374151;">{{ $entry->lpo_number ?? '—' }}</td>
                <td style="padding:8px 10px; text-align:right; font-family:monospace; font-weight:600; color:{{ $isReturn ? '#b45309' : '#1e293b' }};">
                    {{ $isReturn ? '- ' : '' }}AED {{ number_format(abs($entry->grand_total ?? $entry->total_amount ?? 0), 2) }}
                </td>
                <td style="padding:8px 10px; text-align:right; font-family:monospace; color:#059669;">
                    AED {{ number_format($entry->amount_paid ?? 0, 2) }}
                </td>
                <td style="padding:8px 10px; text-align:right; font-family:monospace; font-weight:700; color:{{ $balance > 0.01 ? '#dc2626' : '#15803d' }};">
                    AED {{ number_format(abs($balance), 2) }}
                </td>
                <td style="padding:8px 10px; text-align:center;">
                    <span style="{{ $statusColors }} border-radius:4px; padding:2px 8px; font-size:10px; font-weight:700; text-transform:uppercase;">
                        {{ $entry->payment_status ?? 'unpaid' }}
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @php
            $footerBilled = $entries->where('entry_type', '!=', 'return')->sum(fn($e) => $e->grand_total ?? $e->total_amount ?? 0);
            $footerPaid = $entries->sum('amount_paid');
            $footerBalance = $entries->sum(function($e) {
                $bal = $e->balance_due ?? (($e->grand_total ?? $e->total_amount ?? 0) - ($e->amount_paid ?? 0));
                return $e->entry_type === 'return' ? -abs($bal) : $bal;
            });
        @endphp
        <tr style="border-top:2px solid #e5e7eb; background:#f9fafb; font-weight:700;">
            <td colspan="6" style="padding:8px 10px; font-size:11px; text-align:right; color:#374151;">TOTALS:</td>
            <td style="padding:8px 10px; text-align:right; font-family:monospace;">AED {{ number_format($footerBilled, 2) }}</td>
            <td style="padding:8px 10px; text-align:right; font-family:monospace; color:#059669;">AED {{ number_format($footerPaid, 2) }}</td>
            <td style="padding:8px 10px; text-align:right; font-family:monospace; color:{{ $footerBalance > 0.01 ? '#dc2626' : '#15803d' }};">AED {{ number_format($footerBalance, 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
</div>
@endif
