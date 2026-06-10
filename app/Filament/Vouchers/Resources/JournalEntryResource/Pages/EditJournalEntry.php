<?php

namespace App\Filament\Vouchers\Resources\JournalEntryResource\Pages;

use App\Filament\Vouchers\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (\App\Models\JournalEntry $record) => route('journal_entry.pdf', $record))
                ->openUrlInNewTab(),
            Actions\Action::make('toggle_lock')
                ->label(fn ($record) => $record->is_locked ? 'Unlock' : 'Lock')
                ->icon(fn ($record) => $record->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color(fn ($record) => $record->is_locked ? 'warning' : 'danger')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->hasAnyRole(['Accountant', 'Admin', 'Super Admin']))
                ->action(function ($record) {
                    $record->update(['is_locked' => !$record->is_locked]);
                    \Filament\Notifications\Notification::make()
                        ->title($record->is_locked ? 'Journal Entry Locked' : 'Journal Entry Unlocked')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save_journal_entry')
                ->label('Save Changes')
                ->requiresConfirmation()
                ->modalHeading('Save Journal Entry')
                ->modalDescription('Are you sure you want to save these changes? Please double-check the amounts. If the totals change the shortage or excess amount, the system will automatically adjust or void the corresponding settlement vouchers.')
                ->modalSubmitActionLabel('Yes, Save Changes')
                ->action(function () {
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function afterSave(): void
    {
        app(\App\Observers\JournalEntryObserver::class)->processAutoLiquidation($this->record);
    }
}
