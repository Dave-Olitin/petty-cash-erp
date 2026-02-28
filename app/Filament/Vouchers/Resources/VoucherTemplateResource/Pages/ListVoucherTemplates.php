<?php

namespace App\Filament\Vouchers\Resources\VoucherTemplateResource\Pages;

use App\Filament\Vouchers\Resources\VoucherTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListVoucherTemplates extends ListRecords
{
    protected static string $resource = VoucherTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
