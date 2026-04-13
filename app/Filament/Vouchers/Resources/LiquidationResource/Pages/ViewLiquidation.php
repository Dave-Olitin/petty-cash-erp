<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewLiquidation extends ViewRecord
{
    protected static string $resource = LiquidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(function () {
                    $record = $this->getRecord();
                    // Always allow editing pending/short (unresolved)
                    if (in_array($record->status, ['pending', 'short'])) {
                        return true;
                    }
                    // Allow settled edits if the user has the override permission
                    return auth()->user()->can('liquidation.edit_settled');
                }),
        ];
    }
}
