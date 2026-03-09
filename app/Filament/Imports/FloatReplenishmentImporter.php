<?php

namespace App\Filament\Imports;

use App\Models\FloatReplenishment;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class FloatReplenishmentImporter extends Importer
{
    protected static ?string $model = FloatReplenishment::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('reference')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric']),
            ImportColumn::make('remarks'),
        ];
    }

    public function resolveRecord(): ?FloatReplenishment
    {
        return new FloatReplenishment([
            'created_by' => auth()->id(),
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your float replenishment import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
