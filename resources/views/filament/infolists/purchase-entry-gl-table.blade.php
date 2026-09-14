@php
    $record = $getRecord() ?? ($record ?? null);
    if ($record) {
        $linesData = $record->lines->map(function ($line) {
            return [
                'description' => $line->description,
                'branch' => $line->branch,
                'debit_account_id' => $line->debit_account_id ?? $line->credit_account_id,
                'amount' => max((float) ($line->amount ?? 0), (float) ($line->total ?? 0), (float) ($line->debit ?? 0), (float) ($line->credit ?? 0)),
                'debit' => (float) ($line->debit ?? 0),
                'credit' => (float) ($line->credit ?? 0),
                'total' => (float) ($line->total ?? 0),
            ];
        })->toArray();

        $previewHtml = \App\Filament\Vouchers\Resources\PurchaseEntryResource::renderGlPreviewHtml([
            'lines' => $linesData,
            'entry_type' => $record->entry_type ?? 'purchase',
            'supplier_account_id' => $record->supplier_account_id,
            'tax_registration_id' => $record->tax_registration_id,
        ]);
    } else {
        $previewHtml = '';
    }
@endphp

<div style="width: 100% !important; min-width: 100% !important;" class="w-full">
    {!! $previewHtml !!}
</div>
