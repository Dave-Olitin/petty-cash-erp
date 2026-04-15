<?php

namespace App\Filament\Exports;

use App\Models\TaxRegistration;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TaxRegistrationExporter extends Exporter
{
    protected static ?string $model = TaxRegistration::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('trn')->label('TRN'),
            ExportColumn::make('supplier_code')->label('Supplier Code'),
            ExportColumn::make('name')->label('Supplier Name'),
            ExportColumn::make('payment_terms')->label('Payment Terms'),
            ExportColumn::make('contact_name')->label('Contact Name'),
            ExportColumn::make('phone')->label('Phone'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('entity')->label('Entity'),
            ExportColumn::make('started_date')->label('Started Date'),
            ExportColumn::make('is_active')->label('Status (Active)')->state(fn ($record) => $record->is_active ? 'Yes' : 'No'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your tax registration export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
