<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseEntry extends EditRecord
{
    protected static string $resource = PurchaseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
