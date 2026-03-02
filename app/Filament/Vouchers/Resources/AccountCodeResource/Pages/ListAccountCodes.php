<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\Pages;

use App\Filament\Vouchers\Resources\AccountCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountCodes extends ListRecords
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
