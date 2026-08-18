<?php

namespace App\Filament\Vouchers\Resources\PurchaseEntryResource\Pages;

use App\Filament\Vouchers\Resources\PurchaseEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseEntry extends ViewRecord
{
    protected static string $resource = PurchaseEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn () => url()->previous() !== url()->current() ? url()->previous() : static::$resource::getUrl('index'))
                ->color('gray')
                ->icon('heroicon-m-arrow-left'),
            Actions\Action::make('toggle_lock')
                ->label(fn ($record) => $record->is_locked ? 'Unlock' : 'Lock')
                ->icon(fn ($record) => $record->is_locked ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
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
            Actions\EditAction::make(),
            Actions\ReplicateAction::make()
                ->label('Duplicate')
                ->modalHeading('Duplicate Purchase Entry')
                ->modalSubmitActionLabel('Duplicate')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                ->modalDescription(fn ($record) => "Are you sure you want to duplicate purchase entry {$record->entry_no}? A new unpaid entry will be created and all line items will be copied.")
                ->excludeAttributes(['entry_no', 'is_locked', 'payment_status', 'amount_paid', 'balance_due'])
                ->beforeReplicaSaved(function (\Illuminate\Database\Eloquent\Model $replica): void {
                    $replica->payment_status = 'unpaid';
                    $replica->amount_paid = 0;
                    $replica->is_locked = false;
                    $replica->entry_no = '';
                    $replica->user_id = auth()->id();
                })
                ->afterReplicaSaved(function (\Illuminate\Database\Eloquent\Model $original, \Illuminate\Database\Eloquent\Model $replica): void {
                    foreach ($original->lines as $line) {
                        $newLine = $line->replicate();
                        $newLine->purchase_entry_id = $replica->id;
                        $newLine->save();
                    }
                })
                ->successRedirectUrl(fn (\Illuminate\Database\Eloquent\Model $replica): string => \App\Filament\Vouchers\Resources\PurchaseEntryResource::getUrl('view', ['record' => $replica])),
        ];
    }
}
