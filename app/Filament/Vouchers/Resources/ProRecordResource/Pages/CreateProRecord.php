<?php

namespace App\Filament\Vouchers\Resources\ProRecordResource\Pages;

use App\Filament\Vouchers\Resources\ProRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProRecord extends CreateRecord
{
    protected static string $resource = ProRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
