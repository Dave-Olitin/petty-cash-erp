<?php

namespace App\Filament\Vouchers\Resources\JournalEntryResource\Pages;

use App\Filament\Vouchers\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('submit_journal_entry')
                ->label('Create')
                ->requiresConfirmation()
                ->modalHeading('Submit Journal Entry')
                ->modalDescription('Are you sure you want to submit this journal entry? Please double-check the amounts. If the totals result in a shortage or excess, the system will automatically generate the corresponding settlement vouchers.')
                ->modalSubmitActionLabel('Yes, Submit')
                ->action(function () {
                    $this->create();
                }),
            ...(static::canCreateAnother() ? [$this->getCreateAnotherFormAction()] : []),
            $this->getCancelFormAction(),
        ];
    }

    protected function afterCreate(): void
    {
        app(\App\Observers\JournalEntryObserver::class)->processAutoLiquidation($this->record);
    }
}
