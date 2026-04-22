<?php

namespace App\Filament\Vouchers\Resources\VoucherTemplateResource\Pages;

use App\Filament\Vouchers\Resources\VoucherTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditVoucherTemplate extends EditRecord
{
    protected static string $resource = VoucherTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
