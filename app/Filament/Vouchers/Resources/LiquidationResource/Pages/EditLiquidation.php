<?php

namespace App\Filament\Vouchers\Resources\LiquidationResource\Pages;

use App\Filament\Vouchers\Resources\LiquidationResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditLiquidation extends EditRecord
{
    protected static string $resource = LiquidationResource::class;

    /**
     * Capture the previous liquidation status BEFORE the save overwrites it.
     * Used in afterSave() to correctly detect if this was a settled-record override.
     */
    protected ?string $_previousLiqStatus = null;

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
        // Ensure prior_deduction is visible in the form from the saved record
        $data['prior_deduction'] = $this->getRecord()->prior_deduction ?? 0;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['_voucher_amount'], $data['_voucher_payee'], $data['_voucher_number']);

        // Capture the CURRENT (pre-save) status before it gets overwritten
        // getOriginal() is unreliable after save fires, so we store it here.
        $this->_previousLiqStatus = $this->getRecord()->status;

        $voucher = $this->getRecord()?->voucher;
        if (!$voucher) return $data;

        $original  = (float) $voucher->amount;
        $deduction = (float) ($this->getRecord()->prior_deduction ?? $data['prior_deduction'] ?? 0);
        $netTarget = max(0, $original - $deduction);
        $spent     = (float) ($data['amount_spent'] ?? 0);
        $returned  = (float) ($data['amount_returned'] ?? 0);
        $diff      = round(($spent + $returned) - $netTarget, 2);

        $data['amount_short']    = max(0, -$diff);
        $data['prior_deduction'] = $deduction;

        // Only stamp the time when the liquidation is first settled, not on subsequent edits
        if (empty($this->getRecord()->liquidated_at)) {
            $data['liquidated_at'] = now();
        }

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

        // CONFLICT FIX #3: Any settled status (complete, excess, short) should mark
        // the PCV as liquidated. Previously 'short' left it as 'pending', which
        // re-queued the employee in the pending list despite a settlement record existing.
        $newLiqStatus = in_array($record->status, ['complete', 'excess', 'short'])
            ? 'liquidated'
            : 'pending';
        $voucher->updateQuietly(['liquidation_status' => $newLiqStatus]);

        // Auto-log change received on the physical denominations tracker
        $denom = $voucher->denominations()->first();
        if ($denom && !$denom->is_change_received) {
            $denom->updateQuietly(['is_change_received' => true]);
        }

        activity()
            ->performedOn($voucher)
            ->causedBy(auth()->user())
            ->event('updated')
            ->log("Liquidation updated. Status: {$record->status}. Spent: AED " . number_format($record->amount_spent, 2) . " | Returned: AED " . number_format($record->amount_returned, 2));

        // Bust the float widget cache so the dashboard reflects the settlement immediately
        \Illuminate\Support\Facades\Cache::forget('head_office_float_widget_stats');

        // Log override if a privileged user edited an already-settled liquidation
        // Uses _previousLiqStatus captured in mutateFormDataBeforeSave to avoid getOriginal() timing issue.
        if ($this->_previousLiqStatus !== null
            && in_array($this->_previousLiqStatus, ['complete', 'excess', 'short'])
            && auth()->user()->can('liquidation.edit_settled')) {
            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->event('updated')
                ->log("⚠️ SETTLED LIQUIDATION OVERRIDDEN by " . auth()->user()->name . ". Previous status: {$this->_previousLiqStatus} → New status: {$record->status}.");
        }

        // ── Auto-generate OR update the linked Child Vouchers ──────────────────
        app(\App\Services\LiquidationService::class)->handleAutoGeneration($record, true);
    }
}
