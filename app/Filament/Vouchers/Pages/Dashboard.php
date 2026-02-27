<?php

namespace App\Filament\Vouchers\Pages;

use Filament\Actions\Action;
use App\Filament\Vouchers\Resources\VoucherResource;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationLabel = 'Overview';
    protected static ?string $title = 'Overview';

    public static function canAccess(): bool
    {
        return auth()->user()->can('access_vouchers_panel');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_voucher')
                ->label('Create Voucher')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()->can('voucher.create'))
                ->url(fn (): string => VoucherResource::getUrl('create')),
        ];
    }
}
