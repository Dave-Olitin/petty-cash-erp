<?php

namespace App\Observers;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class VoucherObserver
{
    /**
     * Handle the Voucher "creating" event.
     */
    public function creating(Voucher $voucher): void
    {
        if (!empty($voucher->voucher_number)) {
            return; // Allow manual overrides for historical data entry
        }

        // Use template prefix if set, otherwise fall back to type-based prefix
        if ($voucher->type === 'petty_cash') {
            $prefix = 'PCV NO: ' . date('y') . '-';
        } elseif ($voucher->type === 'receipt') {
            $prefix = 'RV NO: ';
        } else {
            if ($voucher->voucher_template_id) {
                $template = \App\Models\VoucherTemplate::find($voucher->voucher_template_id);
                $prefix = $template ? $template->prefix . '-' : 'PV NO: ';
            } else {
                $prefix = 'PV NO: ';
            }
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('voucher_number_generation', 5);

        try {
            $lock->block(5, function () use ($voucher, $prefix) {
                $latest = Voucher::withTrashed()
                    ->where('voucher_number', 'like', $prefix . '%')
                    ->orderByRaw('LENGTH(voucher_number) DESC')
                    ->orderBy('voucher_number', 'desc')
                    ->first();

                if ($latest) {
                    $number = intval(substr($latest->voucher_number, strlen($prefix))) + 1;
                } else {
                    if ($voucher->type === 'petty_cash' && str_starts_with($prefix, 'PCV NO: ')) {
                        $number = 1; // Starts at PCV NO: 26-0001
                    } elseif ($voucher->type === 'receipt' && str_starts_with($prefix, 'RV NO: ')) {
                        $number = 776;  // Starts at RV NO: 0776
                    } elseif (str_contains($prefix, 'ETC-')) {
                        $number = 1149; // Starts at PV NO: ETC-1149
                    } elseif (str_contains($prefix, 'SB-')) {
                        $number = 203;  // Starts at PV NO: SB-0203
                    } elseif (str_contains($prefix, 'TG-')) {
                        $number = 560;  // Starts at PV NO: TG-0560
                    } elseif (str_contains($prefix, 'IC-')) {
                        $number = 346;  // Starts at PV NO: IC-0346
                    } else {
                        $number = 1;
                    }
                }

                if ($voucher->type === 'petty_cash' && str_starts_with($prefix, 'PCV NO: ')) {
                    $padLength = 5;
                } else {
                    $padLength = 4;
                }
                
                $voucher->voucher_number = $prefix . str_pad($number, $padLength, '0', STR_PAD_LEFT);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher_template_id' => 'Could not generate a voucher number due to high system load. Please try again in a moment.'
            ]);
        }
    }

    /**
     * Handle the Voucher "created" event.
     */
    public function created(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "updated" event.
     */
    public function updated(Voucher $voucher): void
    {
        if ($voucher->wasChanged('status') && $voucher->status === VoucherStatus::Paid->value) {
            DB::transaction(function () use ($voucher) {
                // ── Petty Cash: low balance check + liquidation trigger ────────────
                if ($voucher->type === 'petty_cash') {

                    // ── 1. Fetch the just-saved denomination once and re-use it ───
                    $denomination = $voucher->denominations()->latest()->first();
                    $priorDeduction = $denomination ? (float) $denomination->prior_deduction : 0.0;

                    // ── 2. Net balance check — pure SQL, no PHP collection loop ───
                    $totalReplenishing = \App\Models\FloatReplenishment::sum('amount');

                    // Single DB aggregation: SUM(total_amount - change_given) per paid petty-cash voucher
                    $totalSpent = \Illuminate\Support\Facades\DB::table('denominations')
                        ->join('vouchers', function ($join) {
                            $join->on('denominations.denominatable_id', '=', 'vouchers.id')
                                 ->where('denominations.denominatable_type', \App\Models\Voucher::class);
                        })
                        ->where('vouchers.type', 'petty_cash')
                        ->where('vouchers.status', VoucherStatus::Paid->value)
                        ->selectRaw('COALESCE(SUM(denominations.total_amount - IF(denominations.is_change_received = 1, denominations.change_given, 0)), 0) as net_spent')
                        ->value('net_spent');

                    // Fallback: if no denomination records exist, use voucher amounts directly
                    if ((float) $totalSpent === 0.0) {
                        $totalSpent = \App\Models\Voucher::where('type', 'petty_cash')
                            ->where('status', VoucherStatus::Paid->value)
                            ->sum('amount');
                    }

                    $currentBalance = (float) $totalReplenishing - (float) $totalSpent;

                    // ── 3. Dispatch low-balance notifications to the queue ─────────
                    if ($currentBalance < 2000) {
                        try {
                            $managers = \App\Models\User::permission('voucher.manage_float')->get();
                            foreach ($managers as $manager) {
                                $manager->notify(
                                    (new \App\Notifications\LowBalanceNotification($currentBalance))
                                        ->onQueue('notifications')
                                );
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Low Balance Notification Failed: ' . $e->getMessage());
                        }
                    }

                    // ── 4. Auto-trigger liquidation ───────────────────────────────
                    $isReimbursement = !is_null($voucher->parent_voucher_id) && str_contains($voucher->description ?? '', 'REIMBURSEMENT FOR LIQUIDATION');

                    if ($isReimbursement) {
                        $voucher->updateQuietly(['liquidation_status' => 'liquidated']);

                        \App\Models\Liquidation::updateOrCreate(
                            ['voucher_id' => $voucher->id],
                            [
                                'liquidated_by'   => $voucher->user_id,
                                'amount_spent'    => 0,
                                'amount_returned' => 0,
                                'prior_deduction' => $voucher->amount,
                                'amount_short'    => 0,
                                'status'          => 'auto_settled',
                                'due_date'        => now()->toDateString(),
                                'liquidated_at'   => now(),
                                'remarks'         => '[Auto-Settled] Reimbursement PCV'
                            ]
                        );
                    } else {
                        $threshold = (float) config('liquidation.minimum_amount', 0);
                        if ((float) $voucher->amount >= $threshold) {

                            if ($voucher->liquidation()->exists()) {
                                $voucher->updateQuietly(['liquidation_status' => 'pending']);
                            } else {
                                $deadlineDays = config('liquidation.deadline_days');
                                $dueDate = $deadlineDays ? now()->addDays((int) $deadlineDays)->toDateString() : null;

                                $netToJustify = max(0, (float) $voucher->amount - $priorDeduction);

                                $voucher->updateQuietly(['liquidation_status' => 'pending']);

                                \App\Models\Liquidation::create([
                                    'voucher_id'      => $voucher->id,
                                    'liquidated_by'   => $voucher->user_id,
                                    'amount_spent'    => 0,
                                    'amount_returned' => 0,
                                    'prior_deduction' => $priorDeduction,
                                    'amount_short'    => $netToJustify,
                                    'status'          => 'pending',
                                    'due_date'        => $dueDate,
                                ]);
                            }
                        }
                    }
                }

                \Illuminate\Support\Facades\Cache::forget('head_office_float_widget_stats');
            });
        }

        // ── Mark overdue liquidations ──────────────────────────────────────────
        if ($voucher->type === 'petty_cash' && $voucher->liquidation_status === 'pending') {
            $liq = $voucher->liquidation;
            if ($liq && $liq->due_date && \Carbon\Carbon::parse($liq->due_date)->isPast() && $liq->status === 'pending') {
                DB::transaction(function () use ($voucher, $liq) {
                    $voucher->updateQuietly(['liquidation_status' => 'overdue']);
                    $liq->updateQuietly(['status' => 'pending']); 
                });
            }
        }
    }

    /**
     * Handle the Voucher "deleted" event.
     */
    public function deleted(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "restored" event.
     */
    public function restored(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "force deleted" event.
     */
    public function forceDeleted(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "saved" event.
     */
    public function saved(Voucher $voucher): void
    {
        $paths = $voucher->attachment_paths ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }

        // Get existing documents in DB
        $existingDocs = \App\Models\Document::where('voucher_id', $voucher->id)->get();
        $existingPaths = $existingDocs->pluck('file_path')->toArray();

        // Find paths to delete (present in DB, but not in current attachment_paths)
        $pathsToDelete = array_diff($existingPaths, $paths);
        if (!empty($pathsToDelete)) {
            \App\Models\Document::where('voucher_id', $voucher->id)
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
                'voucher_id' => $voucher->id,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'uploaded_by' => auth()->id() ?? $voucher->user_id,
            ]);
        }
    }
}
