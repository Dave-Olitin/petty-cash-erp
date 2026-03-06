<?php

namespace App\Filament\Resources\ChartOfAccountResource\Pages;

use App\Filament\Imports\ChartOfAccountImporter;
use App\Filament\Resources\ChartOfAccountResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListChartOfAccounts extends ListRecords
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(ChartOfAccountImporter::class)
                ->csvDelimiter(',')
                ->color('success'),

            \Filament\Actions\CreateAction::make(),
        ];
    }
}

