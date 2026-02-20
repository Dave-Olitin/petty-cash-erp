<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher #{{ $transaction->id }}</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 14px; line-height: 1.3; color: #000; padding: 20px; max-width: 900px; margin: 0 auto; }
        .header-top { text-align: left; margin-bottom: 20px; }
        .company-name { font-weight: bold; font-size: 18px; text-transform: uppercase; }
        .company-details { font-size: 12px; }
        
        .voucher-title { 
            text-align: center; 
            font-weight: bold; 
            border: 1px solid #000; 
            padding: 5px; 
            margin: 15px 0; 
            font-size: 16px;
        }

        .meta-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        
        .payment-info { margin-bottom: 20px; }
        .info-row { display: flex; margin-bottom: 5px; align-items: baseline; }
        .info-label { width: 80px; font-weight: bold; white-space: nowrap; }
        .info-value { flex: 1; border-bottom: 1px solid #000; padding-left: 5px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th, td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        th { text-align: center; font-weight: bold; background-color: #f0f0f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .amount-words-row { border: 1px solid #000; border-top: none; padding: 5px; margin-top: -1px; }
        .amount-words-content { border: 1px solid #000; padding: 5px; min-height: 40px; display: flex; align-items: center; }
        
        .signatures { margin-top: 50px; display: flex; justify-content: space-between; font-size: 12px; }
        .sig-block { width: 22%; }
        .sig-label { font-weight: bold; margin-bottom: 40px; }
        .sig-line { border-bottom: 1px solid #000; margin-bottom: 5px; }

        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none; }
            @page { margin: 0.5cm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header-top">
        <div class="company-name">ERICK TR CO</div>
        <div class="company-details">TEL. NO. 06-525-2030</div>
        <div class="company-details">TIGER 2 BLDG AL TAAWUN ST. SHARJAH, UAE</div>
    </div>

    <div class="voucher-title">PAYMENT VOUCHER</div>

    <div class="meta-row">
        <div>
            <strong>P.V NO:</strong> 
            {{ $transaction->branch ? $transaction->branch->code : 'HO' }}-{{ $transaction->id }}
        </div>
        <div>
            <strong>DATE:</strong> {{ $transaction->created_at->format('d/m/Y') }}
        </div>
    </div>

    @if($transaction->cheque_number || $transaction->bank_name)
    <div class="meta-row" style="margin-top: -10px; margin-bottom: 20px;">
        <div>
            <strong>CHEQUE NO:</strong> {{ $transaction->cheque_number ?? 'N/A' }}
        </div>
        <div>
            <strong>DATE:</strong> {{ $transaction->cheque_date ? $transaction->cheque_date->format('d/m/Y') : 'N/A' }}
        </div>
        <div>
            <strong>BANK:</strong> {{ strtoupper($transaction->bank_name ?? 'N/A') }}
        </div>
    </div>
    @endif

    <div class="payment-info">
        <div class="info-row">
            <span class="info-label">PAID TO:</span>
            <span class="info-value">{{ strtoupper($transaction->payee) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">BEING:</span>
            <span class="info-value">{{ strtoupper($transaction->description) }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">BRANCH</th>
                <th style="width: 15%;">ACCT CODE</th>
                <th style="width: 55%;">ACCOUNT DETAILS</th>
                <th style="width: 10%;">DR</th>
                <th style="width: 10%;">CR</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalDebit = 0;
            @endphp
            {{-- Debit Rows (Expenses) --}}
            @foreach($transaction->items as $item)
            @php $totalDebit += $item->total_price; @endphp
            <tr>
                <td class="text-center">{{ $transaction->branch ? $transaction->branch->code : 'HO' }}</td>
                <td class="text-center">{{ $item->category->gl_code ?? 'N/A' }}</td>
                <td>
                    {{ strtoupper($item->category->name ?? '') }}
                    @if($item->name) - {{ strtoupper($item->name) }} @endif
                </td>
                <td class="text-right">{{ number_format($item->total_price, 2) }}</td>
                <td class="text-right"></td>
            </tr>
            @endforeach

            {{-- Credit Row (Cash/Bank) --}}
            <tr>
                <td class="text-center">{{ $transaction->branch ? $transaction->branch->code : 'HO' }}</td>
                <td class="text-center">{{ $transaction->branch->gl_code ?? '1010-00' }}</td>
                <td>
                    CASH IN HAND/BANK - {{ strtoupper($transaction->branch->name ?? 'Head Office') }}
                </td>
                <td class="text-right"></td>
                <td class="text-right">{{ number_format($transaction->amount, 2) }}</td>
            </tr>
            
            {{-- Spacer Rows to fill height --}}
            @for($i = count($transaction->items); $i < 5; $i++)
            <tr style="height: 25px;">
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
            @endfor

            {{-- Total Row --}}
            <tr style="font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL</td>
                <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                <td class="text-right">{{ number_format($transaction->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="amount-words-row">
        <div style="font-weight: bold; margin-bottom: 5px;">AMOUNT IN WORDS</div>
        <div style="border: 1px solid #000; padding: 10px; min-height: 20px; text-transform: uppercase;">
             @php
                try {
                    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
                    $amount = $transaction->amount;
                    $words = $f->format($amount);
                    echo ucwords($words) . " Dirhams Only"; 
                } catch (\Throwable $e) {
                    echo "AED " . number_format($transaction->amount, 2) . " Only";
                }
            @endphp
        </div>
    </div>

    <div class="signatures">
        <div class="sig-block">
            <div class="sig-label">PREPARED BY:</div>
            <div class="sig-line"></div>
            <div class="text-center"></div>
        </div>
        <div class="sig-block">
             <div class="sig-label">ACCOUNTANT:</div>
            <div class="sig-line"></div>
        </div>
        <div class="sig-block">
             <div class="sig-label">GM:</div>
            <div class="sig-line"></div>
        </div>
        <div class="sig-block">
             <div class="sig-label">RECEIVED BY:</div>
            <div class="sig-line"></div>
        </div>
    </div>

</body>
</html>
