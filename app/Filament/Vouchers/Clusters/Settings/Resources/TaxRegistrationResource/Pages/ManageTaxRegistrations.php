<?php

namespace App\Filament\Vouchers\Clusters\Settings\Resources\TaxRegistrationResource\Pages;

use App\Filament\Vouchers\Clusters\Settings\Resources\TaxRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTaxRegistrations extends ManageRecords
{
    protected static string $resource = TaxRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\TaxRegistrationImporter::class)
                ->icon('heroicon-o-arrow-up-tray'),
            Actions\CreateAction::make(),
        ];
    }
}
