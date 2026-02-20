<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petty Cash Voucher #{{ $transaction->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #f5f7fa;
        }

        .page {
            width: 210mm;
            min-height: 148mm;
            margin: 10mm auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 20px 28px 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header-left h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
        .header-left p  { font-size: 11px; opacity: 0.8; margin-top: 2px; }
        .header-right   { text-align: right; }
        .voucher-number { font-size: 22px; font-weight: 800; }
        .voucher-label  { font-size: 10px; opacity: 0.75; text-transform: uppercase; letter-spacing: 1px; }

        /* ── Status badge ── */
        .status-bar {
            padding: 6px 28px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-bar.approved  { background: #d1fae5; color: #065f46; }
        .status-bar.pending   { background: #fef3c7; color: #92400e; }
        .status-bar.rejected  { background: #fee2e2; color: #991b1b; }

        .type-badge {
            margin-left: auto;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .type-expense       { background: #fee2e2; color: #991b1b; }
        .type-replenishment { background: #d1fae5; color: #065f46; }

        /* ── Body ── */
        .body { padding: 20px 28px; }

        /* Amount Hero */
        .amount-hero {
            text-align: center;
            margin-bottom: 20px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .amount-hero .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .amount-hero .value { font-size: 32px; font-weight: 800; color: #1e3a8a; }
        .amount-hero .vat   { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
            margin-bottom: 18px;
        }
        .detail-item { }
        .detail-item .key   { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .detail-item .value { font-size: 13px; color: #1e293b; font-weight: 500; word-break: break-word; }
        .detail-item.full   { grid-column: 1 / -1; }

        /* Items Table */
        .items-section { margin-top: 16px; }
        .items-section h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f1f5f9; }
        th { padding: 7px 10px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        td { padding: 7px 10px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }

        /* Rejection Reason */
        .rejection-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 14px;
        }
        .rejection-box .label { font-size: 10px; font-weight: 700; color: #dc2626; text-transform: uppercase; margin-bottom: 3px; }
        .rejection-box .reason { font-size: 12px; color: #7f1d1d; }

        /* Footer */
        .footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sig-block { text-align: center; }
        .sig-line { border-top: 1px solid #cbd5e1; width: 130px; margin: 28px auto 4px; }
        .sig-label { font-size: 10px; color: #94a3b8; }

        .print-meta { font-size: 9px; color: #cbd5e1; text-align: right; }

        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; width: 100%; border-radius: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

{{-- Print Button (hidden on print) --}}
<div class="no-print" style="text-align:center; padding: 12px; background: #f1f5f9;">
    <button onclick="window.print()"
        style="background:#1e3a8a;color:white;border:none;padding:8px 24px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">
        🖨️ Print / Save PDF
    </button>
    <button onclick="window.history.back()"
        style="background:#e2e8f0;color:#475569;border:none;padding:8px 20px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;margin-left:8px;">
        ← Back
    </button>
</div>

<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <h1>Petty Cash Voucher</h1>
            <p>{{ $transaction->branch->name ?? 'Head Office' }}</p>
        </div>
        <div class="header-right">
            <div class="voucher-label">Voucher No.</div>
            <div class="voucher-number">#{{ str_pad($transaction->id, 5, '0', STR_PAD_LEFT) }}</div>
        </div>
    </div>

    {{-- Status Bar --}}
    <div class="status-bar {{ $transaction->status }}">
        @php
            $statusIcon = match($transaction->status) {
                'approved' => '✅', 'rejected' => '❌', default => '⏳'
            };
        @endphp
        {{ $statusIcon }} Status: {{ ucfirst($transaction->status) }}
        <span class="type-badge {{ $transaction->type === 'EXPENSE' ? 'type-expense' : 'type-replenishment' }}">
            {{ $transaction->type }}
        </span>
    </div>

    {{-- Body --}}
    <div class="body">

        {{-- Amount Hero --}}
        <div class="amount-hero">
            <div class="label">Total Amount</div>
            <div class="value">AED {{ number_format($transaction->amount, 2) }}</div>
            @if($transaction->vat > 0)
            <div class="vat">incl. VAT: AED {{ number_format($transaction->vat, 2) }}</div>
            @endif
        </div>

        {{-- Details Grid --}}
        <div class="details-grid">
            <div class="detail-item">
                <div class="key">Date &amp; Time</div>
                <div class="value">{{ $transaction->created_at->format('d M Y, h:i A') }}</div>
            </div>
            <div class="detail-item">
                <div class="key">Created By</div>
                <div class="value">{{ $transaction->user->name ?? '—' }}</div>
            </div>

            @if($transaction->payee)
            <div class="detail-item">
                <div class="key">Paid To</div>
                <div class="value">{{ $transaction->payee }}</div>
            </div>
            @endif

            @if($transaction->supplier)
            <div class="detail-item">
                <div class="key">Supplier</div>
                <div class="value">{{ $transaction->supplier }}</div>
            </div>
            @endif

            @if($transaction->trn)
            <div class="detail-item">
                <div class="key">TRN</div>
                <div class="value">{{ $transaction->trn }}</div>
            </div>
            @endif

            @if($transaction->reference_number)
            <div class="detail-item">
                <div class="key">Invoice / Reference #</div>
                <div class="value">{{ $transaction->reference_number }}</div>
            </div>
            @endif

            @if($transaction->description)
            <div class="detail-item full">
                <div class="key">Description</div>
                <div class="value">{{ $transaction->description }}</div>
            </div>
            @endif
        </div>

        {{-- Items Table --}}
        @if($transaction->items->isNotEmpty())
        <div class="items-section">
            <h3>Line Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category?->name ?? '—' }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->vat, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($item->total_price, 2) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Rejection Reason --}}
        @if($transaction->status === 'rejected' && $transaction->rejection_reason)
        <div class="rejection-box">
            <div class="label">Rejection Reason</div>
            <div class="reason">{{ $transaction->rejection_reason }}</div>
        </div>
        @endif

        {{-- Accounting Remarks --}}
        @if($transaction->accounting_remarks)
        <div style="margin-top:12px; padding: 10px 14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px;">
            <div style="font-size:10px;font-weight:700;color:#0369a1;text-transform:uppercase;margin-bottom:3px;">Accounting Remarks</div>
            <div style="font-size:12px;color:#0c4a6e;">{{ $transaction->accounting_remarks }}</div>
        </div>
        @endif

        {{-- Signature Footer --}}
        <div class="footer">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Prepared By</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Approved By</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Received By</div>
            </div>
            <div class="print-meta">
                Printed: {{ now()->format('d M Y H:i') }}<br>
                {{ config('app.name') }}
            </div>
        </div>

    </div>{{-- /body --}}
</div>{{-- /page --}}

</body>
</html>
