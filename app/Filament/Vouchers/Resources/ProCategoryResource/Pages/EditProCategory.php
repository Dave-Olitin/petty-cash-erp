<?php

namespace App\Filament\Vouchers\Resources\ProCategoryResource\Pages;

use App\Filament\Vouchers\Resources\ProCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProCategory extends EditRecord
{
    protected static string $resource = ProCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
