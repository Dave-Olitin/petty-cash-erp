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
