

@if($voucher->status === 'voided')
    <div style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; text-align: center; font-weight: bold; font-family: Manrope, sans-serif; border-radius: 10px; margin-bottom: 20px; font-size: 14px; letter-spacing: 1px;">
        THIS VOUCHER HAS BEEN CANCELLED
    </div>
@elseif($isPreview ?? false)
    <div class="watermark">DRAFT</div>
@endif

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

    $checkerName  = $voucher->approvals->firstWhere('action', 'checked')?->user?->name ?? '';

    // Resolve each approver slot by cross-referencing approval records with ApprovalWorkflow labels.
    // Each 'approved' action records who approved at which step. We match by checking the
    // workflow config for each step to get the correct name per slot.
    $gmName  = '';
    $ceoName = '';

    $approvedActions = $voucher->approvals->where('action', 'approved')->values();

    if ($approvedActions->isNotEmpty()) {
        $totalSteps = \App\Models\ApprovalWorkflow::totalSteps();

        if ($totalSteps >= 2) {
            // Multi-step: first approval = step 1 (GM), second = step 2 (CEO)
            $gmName  = $approvedActions->get(0)?->user?->name ?? '';
            $ceoName = $approvedActions->get(1)?->user?->name ?? '';
        } else {
            // Single-step or no config: only one approver — goes into GM slot
            $gmName = $approvedActions->last()?->user?->name ?? '';
        }

        // Override with label-based matching if workflow labels are configured (GM / CEO keywords)
        foreach ($approvedActions as $approval) {
            $label = strtolower($approval->comments ?? '');
            if (str_contains($label, 'gm') || str_contains($label, 'general manager')) {
                $gmName = $approval->user?->name ?? $gmName;
            } elseif (str_contains($label, 'ceo') || str_contains($label, 'chief')) {
                $ceoName = $approval->user?->name ?? $ceoName;
            }
        }
    }
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
    {{ match($voucher->type) { 'petty_cash' => 'PETTY CASH VOUCHER', 'receipt' => 'RECEIPT VOUCHER', default => 'PAYMENT VOUCHER' } }}
</div>

<table class="ref-table">
    <tr>
        <td>
            @if($voucher->type === 'receipt')
                <strong>{{ $voucher->voucher_number }}</strong>
            @elseif($voucher->type === 'petty_cash')
                <strong>{{ $voucher->voucher_number }}</strong>
            @else
                <strong>P.V NO:</strong> {{ str_replace(['PV NO: ', 'P.V NO: '], '', trim($voucher->voucher_number)) }}
            @endif
        </td>
        <td style="text-align:right;"><strong>DATE:</strong> {{ $voucher->date ? $voucher->date->format('d/m/Y') : $voucher->created_at->format('d/m/Y') }}</td>
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

@if(!empty($voucher->multiple_payments) && is_array($voucher->multiple_payments) && count($voucher->multiple_payments) > 0)
    <table class="cheque-table" style="margin-bottom: 2px;">
        @foreach($voucher->multiple_payments as $payment)
        <tr>
            <td style="width: 30%;">Ref/Cheque: {{ $payment['cheque_no'] ?? '__________________' }}</td>
            <td style="width: 25%; text-align:center;">Date: {{ !empty($payment['cheque_date']) ? \Carbon\Carbon::parse($payment['cheque_date'])->format('d/m/Y') : '__________________' }}</td>
            <td style="width: 25%; text-align:center;">Bank: {{ $payment['bank'] ?? '__________________' }}</td>
            <td style="width: 20%; text-align:right;">Amount: {{ isset($payment['amount']) ? number_format($payment['amount'], 2) : '__________________' }}</td>
        </tr>
        @endforeach
    </table>
@else
    <table class="cheque-table">
        <tr>
            <td>Cheque No.: {{ $voucher->cheque_no ?: '_____________________' }}</td>
            <td style="text-align:center;">Date: {{ $voucher->cheque_date ? (\Carbon\Carbon::parse($voucher->cheque_date)->format('d/m/Y')) : '_____________________' }}</td>
            <td style="text-align:right;">Bank: {{ $voucher->bank ?: '_____________________' }}</td>
        </tr>
    </table>
@endif

