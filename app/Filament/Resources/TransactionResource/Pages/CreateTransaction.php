<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    // Fix #4: Store custom date as a class property instead of session.
    // Session storage is shared across Livewire components for the same user —
    // submitting two tabs simultaneously would cause Tab B's date to overwrite Tab A's.
    // A class property is per-component instance and inherently isolated.
    protected ?string $customDate = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always set user_id server-side — never trust the form field.
        $data['user_id'] = auth()->id();

        // Force branch for branch users
        if (auth()->user()->branch_id) {
            $data['branch_id'] = auth()->user()->branch_id;
        }

        // Stash custom date on the instance, not the session.
        $this->customDate = $data['created_at'] ?? null;
        unset($data['created_at']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->customDate) {
            // Bypass fillable by updating directly on the query builder
            $this->record->newQueryWithoutScopes()
                ->where('id', $this->record->id)
                ->update(['created_at' => $this->customDate]);
        }
    }
}

