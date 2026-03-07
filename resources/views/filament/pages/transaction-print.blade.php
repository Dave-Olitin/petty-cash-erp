<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher #{{ $transaction->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        @page { size: A4 portrait; margin: 15mm; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #111827;
            background: #f3f4f6;
            line-height: 1.5;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            background: #fff;
            padding: 20mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Typography */
        h1, h2, h3 { color: #111827; font-weight: 600; margin-bottom: 0.5rem; }
        .text-gray { color: #6b7280; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .uppercase { text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8em; }

        /* Header Segment */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header-title { font-size: 24pt; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .branch-name { font-size: 13pt; color: #4b5563; }
        
        .voucher-meta { text-align: right; }
        .voucher-number { font-size: 18pt; font-weight: 700; color: #111827; }
        
        /* Status Badges - Clean Bordered Style */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border: 1px solid currentColor;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .badge.approved { color: #059669; }
        .badge.pending { color: #d97706; }
        .badge.rejected { color: #dc2626; }
        .badge.expense { color: #4f46e5; }
        .badge.replenish { color: #0891b2; }

        /* Details Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .info-box {
            /* Clean line separations */
        }
        
        .info-label {
            font-size: 8pt;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }
        .info-value { font-size: 12pt; font-weight: 500; }

        /* Amount Highlight */
        .amount-highlight {
            grid-column: 1 / -1;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 20px;
            text-align: right;
            border-radius: 4px;
        }
        .total-amount { font-size: 24pt; font-weight: 700; color: #111827; }

        /* Minimal Table */
        .table-container { margin-bottom: 40px; }
        .table-title { font-size: 10pt; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        
        th {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #111827;
            font-weight: 600;
            color: #111827;
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        
        tr:last-child td { border-bottom: 2px solid #e5e7eb; }

        /* Remarks/Rejection Sections */
        .note-box {
            border-left: 3px solid #111827;
            padding: 10px 15px;
            background: #f9fafb;
            margin-bottom: 20px;
        }
        .note-box.danger { border-left-color: #dc2626; background: #fef2f2; }
        .note-label { font-size: 9pt; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        
        /* Signatures */
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 60px;
            page-break-inside: avoid;
        }
        
        .sig-box { text-align: center; }
        .sig-line { border-top: 1px solid #111827; padding-top: 8px; margin-top: 40px; font-size: 9pt; font-weight: 600; color: #4b5563; }

        /* Print formatting */
        .no-print {
            text-align: center;
            padding: 15px;
            background: #e5e7eb;
            margin-bottom: 20px;
        }
        
        .btn {
            background: #111827; color: white; border: none; padding: 10px 20px; border-radius: 4px;
            font-size: 11pt; cursor: pointer; font-weight: 600; margin: 0 5px;
        }
        .btn.outline { background: white; color: #111827; border: 1px solid #111827; }

        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .page { box-shadow: none; margin: 0; padding: 0; min-height: auto; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn" onclick="window.print()">🖨️ Print / Save PDF</button>
    <button class="btn outline" onclick="window.history.back()">← Back</button>
</div>

<div class="page">
    <!-- Header -->
    <div class="header">
        <div>
            <div class="header-title">Petty Cash Voucher</div>
            <div class="branch-name">{{ $transaction->branch->name ?? 'Head Office' }}</div>
        </div>
        <div class="voucher-meta">
            <div class="uppercase text-gray">Voucher No.</div>
            <div class="voucher-number">#{{ str_pad($transaction->id, 5, '0', STR_PAD_LEFT) }}</div>
            <div style="margin-top: 8px;">
                <span class="badge {{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span>
                <span class="badge {{ $transaction->type === 'EXPENSE' ? 'expense' : 'replenish' }}">{{ $transaction->type }}</span>
            </div>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">Date & Time</div>
            <div class="info-value">{{ $transaction->created_at->format('d M Y, h:i A') }}</div>
        </div>
        
        <div class="info-box">
            <div class="info-label">Created By</div>
            <div class="info-value">{{ $transaction->user->name ?? '—' }}</div>
        </div>

        @if($transaction->payee)
        <div class="info-box">
            <div class="info-label">Paid To</div>
            <div class="info-value">{{ $transaction->payee }}</div>
        </div>
        @endif

        @if($transaction->supplier)
        <div class="info-box">
            <div class="info-label">Supplier / Vendor</div>
            <div class="info-value">{{ $transaction->supplier }}</div>
        </div>
        @endif

        @if($transaction->trn)
        <div class="info-box">
            <div class="info-label">TRN</div>
            <div class="info-value">{{ $transaction->trn }}</div>
        </div>
        @endif

        @if($transaction->reference_number)
        <div class="info-box">
            <div class="info-label">Invoice / Ref #</div>
            <div class="info-value">{{ $transaction->reference_number }}</div>
        </div>
        @endif

        @if($transaction->description)
        <div class="info-box" style="grid-column: 1 / -1;">
            <div class="info-label">Description / Purpose</div>
            <div class="info-value">{{ $transaction->description }}</div>
        </div>
        @endif

        <div class="amount-highlight">
            <div class="info-label">Total Amount</div>
            <div class="total-amount">AED {{ number_format($transaction->amount, 2) }}</div>
            @if($transaction->vat > 0)
            <div class="text-gray" style="font-size: 9pt; margin-top: 4px;">Including VAT: AED {{ number_format($transaction->vat, 2) }}</div>
            @endif
        </div>
    </div>

    <!-- Items Table -->
    @if($transaction->items->isNotEmpty())
    <div class="table-container">
        <div class="table-title">Line Items</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Description</th>
                    <th style="width: 20%;">Category</th>
                    <th class="text-right" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 15%;">Unit Price</th>
                    <th class="text-right" style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->name }}
                        @if($item->vat > 0)
                        <div style="font-size: 8pt; color: #6b7280; margin-top: 2px;">+ VAT: {{ number_format($item->vat, 2) }}</div>
                        @endif
                    </td>
                    <td>{{ $item->category?->name ?? '—' }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($item->total_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Additional Notes -->
    @if($transaction->status === 'rejected' && $transaction->rejection_reason)
    <div class="note-box danger">
        <div class="note-label" style="color: #dc2626;">Rejection Reason</div>
        <div>{{ $transaction->rejection_reason }}</div>
    </div>
    @endif

    @if($transaction->accounting_remarks)
    <div class="note-box">
        <div class="note-label">Accounting Remarks</div>
        <div>{{ $transaction->accounting_remarks }}</div>
    </div>
    @endif

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Approved By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Received By</div>
        </div>
    </div>

    <div style="margin-top: 40px; text-align: right; font-size: 8pt; color: #9ca3af;">
        Generated: {{ now()->format('d M Y H:i:s') }}
    </div>

</div>

</body>
</html>
