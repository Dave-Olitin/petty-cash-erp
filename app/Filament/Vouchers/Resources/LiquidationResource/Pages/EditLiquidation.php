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

        $original = (float) $voucher->amount;
        $spent    = (float) ($data['amount_spent'] ?? 0);
        $returned = (float) ($data['amount_returned'] ?? 0);
        $diff = round(($spent + $returned) - $original, 2);

        $data['amount_short'] = max(0, -$diff);

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

        // ── Auto-generate OR update the linked Receipt Voucher ──────────────────
        $returned = (float) $record->amount_returned;
        $spent    = (float) $record->amount_spent;
        $original = (float) ($voucher->amount ?? 0);
        $variance = round(($spent + $returned) - $original, 2);

        // Find any existing linked RV (including soft-deleted, to prevent duplicates)
        $existingRv = \App\Models\Voucher::where('type', 'receipt')
            ->where('description', 'like', "%{$voucher->voucher_number}%")
            ->withTrashed()
            ->first();

        if ($returned > 0 && in_array($record->status, ['complete', 'excess'])) {

            // CONFLICT FIX #2: If the RV is already Paid, we CANNOT auto-update it.
            // Warn the user — manual correction via a new Receipt Voucher is required.
            if ($existingRv && $existingRv->status === 'paid') {
                $diff = abs($returned - (float) $existingRv->amount);
                if ($diff > 0.01) {
                    Notification::make()
                        ->title('⚠️ Float Reconciliation Required')
                        ->body(
                            "The cash return amount changed, but the linked Receipt Voucher ({$existingRv->voucher_number}) was already COLLECTED (Paid). " .
                            "The float may now be out of sync by AED " . number_format($diff, 2) .
                            ". Please manually create a correcting Receipt or Payment Voucher to adjust."
                        )
                        ->danger()
                        ->persistent()
                        ->send();
                }
                return; // No further auto-changes — accountant must reconcile manually
            }

            // CONFLICT FIX #1: If the RV exists in draft/submitted, update its amount.
            if ($existingRv && in_array($existingRv->status, ['draft', 'pending_checker', 'pending_approver'])) {
                $breakdown = $this->buildRvBreakdown($voucher, $original, $spent, $returned, $variance, $record);

                $existingRv->updateQuietly([
                    'amount'              => $returned,
                    'transaction_summary' => $breakdown,
                ]);

                // Update the linked ledger item amount too
                $existingRv->items()->update(['amount' => $returned]);

                activity()
                    ->performedOn($existingRv)
                    ->causedBy(auth()->user())
                    ->event('updated')
                    ->log("Auto-RV amount updated to AED " . number_format($returned, 2) . " due to settlement edit of {$voucher->voucher_number}.");

                Notification::make()
                    ->title('Receipt Voucher Updated')
                    ->body("The linked receipt voucher ({$existingRv->voucher_number}) was automatically updated to AED " . number_format($returned, 2) . " to match the revised settlement.")
                    ->warning()
                    ->persistent()
                    ->send();

                return;
            }

            // No existing RV — create fresh
            if (!$existingRv) {
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => 'Liquidation Cash Return'],
                    ['type' => 'receipt']
                );

                $breakdown = $this->buildRvBreakdown($voucher, $original, $spent, $returned, $variance, $record);

                $rv = \App\Models\Voucher::create([
                    'type'                => 'receipt',
                    'status'              => 'draft',
                    'amount'              => $returned,
                    'payee'               => $voucher->payee,
                    'description'         => "REVERSING ENTRY - CASH RETURN FROM LIQUIDATION OF {$voucher->voucher_number}",
                    'transaction_summary' => $breakdown,
                    'user_id'             => $voucher->user_id,
                    'category_id'         => $category->id,
                    'department'          => $voucher->department,
                ]);

                $rv->items()->create([
                    'entry_type'  => 'credit',
                    'amount'      => $returned,
                    'description' => "Cash Returned from Liquidation of {$voucher->voucher_number}",
                    'category_id' => $category->id,
                ]);

                Notification::make()
                    ->title('Receipt Voucher Auto-Generated')
                    ->body("RV drafted for AED " . number_format($returned, 2) . " (Return from {$voucher->voucher_number}). Submit it to add the cash back into the float.")
                    ->success()
                    ->persistent()
                    ->send();
            }

        } elseif ($returned == 0 && $existingRv && in_array($existingRv->status, ['draft', 'pending_checker'])) {
            // Amount returned was removed — void the draft RV automatically
            $existingRv->delete(); // soft delete
            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->event('updated')
                ->log("Auto-RV {$existingRv->voucher_number} voided because amount_returned was set to 0.");

            Notification::make()
                ->title('Draft Receipt Voucher Voided')
                ->body("The pending RV ({$existingRv->voucher_number}) was automatically cancelled because the returned cash amount is now AED 0.00.")
                ->warning()
                ->send();
        }
    }

    /**
     * Build the detailed breakdown text for the auto-generated RV's remarks field.
     */
    private function buildRvBreakdown(\App\Models\Voucher $voucher, float $original, float $spent, float $returned, float $variance, \App\Models\Liquidation $record): string
    {
        return implode("\n", [
            "AUTO-GENERATED - Cash Return from Liquidation",
            "Reference PCV: {$voucher->voucher_number}",
            "Payee/Employee: {$voucher->payee}",
            "---------------------------------",
            "Original Advance           : AED " . number_format($original, 2),
            "Amount Spent (w/ receipts) : AED " . number_format($spent, 2),
            "Cash Returned to Box       : AED " . number_format($returned, 2),
            "Variance                   : " . ($variance > 0 ? '+' : '') . "AED " . number_format($variance, 2),
            "Liquidation Status         : " . ucfirst($record->status),
            ($record->remarks ? "Remarks: {$record->remarks}" : ''),
        ]);
    }
}
