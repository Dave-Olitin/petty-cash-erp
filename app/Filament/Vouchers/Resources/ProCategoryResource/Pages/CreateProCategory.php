<?php

namespace App\Filament\Vouchers\Resources\ProCategoryResource\Pages;

use App\Filament\Vouchers\Resources\ProCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProCategory extends CreateRecord
{
    protected static string $resource = ProCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
