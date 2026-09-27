<?php

namespace App\Filament\Imports;

use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class SupplierImporter extends Importer
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Supplier Name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Al Boom Gas Distribution'),

            ImportColumn::make('trn')
                ->label('TRN')
                ->rules(['nullable', 'string', 'max:255'])
                ->example('100007637000003'),

            ImportColumn::make('phone')
                ->label('Phone Number')
                ->rules(['nullable', 'string', 'max:50'])
                ->example('+971 50 123 4567'),

            ImportColumn::make('email')
                ->label('Email Address')
                ->rules(['nullable', 'email', 'max:255'])
                ->example('supplier@example.com'),

            ImportColumn::make('address')
                ->label('Address')
                ->rules(['nullable', 'string', 'max:500'])
                ->example('Dubai, UAE'),
        ];
    }

    /**
     * Use firstOrNew on name so that re-uploading an updated sheet
     * updates existing records rather than creating duplicates.
     */
    public function resolveRecord(): ?Supplier
    {
        $name = trim($this->data['name'] ?? '');
        if ($name === '') {
            return null;
        }

        return Supplier::firstOrNew([
            'name' => $name,
        ]);
    }

    protected function beforeSave(): void
    {
        if ($this->record && $this->record->is_active === null) {
            $this->record->is_active = true;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Suppliers import completed. '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
