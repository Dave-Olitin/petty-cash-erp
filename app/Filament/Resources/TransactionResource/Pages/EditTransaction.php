<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Void')
                ->visible(fn () => auth()->user()->branch_id === null), // HQ only
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // 1. Capture Original Data (Before Update)
        $originalData = $record->fresh()->toArray();

        // 2. Extract the "Reason" (not a DB column)
        $reason = $data['edit_reason'] ?? 'No reason provided';
        unset($data['edit_reason']);

        // 3. Extract created_at — bypasses fillable guard by applying via raw query
        $customDate = $data['created_at'] ?? null;
        unset($data['created_at']);

        // 4. Update the Record (mass-assignable fields only)
        $record->update($data);

        // 5. Apply custom date directly if provided
        if ($customDate) {
            $record->newQueryWithoutScopes()
                ->where('id', $record->id)
                ->update(['created_at' => $customDate]);
        }

        // 6. Create History Log
        \App\Models\TransactionHistory::create([
            'transaction_id' => $record->id,
            'user_id'        => auth()->id(),
            'reason'         => $reason,
            'original_data'  => $originalData,
            'modified_data'  => $record->fresh()->toArray(),
        ]);

        return $record;
    }
}
