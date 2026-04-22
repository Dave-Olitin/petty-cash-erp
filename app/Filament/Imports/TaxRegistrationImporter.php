<?php

namespace App\Filament\Imports;

use App\Models\TaxRegistration;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class TaxRegistrationImporter extends Importer
{
    protected static ?string $model = TaxRegistration::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('trn')
                ->label('TRN')
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('supplier_code')
                ->label('Supplier Code')
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('name')
                ->label('Supplier Name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('payment_terms')
                ->label('Payment Terms')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('contact_name')
                ->label('Contact Name')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('phone')
                ->label('Phone')
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('entity')
                ->label('Entity')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('started_date')
                ->label('Started Date')
                ->castStateUsing(function (?string $state): ?string {
                    return blank($state) ? null : \Carbon\Carbon::parse($state)->toDateString();
                }),
            ImportColumn::make('is_active')
                ->label('Status (Active)')
                ->boolean()
                ->rules(['boolean']),
        ];
    }

    public function resolveRecord(): ?TaxRegistration
    {
        $trn = $this->data['trn'] ?? null;
        $supplierCode = $this->data['supplier_code'] ?? null;

        if (! blank($trn)) {
            return TaxRegistration::firstOrNew(['trn' => $trn]);
        }

        if (! blank($supplierCode)) {
            return TaxRegistration::firstOrNew(['supplier_code' => $supplierCode]);
        }

        return new TaxRegistration();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tax registration import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
