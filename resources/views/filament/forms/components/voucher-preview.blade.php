{{-- Live PDF Preview — uses barryvdh/laravel-dompdf to render real PDF --}}
@php
    $type        = $get('type') ?? 'payment';
    $payee       = $get('payee') ?? '';
    $amount      = (float) ($get('amount') ?? 0);
    $description = $get('description') ?? '';
    $templateId  = $get('voucher_template_id');
    $template    = $templateId ? \App\Models\VoucherTemplate::find($templateId) : null;
    
    // Mock the Voucher model for the PDF view
    $voucher = new \App\Models\Voucher();
    $voucher->fill([
        'type' => $type,
        'payee' => $payee,
        'amount' => $amount,
        'description' => $description,
        'cheque_no' => '',
        'bank' => '',
        'transaction_summary' => $get('transaction_summary') ?? '',
    ]);
    
    // Fake the unfillable fields that PDF renders
    $voucher->voucher_number = 'VCH-NEW';
    $voucher->created_at = now();
    $voucher->cheque_date = null;

    // Fake relationships
    $voucher->setRelation('user', auth()->user());
    $voucher->setRelation('approvals', collect([])); // empty for preview
    if ($template) {
        $voucher->setRelation('template', $template);
    }

    // Reconstruct items as VoucherItem models
    $previewItems = $get('items') ?? [];
    $items = collect($previewItems)->map(function ($data) {
        $item = new \App\Models\VoucherItem();
        $item->fill([
            'entry_type' => $data['entry_type'] ?? 'debit',
            'branch_code' => $data['branch_code'] ?? null,
            'account_code' => $data['account_code'] ?? null,
            'description' => $data['description'] ?? '',
            'amount' => (float)($data['amount'] ?? 0),
        ]);
        return $item;
    });
    $voucher->setRelation('items', $items);

    // Generate the PDF in real-time
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.voucher', [
        'voucher' => $voucher,
        'template' => $template,
        'isPreview' => true,
    ]);
    
    // Set to A4 and render
    $pdf->setPaper('A4', 'portrait');
    $base64 = base64_encode($pdf->output());
@endphp

<div style="height: 650px; width: 100%; margin: -1.5rem; width: calc(100% + 3rem);">
    <iframe src="data:application/pdf;base64,{{ $base64 }}#view=FitH" width="100%" height="100%" style="border: none; border-radius: 6px;"></iframe>
</div>
