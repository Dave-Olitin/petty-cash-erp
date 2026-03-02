<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\Pages;

use App\Filament\Vouchers\Resources\AccountCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountCode extends EditRecord
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
