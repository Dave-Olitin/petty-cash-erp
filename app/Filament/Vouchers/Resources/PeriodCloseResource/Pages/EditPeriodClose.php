<?php

namespace App\Filament\Vouchers\Resources\PeriodCloseResource\Pages;

use App\Filament\Vouchers\Resources\PeriodCloseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPeriodClose extends EditRecord
{
    protected static string $resource = PeriodCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
