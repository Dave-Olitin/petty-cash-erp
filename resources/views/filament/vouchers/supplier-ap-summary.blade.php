@php
    $supplier = $record;
    $entries = $supplier->purchaseEntries;

    $totalBilled  = $entries->where('entry_type', 'bill')->sum('grand_total');
    $totalReturns = abs($entries->where('entry_type', 'return')->sum('grand_total'));
    $totalPaid    = $entries->sum('amount_paid');
    $netBalance   = $entries->sum('balance_due');

    $openCount    = $entries->whereIn('payment_status', ['unpaid', 'partial'])->count();
    $totalBills   = $entries->count();

    $balanceColor = $netBalance > 0.01
        ? 'background:#fef2f2; border-color:#fca5a5; color:#b91c1c;'
        : 'background:#f0fdf4; border-color:#86efac; color:#15803d;';
@endphp

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:4px;">

    {{-- Total Bills --}}
    <div style="border-radius:10px; border-left:4px solid #6366f1; background:#eef2ff; padding:14px 16px;">
        <p style="font-size:10px; font-weight:700; letter-spacing:.08em; color:#6366f1; text-transform:uppercase; margin:0 0 4px;">Total Bills</p>
        <p style="font-size:20px; font-family:monospace; font-weight:700; margin:0; color:#312e81;">AED {{ number_format($totalBilled, 2) }}</p>
        <p style="font-size:11px; color:#6366f1; margin:4px 0 0;">{{ $totalBills }} entr{{ $totalBills === 1 ? 'y' : 'ies' }}</p>
    </div>

    {{-- Purchase Returns --}}
    <div style="border-radius:10px; border-left:4px solid #f59e0b; background:#fffbeb; padding:14px 16px;">
        <p style="font-size:10px; font-weight:700; letter-spacing:.08em; color:#b45309; text-transform:uppercase; margin:0 0 4px;">Returns</p>
        <p style="font-size:20px; font-family:monospace; font-weight:700; margin:0; color:#78350f;">- AED {{ number_format($totalReturns, 2) }}</p>
        <p style="font-size:11px; color:#b45309; margin:4px 0 0;">Credit notes applied</p>
    </div>

    {{-- Amount Paid --}}
    <div style="border-radius:10px; border-left:4px solid #10b981; background:#ecfdf5; padding:14px 16px;">
        <p style="font-size:10px; font-weight:700; letter-spacing:.08em; color:#059669; text-transform:uppercase; margin:0 0 4px;">Paid to Date</p>
        <p style="font-size:20px; font-family:monospace; font-weight:700; margin:0; color:#065f46;">AED {{ number_format($totalPaid, 2) }}</p>
        <p style="font-size:11px; color:#059669; margin:4px 0 0;">Across all entries</p>
    </div>

    {{-- Net Balance Due --}}
    <div style="border-radius:10px; border-left:4px solid #ef4444; padding:14px 16px; {{ $balanceColor }}">
        <p style="font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; margin:0 0 4px;">Balance Due</p>
        <p style="font-size:22px; font-family:monospace; font-weight:800; margin:0;">AED {{ number_format($netBalance, 2) }}</p>
        <p style="font-size:11px; margin:4px 0 0;">{{ $openCount }} open invoice{{ $openCount === 1 ? '' : 's' }}</p>
    </div>

</div>
