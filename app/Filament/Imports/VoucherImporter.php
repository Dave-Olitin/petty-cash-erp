<?php

namespace App\Filament\Imports;

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
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required', 'in:petty_cash,payment'])
                ->example('payment'),
            ImportColumn::make('payee')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Amazon Web Services'),
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0.01'])
                ->example('500.00'),
            ImportColumn::make('category')
                ->relationship(resolveUsing: 'name')
                ->example('Office Supplies'),
            ImportColumn::make('description')
                ->example('Monthly subscription'),
        ];
    }

    public function resolveRecord(): ?Voucher
    {
        $voucher = new Voucher();
        $voucher->user_id = auth()->id(); // Auto-assign to the uploader
        $voucher->status = 'draft';       // Always start as draft
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
