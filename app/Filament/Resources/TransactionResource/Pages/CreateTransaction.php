<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force branch for branch users
        if (auth()->user()->branch_id) {
            $data['branch_id'] = auth()->user()->branch_id;
        }

        // Store custom date aside so it doesn't hit fillable guard
        // It is applied in afterCreate() below.
        session()->put('_tx_custom_date', $data['created_at'] ?? null);
        unset($data['created_at']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $customDate = session()->pull('_tx_custom_date');

        if ($customDate) {
            // Bypass fillable by updating directly on the query builder
            $this->record->newQueryWithoutScopes()
                ->where('id', $this->record->id)
                ->update(['created_at' => $customDate]);
        }
    }
}

