<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction #{{ $transaction->id }}</title>
    <style>
        body { font-family: sans-serif; line-height: 1.4; color: #333; padding: 1rem; }
        .header { text-align: center; margin-bottom: 1rem; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        .header h1 { margin: 0; color: #4338ca; font-size: 1.5rem; }
        .header p { margin: 0.25rem 0 0; color: #666; font-size: 0.875rem; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .box { background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #eee; }
        .box h3 { margin-top: 0; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; margin-bottom: 0.5rem; }
        
        .row { display: flex; justify-content: space-between; margin-bottom: 0.25rem; border-bottom: 1px dashed #eee; padding-bottom: 0.125rem; font-size: 0.875rem; }
        .label { font-weight: 500; color: #374151; }
        .value { color: #111827; }

        .amount-box { text-align: center; margin: 1rem 0; padding: 1rem; background: #eff6ff; border-radius: 8px; border: 1px solid #dbeafe; }
        .amount-label { font-size: 0.75rem; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em; }
        .amount-value { font-size: 2rem; font-weight: bold; color: #1e3a8a; margin: 0.25rem 0; }
        
        .footer { margin-top: 2rem; text-align: center; font-size: 0.75rem; color: #9ca3af; border-top: 1px solid #eee; padding-top: 0.5rem; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .box, .amount-box { border: 1px solid #ccc; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>Erick Trading Co.</h1>
        <p>Petty Cash Voucher • #{{ $transaction->id }} • {{ $transaction->created_at->format('F j, Y h:i A') }}</p>
    </div>

    <div class="amount-box">
        <div class="amount-label">{{ $transaction->type === 'EXPENSE' ? 'Paid Amount' : 'Received Amount' }}</div>
        <div class="amount-value">AED {{ number_format($transaction->amount, 2) }}</div>
        <div style="color: #666; font-size: 0.875rem;">{{ $transaction->type }}</div>
    </div>

    <div class="grid">
        <div class="box">
            <h3>Details</h3>
            <div class="row"><span class="label">Payee</span> <span class="value">{{ $transaction->payee ?? 'N/A' }}</span></div>
            {{-- Category removed from header as it is per-item --}}
            <div class="row"><span class="label">Branch</span> <span class="value">{{ $transaction->branch->name ?? 'N/A' }}</span></div>
            <div class="row"><span class="label">Status</span> <span class="value" style="text-transform: capitalize;">{{ $transaction->status }}</span></div>
            @if($transaction->status === 'rejected')
                <div class="row" style="color: red;"><span class="label">Rejection Reason</span> <span class="value">{{ $transaction->rejection_reason }}</span></div>
            @endif
        </div>
        
        <div class="box">
            <h3>Description</h3>
            <p style="margin:0; font-size: 0.875rem;">{{ $transaction->description }}</p>
        </div>
    </div>

    @if($transaction->items->count() > 0)
    <div class="box" style="margin-bottom: 1rem;">
        <h3>Line Items</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem;">
            <thead>
                <tr style="border-bottom: 2px solid #eee; text-align: left;">
                    <th style="padding: 0.25rem;">Item Description</th>
                    <th style="padding: 0.25rem;">Category</th>
                    <th style="padding: 0.25rem; text-align: center;">Qty</th>
                    <th style="padding: 0.25rem; text-align: right;">Unit Price</th>
                    <th style="padding: 0.25rem; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.25rem;">{{ $item->name }}</td>
                    <td style="padding: 0.25rem;">{{ $item->category->name ?? '-' }}</td>
                    <td style="padding: 0.25rem; text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: 0.25rem; text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="padding: 0.25rem; text-align: right;">{{ number_format($item->total_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if($transaction->vat > 0 || $transaction->items->sum('vat') > 0)
                <tr>
                    <td colspan="4" style="padding: 0.25rem; text-align: right; font-weight: bold;">Subtotal:</td>
                    <td style="padding: 0.25rem; text-align: right;">{{ number_format($transaction->items->sum('total_price') - $transaction->items->sum('vat'), 2) }}</td>
                </tr>
                 <tr>
                    <td colspan="4" style="padding: 0.25rem; text-align: right; font-weight: bold;">Total Item VAT:</td>
                    <td style="padding: 0.25rem; text-align: right;">{{ number_format($transaction->items->sum('vat'), 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="padding: 0.25rem; text-align: right; font-weight: bold;">VAT:</td>
                    <td style="padding: 0.25rem; text-align: right;">{{ number_format($transaction->vat, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td colspan="4" style="padding: 0.25rem; text-align: right; font-weight: bold;">Grand Total:</td>
                    <td style="padding: 0.25rem; text-align: right; font-weight: bold;">{{ number_format($transaction->amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
    
    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 150px;">
            <div style="border-bottom: 1px solid #000; height: 40px;"></div>
            <p style="font-size: 0.75rem; margin-top: 0.25rem;">Approved By</p>
        </div>
        <div style="text-align: center; width: 150px;">
            <div style="border-bottom: 1px solid #000; height: 40px;"></div>
            <p style="font-size: 0.75rem; margin-top: 0.25rem;">Received By</p>
        </div>
    </div>

    <div class="footer">
        Generated by Petty Cash ERP on {{ now()->format('Y-m-d H:i:s') }}
        <br>
        This is a computer-generated document.
    </div>

</body>
</html>
