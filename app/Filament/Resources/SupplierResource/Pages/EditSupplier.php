<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Supplier $record, Actions\DeleteAction $action) {
                    if ($record->transactions()->exists()) {
                        Notification::make()
                            ->title('Cannot Delete Supplier')
                            ->body("'{$record->name}' is linked to {$record->transactions()->count()} transaction(s). Please deactivate it instead.")
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
