<?php

namespace App\Filament\Vouchers\Resources\JournalEntryResource\Pages;

use App\Filament\Vouchers\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('period_close')
                ->label('Period Close')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('info')
                ->url(fn () => \App\Filament\Vouchers\Resources\PeriodCloseResource::getUrl('index')),
            Actions\CreateAction::make(),
        ];
    }
}
