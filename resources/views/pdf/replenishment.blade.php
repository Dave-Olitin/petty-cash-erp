<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Float Replenishment - {{ $replenishment->reference }}</title>
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

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .details-table th, .details-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
        }
        .details-table th {
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            background-color: #f7f7f7;
            width: 30%;
        }
        .details-table td { font-size: 11px; width: 70%; }
        
        .words-section {
            border: 1px solid #000;
            padding: 5px 7px;
            margin-bottom: 30px;
            margin-top: 15px;
        }
        .words-label { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .words-value { font-size: 11px; padding: 4px 6px; min-height: 22px; font-weight: bold; }
        .sig-table { width: 100%; margin-top: 40px; }
        .sig-table td {
            width: 50%;
            font-size: 10px;
            font-weight: bold;
            vertical-align: bottom;
            padding-right: 40px;
        }
        .sig-label { margin-bottom: 28px; }
        .sig-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 10px;
            font-weight: normal;
            color: #444;
            min-height: 14px;
        }
    </style>
</head>
<body>

@php
    $amount = (float) $replenishment->amount;
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        $whole    = floor($amount);
        $fraction = round(($amount - $whole) * 100);
        $words = strtoupper($f->format($whole)) . ' DIRHAMS';
        $words .= $fraction > 0
            ? ' AND ' . strtoupper($f->format($fraction)) . ' FILS ONLY'
            : ' ONLY';
    } else {
        $words = strtoupper(\Illuminate\Support\Number::spell($amount)) . ' DIRHAMS ONLY';
    }
@endphp

<div class="company-name">ERICK TR CO</div>
<div class="company-sub">
    TEL. NO. 06-525-2030<br>
    TIGER 2 BLDG AL TAAWUN ST. SHARJAH, UAE
</div>

<div class="title-box">
    HEAD OFFICE FLOAT REPLENISHMENT
</div>

<table class="ref-table">
    <tr>
        <td><strong>REF. NUMBER:</strong> {{ $replenishment->reference }}</td>
        <td style="text-align:right;"><strong>DATE:</strong> {{ \Carbon\Carbon::parse($replenishment->date)->format('d/m/Y') }}</td>
    </tr>
</table>

<table class="details-table">
    <tr>
        <th>AMOUNT</th>
        <td style="font-weight: bold; font-size: 13px;">AED {{ number_format($amount, 2) }}</td>
    </tr>
    <tr>
        <th>REMARKS / DESCRIPTION</th>
        <td>{!! nl2br(e($replenishment->remarks ?: 'Head Office Float Funding')) !!}</td>
    </tr>
</table>

@if($replenishment->denominations)
<div class="title-box" style="margin-top: 20px; font-size: 11px; padding: 4px; letter-spacing: 2px;">
    CASH DENOMINATION BREAKDOWN
</div>
<table class="details-table" style="margin-top: 5px; margin-bottom: 20px; width: 60%;">
    <tr>
        <th style="width: 50%;">BILLS & COINS</th>
        <th style="width: 50%; text-align: right;">AMOUNT (AED)</th>
    </tr>
    @php
    $denoms = [
        '1000 Bills' => ['col' => 'bill_1000', 'val' => 1000],
        '500 Bills'  => ['col' => 'bill_500',  'val' => 500],
        '200 Bills'  => ['col' => 'bill_200',  'val' => 200],
        '100 Bills'  => ['col' => 'bill_100',  'val' => 100],
        '50 Bills'   => ['col' => 'bill_50',   'val' => 50],
        '20 Bills'   => ['col' => 'bill_20',   'val' => 20],
        '10 Bills'   => ['col' => 'bill_10',   'val' => 10],
        '5 Bills'    => ['col' => 'bill_5',    'val' => 5],
        '1 Coins'    => ['col' => 'coin_1',    'val' => 1],
        '0.50 Coins' => ['col' => 'coin_0_50', 'val' => 0.50],
        '0.25 Coins' => ['col' => 'coin_0_25', 'val' => 0.25],
    ];
    @endphp
    @foreach($denoms as $label => $data)
        @if($replenishment->denominations->{$data['col']} > 0)
        <tr>
            <td style="border-bottom: 1px dotted #ccc; padding: 4px 10px;">{{ $label }} ({{ $replenishment->denominations->{$data['col']} }})</td>
            <td style="border-bottom: 1px dotted #ccc; padding: 4px 10px; text-align: right;">{{ number_format($data['val'] * $replenishment->denominations->{$data['col']}, 2) }}</td>
        </tr>
        @endif
    @endforeach
    <tr>
        <td style="font-weight: bold; padding: 6px 10px;">TOTAL CASH</td>
        <td style="font-weight: bold; text-align: right; padding: 6px 10px;">AED {{ number_format($replenishment->denominations->total_amount, 2) }}</td>
    </tr>
</table>
@if($replenishment->denominations->remarks)
<div style="font-size: 10px; margin-bottom: 20px;">
    <strong>Remarks on Denominations:</strong> {{ $replenishment->denominations->remarks }}
</div>
@endif
@endif

<div class="words-section">
    <div class="words-label">AMOUNT IN WORDS</div>
    <div class="words-value">{{ $words }}</div>
</div>

<table class="sig-table">
    <tr>
        <td>
            <div class="sig-label">PREPARED BY:</div>
            <div class="sig-line">{{ $replenishment->creator?->name ?? 'System Admin' }}</div>
        </td>
        <td>
            <div class="sig-label">RECEIVED / AUTHORIZED BY:</div>
            <div class="sig-line" style="width: 80%;"></div>
        </td>
    </tr>
</table>

</body>
</html>
