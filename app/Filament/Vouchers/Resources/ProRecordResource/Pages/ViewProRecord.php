<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProRecord extends ViewRecord
{
    protected static string $resource = ProRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
