<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseEntry extends CreateRecord
{
    protected static string $resource = PurchaseEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['lines']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn () => url()->previous() !== url()->current() ? url()->previous() : static::$resource::getUrl('index'))
                ->color('gray')
                ->icon('heroicon-m-arrow-left'),
        ];
    }
}
