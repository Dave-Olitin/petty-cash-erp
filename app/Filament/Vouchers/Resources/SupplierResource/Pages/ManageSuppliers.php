<?php

namespace App\Filament\Vouchers\Resources\SupplierResource\Pages;

use App\Filament\Vouchers\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppliers extends ManageRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\TaxRegistrationExporter::class)
                ->color('gray')
                ->icon('heroicon-o-arrow-down-tray')
                ->label('Export'),
            Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\TaxRegistrationImporter::class)
                ->color('gray')
                ->icon('heroicon-o-arrow-up-tray')
                ->label('Import Excel'),
            Actions\CreateAction::make()
                ->label('New Supplier'),
        ];
    }
}
