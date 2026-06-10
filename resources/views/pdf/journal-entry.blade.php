<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Journal Entry - {{ $journalEntry->entry_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
            padding: 30px 40px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 2px;
        }
        .company-sub {
            font-size: 9px;
            letter-spacing: 1px;
            color: #333;
            margin-bottom: 20px;
        }
        .title-box {
            border: 1px solid #000;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            padding: 6px;
            margin-bottom: 18px;
            letter-spacing: 3px;
        }
        .ref-table { width: 100%; margin-bottom: 14px; }
        .ref-table td { font-weight: bold; font-size: 11px; }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .ledger-table th, .ledger-table td {
            border: 1px solid #000;
            padding: 5px 7px;
            vertical-align: top;
        }
        .ledger-table th {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            background-color: #f8f8f8;
        }
        .ledger-table td { font-size: 10px; }
        .col-branch { width: 8%; text-align: center; }
        .col-acct   { width: 10%; text-align: center; }
        .col-trn    { width: 10%; text-align: center; }
        .col-inv    { width: 10%; text-align: center; }
        .col-detail { width: 32%; }
        .col-dr     { width: 15%; text-align: right; }
        .col-cr     { width: 15%; text-align: right; }
        
        .total-row th { font-weight: bold; text-align: right; font-size: 11px; padding-top: 8px; padding-bottom: 8px; }
        .total-row .lbl { text-align: right; padding-right: 10px; }

        .sig-table { width: 100%; margin-top: 40px; }
        .sig-table td {
            width: 33%;
            font-size: 10px;
            font-weight: bold;
            vertical-align: bottom;
            padding-right: 15px;
            text-align: center;
        }
        .sig-label { margin-bottom: 30px; }
        .sig-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 9px;
            font-weight: normal;
            color: #444;
            min-height: 14px;
            margin: 0 20px;
        }
    </style>
</head>
<body>

@php
    $totalDR = $journalEntry->total_debit;
    $totalCR = $journalEntry->total_credit;
    $companyName = 'ERICK TRADING CO. LLC';
@endphp

<table class="header-table">
    <tr>
        <td style="width: 100%; text-align: center;">
            <div class="company-name">{{ $companyName }}</div>
            <div class="company-sub">
                JOURNAL VOUCHER
            </div>
        </td>
    </tr>
</table>

<div class="title-box">
    JOURNAL ENTRY
</div>

<table class="ref-table">
    <tr>
        <td>
            <strong>J.V NO:</strong> {{ $journalEntry->entry_no }}
        </td>
        <td style="text-align:right;"><strong>DATE:</strong> {{ $journalEntry->date ? $journalEntry->date->format('d/m/Y') : $journalEntry->created_at->format('d/m/Y') }}</td>
    </tr>
</table>

<table style="width: 100%; margin-bottom: 12px; font-size: 11px; border-collapse: collapse;">
    <tr>
        <td style="width: 1%; white-space: nowrap; font-weight: bold; vertical-align: bottom; padding-bottom: 2px;">REF VOUCHER:&nbsp;</td>
        <td style="border-bottom: 1px solid #000; vertical-align: bottom; padding-bottom: 2px;">{{ $journalEntry->voucher?->voucher_number ?? '—' }}</td>
        
        <td style="width: 1%; white-space: nowrap; font-weight: bold; vertical-align: bottom; padding-bottom: 2px; padding-left: 20px;">PO NO:&nbsp;</td>
        <td style="border-bottom: 1px solid #000; vertical-align: bottom; padding-bottom: 2px;">{{ $journalEntry->po_number ?? '—' }}</td>

    </tr>
</table>

<table class="ledger-table">
    <thead>
        <tr>
            <th class="col-branch">BRANCH</th>
            <th class="col-acct">ACCT</th>
            <th class="col-trn">TRN</th>
            <th class="col-inv">INV #</th>
            <th class="col-detail">DESCRIPTION</th>
            <th class="col-dr">DEBIT (DR)</th>
            <th class="col-cr">CREDIT (CR)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($journalEntry->lines as $line)
        <tr>
            <td class="col-branch">{{ strtoupper($line->branch ?? '—') }}</td>
            <td class="col-acct">{{ $line->accountCode?->code ?? '—' }}</td>
            <td class="col-trn">{{ $line->trn ?? '—' }}</td>
            <td class="col-inv">{{ $line->invoice_no ?? '—' }}</td>
            <td class="col-detail">{{ strtoupper($line->remarks ?? '—') }}<br><small style="color: #555;">{{ $line->supplier_name }}</small></td>
            <td class="col-dr">{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
            <td class="col-cr">{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center; padding: 10px;">No ledger entries</td>
        </tr>
        @endforelse
        @if($journalEntry->lines->count() < 10)
            @for($i = $journalEntry->lines->count(); $i < 10; $i++)
                <tr><td colspan="7" style="height: 22px;">&nbsp;</td></tr>
            @endfor
        @endif
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th colspan="5" class="lbl">TOTAL</th>
            <th class="col-dr">{{ number_format($totalDR, 2) }}</th>
            <th class="col-cr">{{ number_format($totalCR, 2) }}</th>
        </tr>
    </tfoot>
</table>

<table class="sig-table">
    <tr>
        <td>
            <div class="sig-label">PREPARED BY:</div>
            <div class="sig-line"></div>
        </td>
        <td>
            <div class="sig-label">CHECKED BY:</div>
            <div class="sig-line"></div>
        </td>
        <td>
            <div class="sig-label">APPROVED BY:</div>
            <div class="sig-line"></div>
        </td>
</table>

@php
    $childVouchers = $journalEntry->voucher ? $journalEntry->voucher->childVouchers : collect();
    $childVouchers->loadMissing([
        'template',
        'items.category',
        'approvals.user',
        'user',
        'purchaseEntries.lines',
        'purchaseEntries.taxRegistration',
        'denominations'
    ]);
@endphp

@foreach($childVouchers as $childVoucher)
    <div style="page-break-before: always;"></div>
    @include('pdf.voucher-body', ['voucher' => $childVoucher, 'isPreview' => false])
@endforeach

</body>
</html>
