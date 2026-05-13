<?php

namespace App\Filament\Vouchers\Resources\AccountCodeResource\Pages;

use App\Filament\Vouchers\Resources\AccountCodeResource;
use App\Filament\Vouchers\Resources\AccountCodeResource\Widgets\AccountLedgerTableWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountCode extends ViewRecord
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccountLedgerTableWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
