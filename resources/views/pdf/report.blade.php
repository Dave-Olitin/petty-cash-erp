<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vouchers Report: {{ $date_from }} to {{ $date_to }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            padding: 30px;
        }
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .header-table td { vertical-align: bottom; }
        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .report-title { font-size: 16px; font-weight: bold; color: #555; }
        .report-date { font-size: 12px; color: #777; margin-top: 4px; }
        
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .summary-table td { 
            border: 1px solid #ddd; 
            padding: 10px; 
            text-align: center; 
            background: #f9f9f9; 
            width: 20%;
        }
        .summary-label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px; }
        .summary-val { font-size: 14px; font-weight: bold; color: #000; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 6px 8px; font-size: 10px; }
        .data-table th { background: #eee; font-weight: bold; text-align: left; text-transform: uppercase; font-size: 9px; }
        .data-table th.right, .data-table td.right { text-align: right; }
        
        .type-badge { padding: 2px 4px; font-size: 8px; border-radius: 2px; text-transform: uppercase; font-weight: bold; color: #fff; }
        .bg-pc { background-color: #0ea5e9; }
        .bg-pay { background-color: #eab308; }
        .bg-rec { background-color: #22c55e; }
        
        .row-alt { background-color: #fcfcfc; }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 70%;">
            <div class="company-name">{{ $pdf_template?->company_name ?? 'COMPANY NAME' }}</div>
            <div class="report-title">VOUCHERS REPORT OVERVIEW</div>
            <div class="report-date">Period: <strong>{{ \Carbon\Carbon::parse($date_from)->format('d/m/Y') }}</strong> to <strong>{{ \Carbon\Carbon::parse($date_to)->format('d/m/Y') }}</strong></div>
        </td>
        @if($pdf_template?->logo_path)
        <td style="width: 30%; text-align: right;">
            <img src="{{ storage_path('app/public/' . $pdf_template->logo_path) }}" alt="Logo" style="max-height: 50px; max-width: 150px;">
        </td>
        @endif
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td>
            <span class="summary-label">Vouchers Count</span>
            <span class="summary-val">{{ $total_count }}</span>
        </td>
        <td>
            <span class="summary-label">Petty Cash (Exp)</span>
            <span class="summary-val">AED {{ number_format($total_petty_cash, 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Payments (Exp)</span>
            <span class="summary-val">AED {{ number_format($total_payment, 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Receipts (Inc)</span>
            <span class="summary-val" style="color: #22c55e;">AED {{ number_format($total_receipt, 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Net Expense</span>
            <span class="summary-val">AED {{ number_format($net_expenditure, 2) }}</span>
        </td>
    </tr>
</table>

<table class="data-table">
    <thead>
        <tr>
            <th style="width: 10%;">Date</th>
            <th style="width: 12%;">Voucher #</th>
            <th style="width: 10%;">Type</th>
            <th style="width: 25%;">Payee</th>
            <th style="width: 12%;">Account Code</th>
            <th style="width: 16%;">Prepared By</th>
            <th class="right" style="width: 15%;">Amount (AED)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($vouchers_all as $index => $v)
            <tr class="{{ $index % 2 == 1 ? 'row-alt' : '' }}">
                <td>{{ $v->created_at->format('d/m/Y') }}</td>
                <td><strong>{{ $v->voucher_number }}</strong></td>
                <td>
                    @if($v->type === 'petty_cash')
                        <span class="type-badge bg-pc">Petty Cash</span>
                    @elseif($v->type === 'receipt')
                        <span class="type-badge bg-rec">Receipt</span>
                    @else
                        <span class="type-badge bg-pay">Payment</span>
                    @endif
                </td>
                <td>{{ substr($v->payee, 0, 40) }}</td>
                <td>{{ $v->items->first()?->account_code ?? '--' }}</td>
                <td>{{ substr($v->user?->name ?? '--', 0, 20) }}</td>
                <td class="right font-bold">{{ number_format($v->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">No records found for the selected period.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr style="background: #eef2f6;">
            <td colspan="6" class="right" style="font-weight: bold; text-transform: uppercase;">Total Listed Amount:</td>
            <td class="right" style="font-weight: bold; font-size: 11px;">{{ number_format($vouchers_all->sum('amount'), 2) }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
