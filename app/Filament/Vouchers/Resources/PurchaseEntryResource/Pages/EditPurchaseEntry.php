<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseEntry extends EditRecord
{
    protected static string $resource = PurchaseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(static::$resource::getUrl('index'))
                ->color('gray')
                ->icon('heroicon-m-arrow-left'),
            Actions\Action::make('toggle_lock')
                ->label(fn ($record) => $record->is_locked ? 'Unlock' : 'Lock')
                ->icon(fn ($record) => $record->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color(fn ($record) => $record->is_locked ? 'warning' : 'danger')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin']))
                ->action(function ($record) {
                    $record->update(['is_locked' => !$record->is_locked]);
                    \Filament\Notifications\Notification::make()
                        ->title($record->is_locked ? 'Purchase Entry Locked' : 'Purchase Entry Unlocked')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
