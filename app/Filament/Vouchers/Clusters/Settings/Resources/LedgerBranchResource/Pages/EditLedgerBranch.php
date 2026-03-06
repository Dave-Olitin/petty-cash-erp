<?php

namespace App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource\Pages;

use App\Filament\Vouchers\Clusters\Settings\Resources\LedgerBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLedgerBranch extends EditRecord
{
    protected static string $resource = LedgerBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
