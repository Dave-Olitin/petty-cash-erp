<?php

namespace App\Filament\Vouchers\Pages;

use Filament\Actions\CreateAction;
use App\Models\FloatReplenishment;
use App\Filament\Vouchers\Resources\VoucherResource;
use Filament\Forms;

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
            \Filament\Actions\Action::make('create_voucher')
                ->label('Create Voucher')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()->can('voucher.create'))
                ->url(fn (): string => VoucherResource::getUrl('create')),

            CreateAction::make('fund_voucher')
                ->model(FloatReplenishment::class)
                ->label('Fund Voucher')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => auth()->user()->isHeadOffice() || auth()->user()->hasRole('Accountant'))
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->prefix('AED'),
                    Forms\Components\DatePicker::make('date')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('reference')
                        ->required()
                        ->label('Reference (e.g. Bank Transfer Ref, Cheque No)')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('remarks')
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),
                ])
                ->successNotificationTitle('Replenishment recorded successfully'),
        ];
    }
}
