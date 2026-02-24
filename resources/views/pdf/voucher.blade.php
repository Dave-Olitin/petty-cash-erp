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
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 75%;
            font-weight: normal;
            padding-bottom: 1px;
        }
        .cheque-table { width: 100%; margin: 12px 0; font-size: 10px; }
        .cheque-table td { width: 33%; padding-bottom: 4px; }
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
    $branchCode = $voucher->user?->branch ? strtoupper(substr($voucher->user->branch->name, 0, 3)) : 'HQ';
    $acctCodeDR = $voucher->category?->accounting_code ?? '—';
    $acctCodeCR = '1010-02';

    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        $whole    = floor($voucher->amount);
        $fraction = round(($voucher->amount - $whole) * 100);
        $words = strtoupper($f->format($whole)) . ' DIRHAMS';
        $words .= $fraction > 0
            ? ' AND ' . strtoupper($f->format($fraction)) . ' FILS ONLY'
            : ' ONLY';
    } else {
        $words = strtoupper(\Illuminate\Support\Number::spell($voucher->amount)) . ' DIRHAMS ONLY';
    }

    $checkerName  = $voucher->approvals->firstWhere('action', 'checked')?->user?->name  ?? '';
    $approverName = $voucher->approvals->whereIn('action', ['approved'])->last()?->user?->name ?? '';
@endphp

<div class="company-name">ERICK TR CO</div>
<div class="company-sub">
    TEL. NO. 06-525-2030<br>
    TIGER 2 BLDG AL TAAWUN ST. SHARJAH, UAE
</div>

<div class="title-box">
    {{ $voucher->type === 'petty_cash' ? 'PETTY CASH VOUCHER' : 'PAYMENT VOUCHER' }}
</div>

<table class="ref-table">
    <tr>
        <td><strong>P.V NO:</strong> {{ $voucher->voucher_number }}</td>
        <td style="text-align:right;"><strong>DATE:</strong> {{ $voucher->created_at->format('d/m/Y') }}</td>
    </tr>
</table>

<div class="field-row">
    <span class="field-label">PAID TO:&nbsp;</span>
    <span class="field-value">{{ strtoupper($voucher->payee) }}</span>
</div>
<div class="field-row">
    <span class="field-label">BEING:&nbsp;&nbsp;&nbsp;</span>
    <span class="field-value">{{ strtoupper($voucher->description) }}</span>
</div>
<div class="field-row">
    <span class="field-value" style="width:100%;">&nbsp;</span>
</div>

<table class="cheque-table">
    <tr>
        <td>Cheque No.: _____________________</td>
        <td style="text-align:center;">Date: _____________________</td>
        <td style="text-align:right;">Bank: _____________________</td>
    </tr>
</table>

<table class="ledger-table">
    <thead>
        <tr>
            <th class="col-branch">BRANCH</th>
            <th class="col-acct">ACCT<br>CODE</th>
            <th class="col-detail">ACCOUNT DETAILS</th>
            <th class="col-dr">DR</th>
            <th class="col-cr">CR</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-branch">{{ $branchCode }}</td>
            <td class="col-acct">{{ $acctCodeDR }}</td>
            <td class="col-detail">{{ strtoupper($voucher->category?->name ?? 'GENERAL EXPENSE') }} - {{ strtoupper($voucher->payee) }}</td>
            <td class="col-dr">{{ number_format($voucher->amount, 2) }}</td>
            <td class="col-cr"></td>
        </tr>
        <tr>
            <td class="col-branch">{{ $branchCode }}</td>
            <td class="col-acct">{{ $acctCodeCR }}</td>
            <td class="col-detail">CASH IN HAND/BANK</td>
            <td class="col-dr"></td>
            <td class="col-cr">{{ number_format($voucher->amount, 2) }}</td>
        </tr>
        <tr class="spacer-row"><td colspan="5"></td></tr>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th colspan="3" class="lbl">TOTAL</th>
            <th class="col-dr">{{ number_format($voucher->amount, 2) }}</th>
            <th class="col-cr">{{ number_format($voucher->amount, 2) }}</th>
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
            <div class="sig-label">RECEIVED BY:</div>
            <div class="sig-line"></div>
        </td>
    </tr>
</table>

</body>
</html>
