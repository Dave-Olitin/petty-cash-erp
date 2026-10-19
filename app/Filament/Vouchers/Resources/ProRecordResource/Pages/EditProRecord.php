<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProRecord extends EditRecord
{
    protected static string $resource = ProRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
