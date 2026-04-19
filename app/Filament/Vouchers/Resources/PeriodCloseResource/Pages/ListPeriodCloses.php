<?php

namespace App\Filament\Vouchers\Resources\PeriodCloseResource\Pages;

use App\Filament\Vouchers\Resources\PeriodCloseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPeriodCloses extends ListRecords
{
    protected static string $resource = PeriodCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
