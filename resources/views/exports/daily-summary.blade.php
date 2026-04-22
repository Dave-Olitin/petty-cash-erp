<table style="width: 100%; border-collapse: collapse; font-family: sans-serif;">
    <!-- Header Row 1 -->
    <tr>
        <td colspan="11" style="font-weight: bold; font-size: 16px; text-align: center; background-color: #1F4E78; color: #ffffff; padding: 10px;">DAILY PETTY CASH SUMMARY REPORT</td>
    </tr>
    <!-- Header Row 2 -->
    <tr>
        <td colspan="5" style="font-weight: bold; font-size: 12px; background-color: #D9E1F2; padding: 5px;">DATE: {{ $date }}</td>
        <td colspan="6" style="font-weight: bold; font-size: 12px; text-align: right; background-color: #D9E1F2; padding: 5px;">TIME: {{ now()->format('h:i A') }}</td>
    </tr>
    <tr><td colspan="11"></td></tr>

    <!-- Table Headers Row -->
    <tr>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center;">SN</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center;">DATE</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 150px;">PCV NO / REF</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 150px;">DEPARTMENT</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 150px;">TRANSACTED BY</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 150px;">CATEGORY</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 200px;">PAYEE / DETAILS</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 150px;">CHEQUE NO.</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 100px;">CASH IN</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 100px;">CASH OUT</th>
        <th style="font-weight: bold; background-color: #2F5597; color: white; border: 1px solid black; text-align: center; width: 200px;">ATTACHMENTS</th>
    </tr>

    <!-- Beginning Balance Row -->
    <tr>
        <td colspan="6" style="border: 1px solid black;"></td>
        <td colspan="2" style="text-align: right; font-weight: bold; border: 1px solid black; background-color: #E2EFDA;">BEGINNING BALANCE</td>
        <td style="font-weight: bold; text-align: right; border: 1px solid black; background-color: #E2EFDA;">{{ $beginningBalance > 0 ? number_format($beginningBalance, 2) : '-' }}</td>
        <td style="border: 1px solid black; background-color: #E2EFDA;"></td>
        <td style="border: 1px solid black; background-color: #E2EFDA;"></td>
    </tr>

    @php 
        $runningBalance = $beginningBalance; 
        $sn = 1; 
        $totalCashIn = $beginningBalance;
        $totalCashOut = 0;
    @endphp

    @foreach($transactions as $trx)
        @php
            $in = $trx->type === 'receipt' || $trx->type === 'Replenishment' ? $trx->amount : 0;
            $out = $trx->type === 'petty_cash' ? $trx->amount : 0;
            $runningBalance = $runningBalance + $in - $out;
            $totalCashIn += $in;
            $totalCashOut += $out;
        @endphp
        <tr>
            <td style="text-align: center; border: 1px solid black;">{{ $sn++ }}</td>
            <td style="text-align: center; border: 1px solid black;">{{ \Carbon\Carbon::parse($trx->date)->format('d-M-y') }}</td>
            <td style="border: 1px solid black;">{{ $trx->reference }}</td>
            <td style="border: 1px solid black;">{{ $trx->department }}</td>
            <td style="border: 1px solid black;">{{ $trx->maker }}</td>
            <td style="border: 1px solid black;">{{ $trx->category }}</td>
            <td style="border: 1px solid black;">{{ $trx->payee }}</td>
            <td style="border: 1px solid black;">{{ $trx->cheque_no }}</td>
            <td style="text-align: right; border: 1px solid black;">{{ $in > 0 ? number_format($in, 2) : '-' }}</td>
            <td style="text-align: right; border: 1px solid black;">{{ $out > 0 ? number_format($out, 2) : '-' }}</td>
            <td style="border: 1px solid black; font-size: 9px; color: blue;">{{ $trx->attachments ?? '' }}</td>
        </tr>
    @endforeach

    <!-- Grand Total Row -->
    <tr>
        <td colspan="8" style="font-weight: bold; text-align: left; border: 1px solid black; background-color: #D9E1F2;">Grand Total</td>
        <td style="font-weight: bold; text-align: right; border: 1px solid black; background-color: #D9E1F2;">{{ number_format($totalCashIn, 2) }}</td>
        <td style="font-weight: bold; text-align: right; border: 1px solid black; background-color: #D9E1F2;">{{ number_format($totalCashOut, 2) }}</td>
        <td style="border: 1px solid black; background-color: #D9E1F2;"></td>
    </tr>
    <!-- Ending Balance Row -->
    <tr>
        <td colspan="8" style="font-weight: bold; text-align: right; color: #C00000; font-size: 14px;">ENDING BALANCE ---> </td>
        <td colspan="2" style="font-weight: bold; text-align: right; color: #C00000; font-size: 14px; border: 2px solid #C00000;">{{ number_format($runningBalance, 2) }}</td>
        <td></td>
    </tr>

    <!-- GAP -->
    <tr><td colspan="11" style="height: 30px;"></td></tr>

    <!-- DENOMINATIONS & UNALLOCATED CHANGE SPLIT -->
    <tr>
        <!-- Left Table: Denominations -->
        <td colspan="4" style="font-weight: bold; font-size: 11px; text-align: center; background-color: #2F5597; color: white; border: 1px solid black; padding: 5px;">PHYSICAL CASH DENOMINATIONS</td>
        <!-- Spacer -->
        <td></td>
        <!-- Right Table: Unallocated Change -->
        <td colspan="5" style="font-weight: bold; font-size: 11px; text-align: center; background-color: #C55A11; color: white; border: 1px solid black; padding: 5px;">UNALLOCATED CHANGE BREAKDOWN</td>
        <td></td>
    </tr>
    <tr>
        <!-- Left Header -->
        <th colspan="2" style="font-weight: bold; background-color: #D9E1F2; border: 1px solid black; text-align: center;">DENOMINATION</th>
        <th style="font-weight: bold; background-color: #D9E1F2; border: 1px solid black; text-align: center;">PIECES</th>
        <th style="font-weight: bold; background-color: #D9E1F2; border: 1px solid black; text-align: center;">TOTAL (AED)</th>
        <!-- Spacer -->
        <td></td>
        <!-- Right Header -->
        <th colspan="3" style="font-weight: bold; background-color: #F8CBAD; border: 1px solid black; text-align: center;">SOURCE VOUCHER</th>
        <th colspan="2" style="font-weight: bold; background-color: #F8CBAD; border: 1px solid black; text-align: center;">AMOUNT (AED)</th>
        <td></td>
    </tr>

    @php 
        $denoKeys = [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.50, 0.25];
        $grandTotalDenom = 0;
        $unallocatedRecords = isset($unallocatedChangeRecords) ? clone $unallocatedChangeRecords : collect([]);
        $maxBottomRows = max(count($denoKeys), $unallocatedRecords->count());
    @endphp

    @for($i = 0; $i < $maxBottomRows; $i++)
        <tr>
            <!-- Left Side -->
            @if($i < count($denoKeys))
                @php 
                    $key = $denoKeys[$i];
                    $qty = $denominations[(string)$key] ?? 0;
                    $tot = $qty * (float)$key;
                    $grandTotalDenom += $tot;
                @endphp
                <td colspan="2" style="text-align: center; border: 1px solid black;">{{ $key }}</td>
                <td style="text-align: center; border: 1px solid black;">{{ $qty }}</td>
                <td style="text-align: right; border: 1px solid black;">{{ $tot != 0 ? number_format($tot, 2) : '-' }}</td>
            @else
                <td colspan="4"></td>
            @endif

            <!-- Spacer -->
            <td></td>

            <!-- Right Side -->
            @if($i < $unallocatedRecords->count())
                @php
                    $changeRecord = $unallocatedRecords[$i];
                    $voucherRef = $changeRecord->denominatable->voucher_number ?? 'Unknown Voucher';
                @endphp
                <td colspan="3" style="text-align: left; border: 1px solid black; background-color: #FFF2CC;">{{ $voucherRef }}</td>
                <td colspan="2" style="text-align: right; border: 1px solid black; background-color: #FFF2CC;">{{ number_format($changeRecord->change_given, 2) }}</td>
            @else
                <td colspan="5"></td>
            @endif
            
            <!-- End Spacer -->
            <td></td>
        </tr>
    @endfor

    <!-- Totals Rows (Stacked beneath Denoms) -->
    <tr>
        <td colspan="3" style="font-weight: bold; text-align: center; border: 2px solid #2F5597; color: #2F5597; background-color: #D9E1F2;">TOTAL DENOMINATIONS</td>
        <td style="font-weight: bold; text-align: right; border: 2px solid #2F5597; color: #2F5597; background-color: #D9E1F2;">{{ number_format($grandTotalDenom, 2) }}</td>
        <td colspan="7"></td>
    </tr>
    <tr>
        <td colspan="3" style="font-weight: bold; text-align: center; border: 2px solid #C55A11; color: #C55A11; background-color: #F8CBAD;">TOTAL UNALLOCATED CHANGE</td>
        <td style="font-weight: bold; text-align: right; border: 2px solid #C55A11; color: #C55A11; background-color: #F8CBAD;">{{ number_format($unallocatedChange, 2) }}</td>
        <td colspan="7"></td>
    </tr>
    <tr>
        <td colspan="3" style="font-weight: bold; text-align: right; border: 2px solid #000; font-size: 14px; background-color: #FFE699;">TOTAL COMBINED CASH IN BOX:</td>
        <td style="font-weight: bold; text-align: right; border: 2px solid #000; font-size: 14px; background-color: #FFE699;">{{ number_format($grandTotalDenom + $unallocatedChange, 2) }}</td>
        <td colspan="7"></td>
    </tr>

</table>
