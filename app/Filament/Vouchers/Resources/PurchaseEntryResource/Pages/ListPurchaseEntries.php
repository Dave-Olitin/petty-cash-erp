<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseEntries extends ListRecords
{
    protected static string $resource = PurchaseEntryResource::class;

    public function getTabs(): array
    {
        return PurchaseEntryResource::getTabs();
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