@php
    $hasPo = $voucher->items->contains(function($item) {
        return !empty($item->po_number);
    });
    $hasInv = $voucher->items->contains(function($item) {
        return !empty($item->invoice_number);
    });
    
    $extraCols = ($hasPo ? 1 : 0) + ($hasInv ? 1 : 0);
@endphp

<table class="ledger-table">
    <thead>
        <tr>
            <th class="col-branch" style="width: 8%;">BRANCH</th>
            <th class="col-acct" style="width: 9%;">ACCT<br>CODE</th>
            <th class="col-trn" style="width: 9%;">TRN</th>
            @if($hasPo)
                <th class="col-po" style="width: 9%;">PO #</th>
            @endif
            @if($hasInv)
                <th class="col-inv" style="width: 9%;">INV #</th>
            @endif
            
            @if($extraCols == 2)
                <th class="col-detail" style="width: 22%;">ACCOUNT DETAILS</th>
                <th class="col-dr" style="width: 17%;">DR</th>
                <th class="col-cr" style="width: 17%;">CR</th>
            @elseif($extraCols == 1)
                <th class="col-detail" style="width: 26%;">ACCOUNT DETAILS</th>
                <th class="col-dr" style="width: 19%;">DR</th>
                <th class="col-cr" style="width: 19%;">CR</th>
            @else
                <th class="col-detail" style="width: 34%;">ACCOUNT DETAILS</th>
                <th class="col-dr" style="width: 20%;">DR</th>
                <th class="col-cr" style="width: 20%;">CR</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($voucher->items as $item)
        <tr>
            <td class="col-branch">{{ strtoupper($item->branch_code ?? ($template?->branch_code ?? 'HQ')) }}</td>
            <td class="col-acct">{{ $item->account_code ?? '—' }}</td>
            <td class="col-trn">{{ $item->trn ?? '—' }}</td>
            @if($hasPo)
                <td class="col-po">{{ $item->po_number ?? '—' }}</td>
            @endif
            @if($hasInv)
                <td class="col-inv">{{ $item->invoice_number ?? '—' }}</td>
            @endif
            <td class="col-detail">{{ strtoupper($item->description ?? ($item->category?->name ?? '')) }}</td>
            <td class="col-dr">{{ $item->entry_type === 'debit' ? number_format($item->amount, 2) : '' }}</td>
            <td class="col-cr">{{ $item->entry_type === 'credit' ? number_format($item->amount, 2) : '' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="{{ 6 + $extraCols }}" style="text-align:center; padding: 10px;">No ledger entries</td>
        </tr>
        @endforelse
        @if($voucher->items->count() < 5)
            @for($i = $voucher->items->count(); $i < 5; $i++)
                <tr><td colspan="{{ 6 + $extraCols }}" style="height: 22px;">&nbsp;</td></tr>
            @endfor
        @endif
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th colspan="{{ 4 + $extraCols }}" class="lbl">TOTAL</th>
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
            <div class="sig-line">{{ $gmName }}</div>
        </td>
        <td>
            <div class="sig-label">CEO:</div>
            <div class="sig-line">{{ $ceoName }}</div>
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
    <strong>REMARKS:</strong><br>
    {!! nl2br(e($voucher->transaction_summary)) !!}
</div>
@endif

@if($voucher->purchaseEntries && $voucher->purchaseEntries->count() > 0)
@php $voucher->purchaseEntries->loadMissing('lines'); @endphp
<div style="margin-top: 20px; font-size: 10px;">
    <strong>LINKED PURCHASE ENTRIES (SYSTEM):</strong>
    <table style="width: 100%; border-collapse: collapse; margin-top: 5px; border: 1px solid #000;">
        <thead>
            <tr style="border-bottom: 1px solid #000; background-color: #f8f8f8;">
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left; width: 14%;">Entry No.</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left; width: 44%;">Supplier & Details</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: right; width: 14%;">Invoice Total</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: right; width: 14%;">Applied Here</th>
                <th style="padding: 4px; text-align: right; width: 14%;">Balance Due</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $peTotal = 0; 
                $projectedRemaining = (float) $voucher->amount;
            @endphp
            @foreach($voucher->purchaseEntries as $pe)
                @php 
                    $pivotApplied = (float) ($pe->pivot->amount_applied ?? 0);
                    $peGrandTotal = (float) ($pe->grand_total ?? $pe->total_amount ?? 0);
                    if ($pe->isReturn()) $peGrandTotal = -$peGrandTotal;
                    
                    if ($voucher->status === 'paid') {
                        $peAmount = $pivotApplied;
                    } else {
                        // Project what it will apply based on siblings
                        $otherPaid = (float) $pe->vouchers()
                            ->where('vouchers.status', 'paid')
                            ->where('vouchers.id', '!=', $voucher->id)
                            ->sum('purchase_entry_voucher.amount_applied');
                            
                        $stillOwed = max(0, abs($peGrandTotal) - $otherPaid);
                        
                        $peAmount = min($projectedRemaining, $stillOwed);
                        $projectedRemaining -= $peAmount;
                    }

                    if ($pe->isReturn()) $peAmount = -$peAmount;
                    $peTotal += $peAmount; 
                    
                    $supplier = $pe->taxRegistration?->name ?? $pe->supplier_name ?? '—';
                    $po = $pe->po_number ?: '—';
                    $inv = $pe->invoice_no ?: '—';
                    $desc = strtoupper($pe->lines->first()?->description ?: '—');
                    
                    $allSiblings = $pe->vouchers()->withPivot('amount_applied')->orderBy('date')->orderBy('vouchers.id')->get();
                @endphp
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 4px; border-right: 1px solid #000; vertical-align: top;">{{ $pe->entry_no ?? '—' }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000; vertical-align: top;">
                        <strong style="font-size: 11px;">{{ $supplier }}</strong><br>
                        <span style="color: #444;">PO: {{ $po }} | INV: {{ $inv }}</span><br>
                        {{ $desc }}
                        
                        @if($allSiblings->count() > 1)
                            <div style="margin-top: 6px; padding: 4px; background-color: #f9f9f9; border: 1px solid #ddd; font-size: 9px;">
                                <strong>Payment History for this Entry:</strong>
                                <table style="width: 100%; border-collapse: collapse; margin-top: 2px;">
                                @foreach($allSiblings as $sv)
                                    @php 
                                       $isThis = $sv->id === $voucher->id; 
                                       $svAmt = (float)($sv->pivot->amount_applied ?? 0);
                                       if ($isThis && $voucher->status !== 'paid') {
                                           $svAmt = $peAmount; // Use projected amount
                                       }
                                       $svDate = $sv->date ? $sv->date->format('d/m/Y') : '—';
                                       
                                       $statusText = match($sv->status) {
                                            'paid'             => 'Paid',
                                            'pending_checker'  => 'Pending',
                                            'pending_approver' => 'Pending',
                                            default            => ucwords(str_replace('_', ' ', $sv->status)),
                                        };
                                    @endphp
                                    <tr>
                                        <td style="padding: 1px 0; {{ $isThis ? 'font-weight: bold; color: #000;' : 'color: #555;' }}">
                                            &bull; {{ $sv->voucher_number }} 
                                            {{ $isThis ? '(THIS PAYMENT)' : "($statusText - $svDate)" }}
                                        </td>
                                        <td style="padding: 1px 0; text-align: right; {{ $isThis ? 'font-weight: bold; color: #000;' : 'color: #555;' }}">
                                            {{ number_format($svAmt, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </table>
                            </div>
                        @endif
                    </td>
                    <td style="padding: 4px; border-right: 1px solid #000; text-align: right; vertical-align: top;">{{ number_format($peGrandTotal, 2) }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000; text-align: right; vertical-align: top; font-weight: bold;">{{ number_format($peAmount, 2) }}</td>
                    <td style="padding: 4px; text-align: right; vertical-align: top; font-weight: bold;">{{ number_format(max(0, $pe->balance_due), 2) }}</td>
                </tr>
            @endforeach
            <tr style="border-top: 1px solid #000;">
                <td colspan="3" style="padding: 4px; font-weight: bold; text-align: right; border-right: 1px solid #000;">TOTAL APPLIED IN THIS VOUCHER:</td>
                <td style="padding: 4px; text-align: right; font-weight: bold; border-right: 1px solid #000;">{{ number_format($peTotal, 2) }}</td>
                <td style="padding: 4px; background-color: #f8f8f8;"></td>
            </tr>
        </tbody>
    </table>
</div>
@endif

@if(!empty($voucher->invoice_breakdown))
<div style="margin-top: 20px; font-size: 10px;">
    <strong>INVOICE / PO BREAKDOWN (MANUAL):</strong>
    <table style="width: 100%; border-collapse: collapse; margin-top: 5px; border: 1px solid #000;">
        <thead>
            <tr style="border-bottom: 1px solid #000; background-color: #f8f8f8;">
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left;">Category</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left;">Vendor/Staff</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left;">PO #</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left;">INV #</th>
                <th style="padding: 4px; border-right: 1px solid #000; text-align: left;">Description</th>
                <th style="padding: 4px; text-align: right;">Amount (AED)</th>
            </tr>
        </thead>
        <tbody>
            @php $bdTotal = 0; @endphp
            @foreach($voucher->invoice_breakdown as $bd)
                @php $bdTotal += (float) ($bd['total_amount'] ?? 0); @endphp
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 4px; border-right: 1px solid #000;">{{ $bd['category'] ?? '—' }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000;">{{ $bd['vendor_staff'] ?? '—' }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000;">{{ $bd['lpo_number'] ?? '—' }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000;">{{ $bd['invoice_number'] ?? '—' }}</td>
                    <td style="padding: 4px; border-right: 1px solid #000;">{{ strtoupper($bd['description'] ?? '—') }}</td>
                    <td style="padding: 4px; text-align: right;">{{ number_format((float) ($bd['total_amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
            <tr style="border-top: 1px solid #000;">
                <td colspan="5" style="padding: 4px; font-weight: bold; text-align: right; border-right: 1px solid #000;">TOTAL BREAKDOWN:</td>
                <td style="padding: 4px; text-align: right; font-weight: bold;">{{ number_format($bdTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endif

@if($voucher->denominations)
<div style="margin-top: 15px; border-top: 1px dashed #000; padding-top: 10px; font-size: 10px;">
    <strong>CASH DENOMINATIONS:</strong><br>
    @php
        $denoms = [
            'bill_1000' => 1000, 'bill_500' => 500, 'bill_200' => 200,
            'bill_100' => 100, 'bill_50' => 50, 'bill_20' => 20,
            'bill_10' => 10, 'bill_5' => 5, 'coin_1' => 1,
            'coin_0_50' => 0.50, 'coin_0_25' => 0.25
        ];
        $str = [];
        foreach ($denoms as $key => $val) {
            if ($qty = $voucher->denominations->$key) {
                $str[] = "AED ".number_format($val, str_contains($key, 'coin') ? 2 : 0)." x $qty = <b>" . number_format($val * $qty, 2) . "</b>";
            }
        }
        $cashTotal = $voucher->denominations->total_amount;
        $change = $voucher->denominations->change_given ?? 0;
        $deduction = $voucher->denominations->prior_deduction ?? 0;
        $finalBalanced = ($cashTotal - $change) + $deduction;
    @endphp
    <div style="margin-bottom: 6px; line-height: 1.5;">{!! implode(' &nbsp;&bull;&nbsp; ', $str) !!}</div>
    
    <table style="width: 350px; font-size: 10px; border-collapse: collapse;">
        <tr>
            <td style="padding-bottom: 2px;">Physical Cash Tendered:</td>
            <td style="text-align: right; padding-bottom: 2px;">AED {{ number_format($cashTotal, 2) }}</td>
        </tr>
        @if($change > 0)
        <tr>
            <td style="padding-bottom: 2px;">Less: Change Returned:</td>
            <td style="text-align: right; padding-bottom: 2px;">(AED {{ number_format($change, 2) }})</td>
        </tr>
        @endif
        @if($deduction > 0)
        <tr>
            <td style="padding-bottom: 2px;">Plus: Cash Advance / Prior Deduction:</td>
            <td style="text-align: right; padding-bottom: 2px;">AED {{ number_format($deduction, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding-top: 4px; font-weight: bold; border-top: 1px dashed #666;">Final Balanced Amount:</td>
            <td style="text-align: right; font-weight: bold; padding-top: 4px; border-top: 1px dashed #666;">AED {{ number_format($finalBalanced, 2) }}</td>
        </tr>
    </table>
</div>
@endif


