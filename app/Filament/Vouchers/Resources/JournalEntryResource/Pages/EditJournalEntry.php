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
                ->modalDescription('Are you sure you want to save these changes? Please double-check the amounts. If the totals change the shortage or excess amount, the system will automatically adjust or void the corresponding child vouchers.')
                ->modalSubmitActionLabel('Yes, Save Changes')
                ->action(function () {
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }
}
