<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ match($voucher->type) { 'petty_cash' => 'Petty Cash Voucher', 'receipt' => 'Receipt Voucher', default => 'Payment Voucher' } }}</title>
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
        .watermark {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 150px;
            font-weight: bold;
            color: rgba(255, 0, 0, 0.1); /* Very faint red */
            z-index: -1;
            white-space: nowrap;
            user-select: none;
            pointer-events: none;
        }
    </style>
</head>
<body>
    @include('pdf.voucher-body', ['voucher' => $voucher, 'isPreview' => $isPreview ?? false])
</body>
</html>
