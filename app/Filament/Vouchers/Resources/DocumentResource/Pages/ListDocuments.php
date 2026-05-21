<?php

namespace App\Filament\Vouchers\Resources\DocumentResource\Pages;

use App\Filament\Vouchers\Resources\DocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
