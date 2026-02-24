<?php

namespace App\Filament\Vouchers\Resources\FloatReplenishmentResource\Pages;

use App\Filament\Vouchers\Resources\FloatReplenishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFloatReplenishment extends EditRecord
{
    protected static string $resource = FloatReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
