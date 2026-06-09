<?php

namespace App\Services;

use App\Models\Liquidation;
use App\Models\Voucher;
use Filament\Notifications\Notification;

class LiquidationService
{
    /**
     * Auto-generates or updates linked Receipt Vouchers or Reimbursement Payment Vouchers
     * based on the liquidation's returned or short amounts.
     *
     * @param Liquidation $record
     * @param bool $notifyUser
     */
    public function handleAutoGeneration(Liquidation $record, bool $notifyUser = true): void
    {
        $voucher = $record->voucher;
        if (!$voucher) {
            return;
        }

        $returned  = (float) $record->amount_returned;
        $spent     = (float) $record->amount_spent;
        $original  = (float) ($voucher->amount ?? 0);
        $deduction = (float) ($record->prior_deduction ?? 0);
        $netTarget = max(0, $original - $deduction);
        $variance  = round(($spent + $returned) - $netTarget, 2);

        // ── 1. Handle Receipt Voucher (Excess) ───────────────────────────────────
        $existingRv = \App\Models\Voucher::where('type', 'receipt')
            ->where(function($q) use ($voucher) {
                $q->where('parent_voucher_id', $voucher->id)
                  ->orWhere('description', 'like', "%{$voucher->voucher_number}%");
            })
            ->withTrashed()
            ->first();

        if ($returned > 0 && in_array($record->status, ['complete', 'excess'])) {
            if ($existingRv && $existingRv->status === 'paid') {
                $diff = abs($returned - (float) $existingRv->amount);
                if ($diff > 0.01 && $notifyUser) {
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
            } elseif ($existingRv && in_array($existingRv->status, ['draft', 'pending_checker', 'pending_approver'])) {
                $breakdown = $this->buildRvBreakdown($voucher, $original, $spent, $returned, $variance, $record);

                $existingRv->updateQuietly([
                    'amount'              => $returned,
                    'transaction_summary' => $breakdown,
                    'parent_voucher_id'   => $voucher->id,
                ]);

                $existingRv->items()->update(['amount' => $returned]);

                $causer = auth()->user() ?? \App\Models\User::first();
                activity()
                    ->performedOn($existingRv)
                    ->causedBy($causer)
                    ->event('updated')
                    ->log("Auto-RV amount updated to AED " . number_format($returned, 2) . " due to settlement edit of {$voucher->voucher_number}.");

                if ($notifyUser) {
                    Notification::make()
                        ->title('Receipt Voucher Updated')
                        ->body("The linked receipt voucher ({$existingRv->voucher_number}) was automatically updated to AED " . number_format($returned, 2) . " to match the revised settlement.")
                        ->warning()
                        ->persistent()
                        ->send();
                }
            } elseif (!$existingRv) {
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
                    'parent_voucher_id'   => $voucher->id,
                ]);

                $rv->items()->create([
                    'entry_type'  => 'credit',
                    'amount'      => $returned,
                    'description' => "Cash Returned from Liquidation of {$voucher->voucher_number}",
                    'category_id' => $category->id,
                ]);

                if ($notifyUser) {
                    Notification::make()
                        ->title('Receipt Voucher Auto-Generated')
                        ->body("RV drafted for AED " . number_format($returned, 2) . " (Return from {$voucher->voucher_number}). Submit it to add the cash back into the float.")
                        ->success()
                        ->persistent()
                        ->send();
                }
            }
        } elseif ($returned == 0 && $existingRv && in_array($existingRv->status, ['draft', 'pending_checker'])) {
            $existingRv->delete();
            $causer = auth()->user() ?? \App\Models\User::first();
            activity()
                ->performedOn($voucher)
                ->causedBy($causer)
                ->event('updated')
                ->log("Auto-RV {$existingRv->voucher_number} voided because amount_returned was set to 0.");

            if ($notifyUser) {
                Notification::make()
                    ->title('Draft Receipt Voucher Voided')
                    ->body("The pending RV ({$existingRv->voucher_number}) was automatically cancelled because the returned cash amount is now AED 0.00.")
                    ->warning()
                    ->send();
            }
        }

        // ── 2. Handle Reimbursement PCV (Shortage) ───────────────────────────────
        $shortage = (float) $record->amount_short;

        $existingPcv = \App\Models\Voucher::where('type', 'petty_cash')
            ->where(function($q) use ($voucher) {
                $q->where('parent_voucher_id', $voucher->id)
                  ->orWhere('description', 'like', "%REIMBURSEMENT FOR LIQUIDATION OF {$voucher->voucher_number}%");
            })
            ->withTrashed()
            ->first();

