<?php

namespace App\Filament\Vouchers\Resources\FloatReplenishmentResource\Pages;

use App\Filament\Vouchers\Resources\FloatReplenishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFloatReplenishments extends ListRecords
{
    protected static string $resource = FloatReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
