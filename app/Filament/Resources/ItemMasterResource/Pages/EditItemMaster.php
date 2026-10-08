<?php

namespace App\Filament\Resources\ItemMasterResource\Pages;

use App\Filament\Resources\ItemMasterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditItemMaster extends EditRecord
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
