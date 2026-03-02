<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $voucher->type === 'petty_cash' ? 'Petty Cash Voucher' : 'Payment Voucher' }}</title>
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
        .field-row { margin-bottom: 8px; font-size: 11px; }
        .field-label { font-weight: bold; display: inline; }
        .field-value {
            display: inline;
            border-bottom: 1px solid #000;
            min-width: 75%;
            font-weight: normal;
            padding-bottom: 1px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .cheque-table { width: 100%; margin: 12px 0; font-size: 10px; }
        .cheque-table td { width: 33%; padding-bottom: 4px; }
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
        }
        .ledger-table td { font-size: 10px; }
        .col-branch { width: 10%; text-align: center; }
        .col-acct   { width: 13%; text-align: center; }
        .col-detail { width: 47%; }
        .col-dr     { width: 15%; text-align: right; }
        .col-cr     { width: 15%; text-align: right; }
        .spacer-row td { height: 110px; }
        .total-row th { font-weight: bold; text-align: right; font-size: 10px; }
        .total-row .lbl { text-align: right; padding-right: 10px; }
        .words-section {
            border: 1px solid #000;
            border-top: none;
            padding: 5px 7px;
            margin-bottom: 30px;
        }
        .words-label { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .words-value { font-size: 11px; padding: 4px 6px; min-height: 22px; }
        .sig-table { width: 100%; margin-top: 20px; }
        .sig-table td {
            width: 25%;
            font-size: 10px;
            font-weight: bold;
            vertical-align: bottom;
            padding-right: 15px;
        }
        .sig-label { margin-bottom: 28px; }
        .sig-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 9px;
            font-weight: normal;
            color: #444;
            min-height: 14px;
        }
    </style>
</head>
<body>

@php
    $template = $voucher->template;

    // Compute totals from items
    $totalDR = $voucher->items->where('entry_type', 'debit')->sum('amount');
    $totalCR = $voucher->items->where('entry_type', 'credit')->sum('amount');
    $displayAmount = $voucher->amount ?? $totalDR;

    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        $whole    = floor($displayAmount);
        $fraction = round(($displayAmount - $whole) * 100);
        $words = strtoupper($f->format($whole)) . ' DIRHAMS';
        $words .= $fraction > 0
            ? ' AND ' . strtoupper($f->format($fraction)) . ' FILS ONLY'
            : ' ONLY';
    } else {
        $words = strtoupper(\Illuminate\Support\Number::spell($displayAmount)) . ' DIRHAMS ONLY';
    }

    $checkerName  = $voucher->approvals->firstWhere('action', 'checked')?->user?->name  ?? '';
    $approverName = $voucher->approvals->whereIn('action', ['approved'])->last()?->user?->name ?? '';
@endphp

<table class="header-table">
    <tr>
        <td style="width: 70%;">
            <div class="company-name">{{ $template?->company_name ?? 'COMPANY NAME' }}</div>
            <div class="company-sub">
                @if($template?->tel_no)Tel. No.: {{ $template->tel_no }}<br>@endif
                @if($template?->address){!! nl2br(e($template->address)) !!}<br>@endif
                @if($template?->trn)TRN: {{ $template->trn }}@endif
            </div>
        </td>
        @if($template?->logo_path)
        <td style="width: 30%; text-align: right;">
            <img src="{{ storage_path('app/public/' . $template->logo_path) }}" alt="" style="max-height: 60px; max-width: 140px;">
        </td>
        @endif
    </tr>
</table>

<div class="title-box">
    {{ $voucher->type === 'petty_cash' ? 'PETTY CASH VOUCHER' : 'PAYMENT VOUCHER' }}
</div>

<table class="ref-table">
    <tr>
        <td><strong>P.V NO:</strong> {{ $voucher->voucher_number }}</td>
        <td style="text-align:right;"><strong>DATE:</strong> {{ $voucher->created_at->format('d/m/Y') }}</td>
    </tr>
</table>

