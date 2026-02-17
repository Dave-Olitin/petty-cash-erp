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
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // 1. Capture Original Data (Before Update)
        $originalData = $record->fresh()->toArray();

        // 2. Extract the "Reason" from the form data (it's not in the DB)
        $reason = $data['edit_reason'] ?? 'No reason provided';
        unset($data['edit_reason']); // Remove it so it doesn't try to save to 'transactions' table

        // 3. Update the Record
        $record->update($data);

        // 4. Create History Log
        \App\Models\TransactionHistory::create([
            'transaction_id' => $record->id,
            'user_id' => auth()->id(),
            'reason' => $reason,
            'original_data' => $originalData,
            'modified_data' => $record->fresh()->toArray(),
        ]);

        return $record;
    }
}
