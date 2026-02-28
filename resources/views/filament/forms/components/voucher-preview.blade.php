{{-- Live Preview — mirrors the actual PDF voucher layout (pdf/voucher.blade.php)
     Driven by $get() callbacks so it updates in real-time as the user types.
--}}
@php
    $type        = $get('type') ?? 'payment';
    $payee       = $get('payee') ?? '';
    $amount      = (float) ($get('amount') ?? 0);
    $description = $get('description') ?? '';
    $categoryId  = $get('category_id');
    $category    = $categoryId ? \App\Models\Category::find($categoryId) : null;
    $catName     = $category ? strtoupper($category->name) : 'GENERAL EXPENSE';
    $catCode     = $category?->accounting_code ?? '—';
    $title       = $type === 'petty_cash' ? 'PETTY CASH VOUCHER' : 'PAYMENT VOUCHER';
    $today       = now()->format('d/m/Y');

    // Amount in words (best-effort)
    $words = '';
    if ($amount > 0) {
        if (class_exists('NumberFormatter')) {
            $f        = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $whole    = (int) floor($amount);
            $fraction = (int) round(($amount - $whole) * 100);
            $words    = strtoupper($f->format($whole)) . ' DIRHAMS';
            $words   .= $fraction > 0
                ? ' AND ' . strtoupper($f->format($fraction)) . ' FILS ONLY'
                : ' ONLY';
        } else {
            $words = strtoupper(\Illuminate\Support\Number::spell($amount)) . ' DIRHAMS ONLY';
        }
    }
@endphp

<div style="
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    color: #111;
    line-height: 1.4;
    padding: 20px 24px; {{-- Reduced margin/padding --}}
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    min-height: 480px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    position: relative;
">

    {{-- DRAFT watermark --}}
    <div style="
        position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);
        font-size:64px;font-weight:900;color:rgba(0,0,0,0.04);letter-spacing:8px;pointer-events:none;
        white-space:nowrap;user-select:none;
    ">DRAFT</div>

    {{-- Company header --}}
    <div style="font-size:14px;font-weight:bold;letter-spacing:1.5px;margin-bottom:2px;">ERICK TR CO</div>
    <div style="font-size:9px;letter-spacing:0.5px;color:#444;margin-bottom:14px;">
        TEL. NO. 06-525-2030<br>
        TIGER 2 BLDG AL TAAWUN ST. SHARJAH, UAE
    </div>

    {{-- Title box --}}
    <div style="
        border:1.5px solid #111;text-align:center;font-size:12.5px;font-weight:bold;
        padding:5px;margin-bottom:14px;letter-spacing:3px;
    ">{{ $title }}</div>

    {{-- Voucher # / Date row --}}
    <table style="width:100%;margin-bottom:10px;border-collapse:collapse;">
        <tr>
            <td style="font-weight:bold;font-size:11px;width:50%;">P.V NO: VCH-NEW</td>
            <td style="text-align:right;font-weight:bold;font-size:11px;width:50%;">DATE: {{ $today }}</td>
        </tr>
    </table>

    {{-- Paid To --}}
    <div style="margin-bottom:6px;font-size:11px;display:flex;align-items:flex-end;">
        <span style="font-weight:bold;white-space:nowrap;padding-bottom:1px;">PAID TO:&nbsp;</span>
        <span style="flex-grow:1;border-bottom:1px solid #111;min-height:1.2em;padding-bottom:1px;">
            {{ $payee ? strtoupper($payee) : '' }}
        </span>
    </div>

    {{-- Being --}}
    <div style="margin-bottom:6px;font-size:11px;display:flex;align-items:flex-end;">
        <span style="font-weight:bold;white-space:nowrap;padding-bottom:1px;">BEING:&nbsp;&nbsp;&nbsp;</span>
        <span style="flex-grow:1;border-bottom:1px solid #111;min-height:1.2em;padding-bottom:1px;">
            {{ $description ? strtoupper($description) : '' }}
        </span>
    </div>
    <div style="margin-bottom:8px;">
        <span style="display:block;border-bottom:1px solid #111;width:100%;">&nbsp;</span>
    </div>

    {{-- Cheque row --}}
    <table style="width:100%;margin:12px 0;font-size:10px;border-collapse:collapse;">
        <tr>
            <td style="width:33%;">Cheque No.: _________________</td>
            <td style="width:33%;text-align:center;">Date: _________________</td>
            <td style="width:34%;text-align:right;">Bank: _________________</td>
        </tr>
    </table>

    {{-- Ledger table --}}
    <table style="width:100%;border-collapse:collapse;margin-top:4px;">
        <thead>
            <tr>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;width:10%;">BRANCH</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;width:13%;">ACCT CODE</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:left;width:47%;">ACCOUNT DETAILS</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;width:15%;">DR</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;width:15%;">CR</th>
            </tr>
        </thead>
        <tbody>
            <tr style="height:28px;">
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;">HQ</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;">{{ $catCode }}</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;">{{ $catName }}{{ $payee ? ' - ' . strtoupper($payee) : '' }}</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;font-weight:bold;">{{ $amount > 0 ? number_format($amount, 2) : '' }}</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;"></td>
            </tr>
            <tr style="height:28px;">
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;">HQ</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:center;">1010-02</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;">CASH IN HAND/BANK</td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;"></td>
                <td style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;font-weight:bold;">{{ $amount > 0 ? number_format($amount, 2) : '' }}</td>
            </tr>
            <tr><td colspan="5" style="border:1px solid #111;height:40px;"></td></tr>
        </tbody>
        <tfoot>
            <tr style="height:26px;">
                <th colspan="3" style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;padding-right:10px;">TOTAL</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;font-weight:bold;">{{ $amount > 0 ? number_format($amount, 2) : '' }}</th>
                <th style="border:1px solid #111;padding:4px 5px;font-size:10px;text-align:right;font-weight:bold;">{{ $amount > 0 ? number_format($amount, 2) : '' }}</th>
            </tr>
        </tfoot>
    </table>

    {{-- Amount in words --}}
    <div style="border:1px solid #111;border-top:none;padding:5px 7px;margin-bottom:18px;">
        <div style="font-weight:bold;font-size:10px;margin-bottom:3px;">AMOUNT IN WORDS</div>
        <div style="font-size:10.5px;min-height:18px;padding:2px 4px;font-weight:normal;">
            {{ $words ?: '—' }}
        </div>
    </div>

    {{-- Signature row --}}
    <table style="width:100%;margin-top:12px;border-collapse:collapse;">
        <tr>
            @php
                $sigs = [
                    'PREPARED BY:' => auth()->user()?->name ?? '',
                    'ACCOUNTANT:'  => '',
                    'GM:'          => '',
                    'RECEIVED BY:' => '',
                ];
            @endphp
            @foreach($sigs as $label => $name)
                <td style="width:25%;font-size:9.5px;font-weight:bold;vertical-align:bottom;padding-right:12px;">
                    <div style="margin-bottom:22px;">{{ $label }}</div>
                    <div style="border-top:1px solid #111;padding-top:3px;font-size:9px;font-weight:normal;color:#444;min-height:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $name }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Live update indicator --}}
    <div style="margin-top:14px;border-top:1px dashed #e5e7eb;padding-top:6px;display:flex;justify-content:space-between;align-items:center;opacity:0.4;">
        <span style="font-size:8.5px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">⬤ Live Preview</span>
        <span style="font-size:8.5px;font-family:monospace;color:#9ca3af;">VCH-NEW · DRAFT</span>
    </div>
</div>
