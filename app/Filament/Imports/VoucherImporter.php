<?php

namespace App\Filament\Imports;

use App\Models\AccountCode;
use App\Models\LedgerBranch;
use App\Models\Voucher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class VoucherImporter extends Importer
{
    protected static ?string $model = Voucher::class;

    public static function getColumns(): array
    {
        return [
            // Matches export column: REF
            ImportColumn::make('voucher_number')
                ->label('REF')
                ->rules(['nullable', 'max:255'])
                ->example('TG-0001'),

            // Matches export column: SOURCE
            ImportColumn::make('type')
                ->label('SOURCE')
                ->requiredMapping()
                ->rules(['required', 'in:petty_cash,payment'])
                ->example('Payment'),

            // Matches export column: DATE
            ImportColumn::make('voucher_date')
                ->label('DATE')
                ->rules(['nullable', 'date'])
                ->example('2026-03-01'),

            // Matches export column: TRN
            ImportColumn::make('trn')
                ->label('TRN')
                ->rules(['nullable', 'max:255'])
                ->example('100123456700003'),

            // Matches export column: PAYEE
            ImportColumn::make('payee')
                ->label('PAYEE')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Amazon Web Services'),

            // Matches export column: P.O. NO.
            ImportColumn::make('po_number')
                ->label('P.O. NO.')
                ->rules(['nullable', 'max:255'])
                ->example('PO-2024-001'),

            // Matches export column: INVOICE NO.
            ImportColumn::make('cheque_no')
                ->label('INVOICE NO.')
                ->rules(['nullable', 'max:255'])
                ->example('INV-12345'),

            // Matches export column: DESCRIPTION
            ImportColumn::make('description')
                ->label('DESCRIPTION')
                ->rules(['nullable', 'max:1000'])
                ->example('Monthly server hosting subscription'),

            // Matches export column: ACCOUNT CODE
            ImportColumn::make('account_code')
                ->label('ACCOUNT CODE')
                ->rules(['nullable', 'max:255'])
                ->example('1010-01'),

            // Matches export column: AMOUNT (Debit)
            ImportColumn::make('amount')
                ->label('AMOUNT')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0.01'])
                ->example('500.00'),

            // Matches export column: BRANCH (debit side)
            ImportColumn::make('branch_code')
                ->label('BRANCH')
                ->rules(['nullable', 'max:255'])
                ->example('HQ'),
        ];
    }

    public function resolveRecord(): ?Voucher
    {
        $voucher = new Voucher();
        $voucher->user_id = auth()->id();
        $voucher->status  = 'draft';

        // Auto-generate voucher number if not provided in the CSV
        if (blank($this->data['voucher_number'] ?? null)) {
            $voucher->voucher_number = 'IMP-' . strtoupper(uniqid());
        }

        // Assign to first available template (required for PDF generation)
        $voucher->voucher_template_id = \App\Models\VoucherTemplate::first()?->id;

        return $voucher;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your voucher import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
