<?php

namespace App\Observers;

use App\Models\FloatReplenishment;

class FloatReplenishmentObserver
{
    /**
     * When a Float Replenishment is created, automatically create a
     * corresponding Denomination record so it appears in the
     * "Recent Cash Breakdown Logs" widget.
     */
    public function created(FloatReplenishment $replenishment): void
    {
        // Only create if no denomination already exists (avoids double-entry)
        if (! $replenishment->denominations()->exists()) {
            $replenishment->denominations()->create([
                'total_amount'       => $replenishment->amount,
                'change_given'       => 0,
                'is_change_received' => true,
                'remarks'            => $replenishment->remarks ?? 'Float Replenishment',
                'bill_1000' => 0, 'bill_500' => 0, 'bill_200' => 0, 'bill_100' => 0,
                'bill_50'   => 0, 'bill_20'  => 0, 'bill_10'  => 0, 'bill_5'   => 0,
                'coin_1'    => 0, 'coin_0_50' => 0, 'coin_0_25' => 0,
            ]);
        }
    }

    /**
     * Sync attachments to documents table on saved.
     */
    public function saved(FloatReplenishment $replenishment): void
    {
        $paths = $replenishment->attachment_paths ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }

        // Get existing documents in DB for this float replenishment
        $existingDocs = \App\Models\Document::where('float_replenishment_id', $replenishment->id)->get();
        $existingPaths = $existingDocs->pluck('file_path')->toArray();

        // Find paths to delete (present in DB, but not in current attachment_paths)
        $pathsToDelete = array_diff($existingPaths, $paths);
        if (!empty($pathsToDelete)) {
            \App\Models\Document::where('float_replenishment_id', $replenishment->id)
                ->whereIn('file_path', $pathsToDelete)
                ->delete();
        }

        // Find paths to add (present in current attachment_paths, but not in DB)
        $pathsToAdd = array_diff($paths, $existingPaths);
        foreach ($pathsToAdd as $path) {
            if (empty($path)) continue;

            $fileName = basename($path);
            $fileType = pathinfo($path, PATHINFO_EXTENSION);

            \App\Models\Document::create([
                'float_replenishment_id' => $replenishment->id,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'uploaded_by' => auth()->id() ?? $replenishment->created_by,
            ]);
        }

        // Ensure this Fund Voucher (Float Replenishment) is connected to Bank Reconciliation
        $this->syncWithVouchers($replenishment);
    }

    /**
     * Connect the float replenishment to Bank Reconciliation:
     * 1. If a payment voucher is linked, sync bank & cheque_no.
     * 2. If no voucher is linked, but a bank account is specified, auto-create a paid bank_encashment voucher.
     */
    public function syncWithVouchers(FloatReplenishment $replenishment): void
    {
        if ($replenishment->voucher_id) {
            $voucher = \App\Models\Voucher::find($replenishment->voucher_id);
            if ($voucher) {
                $updates = [];
                if ($replenishment->bank_reference && $voucher->cheque_no !== $replenishment->bank_reference) {
                    $updates['cheque_no'] = $replenishment->bank_reference;
                }
                if ($replenishment->account_code && $voucher->bank !== $replenishment->account_code) {
                    $updates['bank'] = $replenishment->account_code;
                }
                if (!empty($updates)) {
                    $voucher->update($updates);
                }
            }
        } elseif ($replenishment->account_code || $replenishment->bank_reference) {
            $voucher = \App\Models\Voucher::where('voucher_number', $replenishment->reference)->first();
            if (!$voucher) {
                $voucher = \App\Models\Voucher::create([
                    'voucher_number'        => $replenishment->reference,
                    'type'                  => 'bank_encashment',
                    'date'                  => $replenishment->date ?? now(),
                    'amount'                => $replenishment->amount,
                    'bank'                  => $replenishment->account_code,
                    'cheque_no'             => $replenishment->bank_reference,
                    'payee'                 => 'Petty Cash Float Replenishment',
                    'description'           => $replenishment->remarks ?: ('Float Replenishment ' . $replenishment->reference),
                    'status'                => 'paid',
                    'user_id'               => $replenishment->created_by ?? 1,
                    'current_approval_step' => 0,
                ]);
            } else {
                $voucher->update([
                    'amount'      => $replenishment->amount,
                    'bank'        => $replenishment->account_code,
                    'cheque_no'   => $replenishment->bank_reference,
                    'date'        => $replenishment->date ?? now(),
                    'description' => $replenishment->remarks ?: ('Float Replenishment ' . $replenishment->reference),
                ]);
            }

            if ($replenishment->voucher_id !== $voucher->id) {
                \Illuminate\Support\Facades\DB::table('float_replenishments')
                    ->where('id', $replenishment->id)
                    ->update(['voucher_id' => $voucher->id]);
                $replenishment->voucher_id = $voucher->id;
            }
        }
    }
}