<table style="width: 100%; margin-bottom: 8px; font-size: 11px; border-collapse: collapse;">
    <tr>
        <td style="width: 1%; white-space: nowrap; font-weight: bold; vertical-align: bottom; padding-bottom: 2px;">PAID TO:&nbsp;</td>
        <td style="border-bottom: 1px solid #000; vertical-align: bottom; padding-bottom: 2px;">{{ strtoupper($voucher->payee) }}</td>
    </tr>
    <tr><td colspan="2" style="height: 6px;"></td></tr>
    <tr>
        <td style="width: 1%; white-space: nowrap; font-weight: bold; vertical-align: bottom; padding-bottom: 2px;">BEING:&nbsp;&nbsp;&nbsp;</td>
        <td style="border-bottom: 1px solid #000; vertical-align: bottom; padding-bottom: 2px;">{{ strtoupper($voucher->description) }}</td>
    </tr>
</table>

<table class="cheque-table">
    <tr>
        <td>Cheque No.: {{ $voucher->cheque_no ?: '_____________________' }}</td>
        <td style="text-align:center;">Date: {{ $voucher->cheque_date ? $voucher->cheque_date->format('d/m/Y') : '_____________________' }}</td>
        <td style="text-align:right;">Bank: {{ $voucher->bank ?: '_____________________' }}</td>
    </tr>
</table>

<table class="ledger-table">
    <thead>
        <tr>
            <th class="col-branch" style="width: 8%;">BRANCH</th>
            <th class="col-acct" style="width: 10%;">ACCT<br>CODE</th>
            <th class="col-detail" style="width: 42%;">ACCOUNT DETAILS</th>
            <th class="col-dr" style="width: 20%;">DR</th>
            <th class="col-cr" style="width: 20%;">CR</th>
        </tr>
    </thead>
    <tbody>
        @forelse($voucher->items as $item)
        <tr>
            <td class="col-branch">{{ strtoupper($item->branch_code ?? ($template?->branch_code ?? 'HQ')) }}</td>
            <td class="col-acct">{{ $item->account_code ?? '—' }}</td>
            <td class="col-detail">{{ strtoupper($item->description ?? ($item->category?->name ?? '')) }}</td>
            <td class="col-dr">{{ $item->entry_type === 'debit' ? number_format($item->amount, 2) : '' }}</td>
            <td class="col-cr">{{ $item->entry_type === 'credit' ? number_format($item->amount, 2) : '' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center; padding: 10px;">No ledger entries</td>
        </tr>
        @endforelse
        @if($voucher->items->count() < 5)
            @for($i = $voucher->items->count(); $i < 5; $i++)
                <tr><td colspan="5" style="height: 22px;">&nbsp;</td></tr>
            @endfor
        @endif
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th colspan="3" class="lbl">TOTAL</th>
            <th class="col-dr">{{ number_format($totalDR, 2) }}</th>
            <th class="col-cr">{{ number_format($totalCR, 2) }}</th>
        </tr>
    </tfoot>
</table>

<div class="words-section">
    <div class="words-label">AMOUNT IN WORDS</div>
    <div class="words-value">{{ $words }}</div>
</div>

<table class="sig-table">
    <tr>
        <td>
            <div class="sig-label">PREPARED BY:</div>
            <div class="sig-line">{{ $voucher->user?->name }}</div>
        </td>
        <td>
            <div class="sig-label">ACCOUNTANT:</div>
            <div class="sig-line">{{ $checkerName }}</div>
        </td>
        <td>
            <div class="sig-label">GM:</div>
            <div class="sig-line">{{ $approverName }}</div>
        </td>
        <td>
            <div class="sig-label">CEO:</div>
            <div class="sig-line"></div>
        </td>
    </tr>
    <tr>
        <td colspan="4" style="padding-top: 30px;">
            <div class="sig-label">RECEIVED BY:</div>
            <div class="sig-line" style="width: 100px;"></div>
        </td>
    </tr>
</table>

@if($voucher->transaction_summary)
<div style="margin-top: 30px; border-top: 1px dashed #000; padding-top: 10px; font-size: 10px;">
    <strong>TRANSACTION SUMMARY:</strong><br>
    {!! nl2br(e($voucher->transaction_summary)) !!}
</div>
@endif

</body>
</html>
