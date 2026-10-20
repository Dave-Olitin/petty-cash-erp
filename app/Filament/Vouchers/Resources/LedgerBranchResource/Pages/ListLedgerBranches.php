<?php

namespace App\Filament\Vouchers\Resources\LedgerBranchResource\Pages;

use App\Filament\Vouchers\Resources\LedgerBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLedgerBranches extends ListRecords
{
    protected static string $resource = LedgerBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
