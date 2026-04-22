<?php

namespace App\Filament\Imports;

use App\Models\AccountCode;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ChartOfAccountImporter extends Importer
{
    protected static ?string $model = AccountCode::class;


    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Account Code')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50'])
                ->example('5100'),

            ImportColumn::make('name')
                ->label('Account Name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Cash Replenishment'),

        ];
    }

    /**
     * Use firstOrNew on account_code so that re-uploading an updated sheet
     * updates existing records rather than creating duplicates.
     */
    public function resolveRecord(): ?AccountCode
    {
        return AccountCode::firstOrNew([
            'code' => $this->data['code'],
        ]);
    }

    protected function beforeSave(): void
    {
        // No is_active on account_codes table — nothing extra needed
    }


    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Chart of Accounts import completed. '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
