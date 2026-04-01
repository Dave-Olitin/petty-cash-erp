<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditLiquidation extends EditRecord
{
    protected static string $resource = LiquidationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate placeholder read-only fields for the live summary
        $voucher = $this->getRecord()?->voucher;
        if ($voucher) {
            $data['_voucher_amount'] = $voucher->amount;
            $data['_voucher_payee']  = $voucher->payee;
            $data['_voucher_number'] = $voucher->voucher_number;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['_voucher_amount'], $data['_voucher_payee'], $data['_voucher_number']);

        $voucher = $this->getRecord()?->voucher;
        if (!$voucher) return $data;

        $original = (float) $voucher->amount;
        $spent    = (float) ($data['amount_spent'] ?? 0);
        $returned = (float) ($data['amount_returned'] ?? 0);
        $diff = round(($spent + $returned) - $original, 2);

        $data['amount_short'] = max(0, -$diff);
        $data['liquidated_at'] = now();

        $data['status'] = match (true) {
            abs($diff) <= 0.01 => 'complete',
            $diff < 0          => 'short',
            default            => 'excess',
        };

        return $data;
    }

    protected function afterSave(): void
    {
        $record  = $this->getRecord();
        $voucher = $record->voucher;
        if (!$voucher) return;

        $newLiqStatus = match ($record->status) {
            'complete', 'excess' => 'liquidated',
            default              => 'pending',
        };
        $voucher->updateQuietly(['liquidation_status' => $newLiqStatus]);

        // Auto-log change received on the physical denominations tracker
        // If a liquidation is filed or updated, it means the cashier/accountant has verified exactly what was received.
        $denom = $voucher->denominations()->first();
        if ($denom && !$denom->is_change_received) {
            $denom->updateQuietly(['is_change_received' => true]);
        }

        activity()
            ->performedOn($voucher)
            ->causedBy(auth()->user())
            ->event('updated')
            ->log("Liquidation updated. Status: {$record->status}. Spent: AED " . number_format($record->amount_spent, 2) . " | Returned: AED " . number_format($record->amount_returned, 2));
    }
}