        if ($shortage > 0 && $record->status === 'short') {
            if ($existingPcv && $existingPcv->status === 'paid') {
                $diff = abs($shortage - (float) $existingPcv->amount);
                if ($diff > 0.01 && $notifyUser) {
                    Notification::make()
                        ->title('⚠️ Reimbursement Update Required')
                        ->body("The shortage amount changed, but the linked Reimbursement PCV ({$existingPcv->voucher_number}) was already Paid. Please adjust manually.")
                        ->danger()
                        ->persistent()
                        ->send();
                }
            } elseif ($existingPcv && in_array($existingPcv->status, ['draft', 'pending_checker', 'pending_approver'])) {
                $breakdown = $this->buildPcvBreakdown($voucher, $original, $spent, $shortage, $variance, $record);

                $existingPcv->updateQuietly([
                    'amount'              => $shortage,
                    'transaction_summary' => $breakdown,
                    'parent_voucher_id'   => $voucher->id,
                ]);

                $existingPcv->items()->update(['amount' => $shortage]);

                $causer = auth()->user() ?? \App\Models\User::first();
                activity()
                    ->performedOn($existingPcv)
                    ->causedBy($causer)
                    ->event('updated')
                    ->log("Auto-PCV amount updated to AED " . number_format($shortage, 2) . " due to settlement edit of {$voucher->voucher_number}.");

                if ($notifyUser) {
                    Notification::make()
                        ->title('Reimbursement PCV Updated')
                        ->body("The linked reimbursement PCV ({$existingPcv->voucher_number}) was automatically updated to AED " . number_format($shortage, 2) . ".")
                        ->warning()
                        ->persistent()
                        ->send();
                }
            } elseif (!$existingPcv) {
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => 'Liquidation Reimbursement'],
                    ['type' => 'petty_cash']
                );

                $breakdown = $this->buildPcvBreakdown($voucher, $original, $spent, $shortage, $variance, $record);

                $pcv = \App\Models\Voucher::create([
                    'type'                => 'petty_cash',
                    'status'              => 'draft',
                    'amount'              => $shortage,
                    'payee'               => $voucher->payee,
                    'description'         => "REIMBURSEMENT FOR LIQUIDATION OF {$voucher->voucher_number}",
                    'transaction_summary' => $breakdown,
                    'user_id'             => $voucher->user_id,
                    'category_id'         => $category->id,
                    'department'          => $voucher->department,
                    'parent_voucher_id'   => $voucher->id,
                ]);

                $pcv->items()->create([
                    'entry_type'  => 'debit',
                    'amount'      => $shortage,
                    'description' => "Reimbursement for Liquidation Shortage of {$voucher->voucher_number}",
                    'category_id' => $category->id,
                ]);

                if ($notifyUser) {
                    Notification::make()
                        ->title('Reimbursement PCV Auto-Generated')
                        ->body("PCV drafted for AED " . number_format($shortage, 2) . " (Reimbursement for {$voucher->voucher_number}).")
                        ->success()
                        ->persistent()
                        ->send();
                }
            }
        } elseif ($shortage == 0 && $existingPcv && in_array($existingPcv->status, ['draft', 'pending_checker'])) {
            $existingPcv->delete();
            $causer = auth()->user() ?? \App\Models\User::first();
            activity()
                ->performedOn($voucher)
                ->causedBy($causer)
                ->event('updated')
                ->log("Auto-PCV {$existingPcv->voucher_number} voided because amount_short was set to 0.");

            if ($notifyUser) {
                Notification::make()
                    ->title('Draft Reimbursement PCV Voided')
                    ->body("The pending Reimbursement PCV ({$existingPcv->voucher_number}) was automatically cancelled because the shortage is now AED 0.00.")
                    ->warning()
                    ->send();
            }
        }
    }

    private function buildRvBreakdown(Voucher $voucher, float $original, float $spent, float $returned, float $variance, Liquidation $record): string
    {
        $deduction = (float) ($record->prior_deduction ?? 0);
        $netTarget = max(0, $original - $deduction);

        $lines = [
            "AUTO-GENERATED - Cash Return from Liquidation",
            "Reference PCV: {$voucher->voucher_number}",
            "Payee/Employee: {$voucher->payee}",
            "---------------------------------",
            "Original Advance           : AED " . number_format($original, 2),
        ];

        if ($deduction > 0) {
            $lines[] = "Less: Cash Advance/Deduction: AED " . number_format($deduction, 2);
            $lines[] = "Net Receipts Required      : AED " . number_format($netTarget, 2);
        }

        $lines[] = "Amount Spent (w/ receipts) : AED " . number_format($spent, 2);
        $lines[] = "Cash Returned to Box       : AED " . number_format($returned, 2);
        $lines[] = "Variance                   : " . ($variance > 0 ? '+' : '') . "AED " . number_format($variance, 2);
        $lines[] = "Liquidation Status         : " . ucfirst($record->status);
        if ($record->remarks) $lines[] = "Remarks: {$record->remarks}";

        return implode("\n", $lines);
    }

    private function buildPcvBreakdown(Voucher $voucher, float $original, float $spent, float $shortage, float $variance, Liquidation $record): string
    {
        $deduction = (float) ($record->prior_deduction ?? 0);
        $netTarget = max(0, $original - $deduction);

        $lines = [
            "AUTO-GENERATED - Reimbursement for Liquidation Shortage",
            "Reference PCV: {$voucher->voucher_number}",
            "Payee/Employee: {$voucher->payee}",
            "---------------------------------",
            "Original Advance           : AED " . number_format($original, 2),
        ];

        if ($deduction > 0) {
            $lines[] = "Less: Cash Advance/Deduction: AED " . number_format($deduction, 2);
            $lines[] = "Net Advance Accounted      : AED " . number_format($netTarget, 2);
        }

        $lines[] = "Amount Spent (w/ receipts) : AED " . number_format($spent, 2);
        $lines[] = "Reimbursement Due to Payee : AED " . number_format($shortage, 2);
        $lines[] = "Variance                   : " . ($variance > 0 ? '+' : '') . "AED " . number_format($variance, 2);
        $lines[] = "Liquidation Status         : " . ucfirst($record->status);
        if ($record->remarks) $lines[] = "Remarks: {$record->remarks}";

        return implode("\n", $lines);
    }
}
