<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\VoucherStatusNotification;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates all multi-step voucher approval business logic.
 *
 * Previously this logic lived inline inside Filament table action closures,
 * making it untestable, unreusable, and hard to reason about. Centralising
 * here makes it callable from actions, API endpoints, CLI commands, and tests.
 */
class VoucherApprovalService
{
    /**
     * Submit a draft voucher for checking.
     * Returns null on success, or an error string if the state is invalid.
     */
    public function submit(Voucher $voucher, User $actor): ?string
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            if ($locked->status !== VoucherStatus::Draft->value) {
                return 'Voucher status has changed. Please refresh.';
            }

            $locked->update(['status' => VoucherStatus::PendingChecker->value]);
            $locked->load('user');

            try {
                User::role('Accountant')->get()->each->notify(
                    new VoucherStatusNotification($locked, 'submitted')
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Voucher Submit Notification Failed: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Accountant verifies the voucher and forwards it to approvers.
     * Returns null on success, or an error string if the state is invalid.
     */
    public function check(Voucher $voucher, User $actor): ?string
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            if ($locked->status !== VoucherStatus::PendingChecker->value) {
                return 'Voucher status changed by another user. Please refresh.';
            }

            $locked->update([
                'status'                => VoucherStatus::PendingApprover->value,
                'current_approval_step' => 1,
            ]);

            $locked->approvals()->create([
                'user_id' => $actor->id,
                'action'  => 'checked',
            ]);

            $locked->load('user');

            try {
                $firstStep = ApprovalWorkflow::getApproverAtStep(1);
                if ($firstStep) {
                    // Step 1: Action Required
                    $firstStep->user->notify(new VoucherStatusNotification($locked, 'checked'));

                    // Steps 2+: FYI preview — "it's coming your way"
                    $totalSteps = ApprovalWorkflow::totalSteps();
                    for ($i = 2; $i <= $totalSteps; $i++) {
                        $laterStep = ApprovalWorkflow::getApproverAtStep($i);
                        if ($laterStep && $laterStep->user_id !== $firstStep->user_id) {
                            try {
                                $laterStep->user->notify(new VoucherStatusNotification($locked, 'pending_fyi'));
                            } catch (\Throwable $fyi) {
                                \Illuminate\Support\Facades\Log::warning("FYI notify failed for step {$i}: " . $fyi->getMessage());
                            }
                        }
                    }
                } else {
                    User::role('Approver')->get()->each->notify(
                        new VoucherStatusNotification($locked, 'checked')
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Voucher Check Notification Failed: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Approve the current workflow step.
     * Advances to the next step or fully approves if all steps are complete.
     * Returns null on success, or an error string if the state/access is invalid.
     */
    public function approve(Voucher $voucher, User $actor): ?string
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            if ($locked->status !== VoucherStatus::PendingApprover->value
                || $locked->current_approval_step !== $voucher->current_approval_step
            ) {
                return 'Voucher was modified by another user. Please refresh.';
            }

            // Validate that the actor is the correct approver for this step
            if (ApprovalWorkflow::isConfigured()) {
                $step = ApprovalWorkflow::getApproverAtStep((int) ($locked->current_approval_step ?? 1));
                if (!$step || $step->user_id != $actor->id) {
                    return 'Unauthorized: You are not the correct approver for this step.';
                }
            } else {
                if (!$actor->hasRole('Approver')) {
                    return 'Unauthorized: You lack Approver privileges.';
                }
            }

            $locked->load('user');
            $currentStep = (int) ($locked->current_approval_step ?? 1);
            $totalSteps  = ApprovalWorkflow::totalSteps();

            // Record this approval
            $stepLabel = ApprovalWorkflow::getApproverAtStep($currentStep)?->label;
            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'approved',
                'comments' => $stepLabel ? 'Approved as ' . $stepLabel : null,
            ]);

            $nextStep = $currentStep + 1;

            if ($totalSteps > 0 && $nextStep <= $totalSteps) {
                // More steps remaining — advance
                $locked->update(['current_approval_step' => $nextStep]);

                try {
                    $next = ApprovalWorkflow::getApproverAtStep($nextStep);
                    if ($next) {
                        $next->user->notify(new VoucherStatusNotification($locked, 'checked'));
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Voucher Next Step Notification Failed: ' . $e->getMessage());
                }

                return null; // caller can show "forwarded to next approver" message
            }

            // All steps completed — fully approve
            $locked->update([
                'status'                => VoucherStatus::Approved->value,
                'current_approval_step' => null,
            ]);

            try {
                $locked->user?->notify(new VoucherStatusNotification($locked, 'approved'));
                User::role('Accountant')->get()->each->notify(
                    new VoucherStatusNotification($locked, 'approved')
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Voucher Final Approval Notification Failed: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Reject (return) a voucher that is pending checker or approver review.
     * Returns null on success, or an error string if the state is invalid.
     */
    public function reject(Voucher $voucher, User $actor, string $reason): ?string
    {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            $rejectableValues = array_map(fn ($s) => $s->value, VoucherStatus::rejectableStates());
            if (!in_array($locked->status, $rejectableValues)) {
                return 'Voucher status changed by another user. Please refresh.';
            }

            $locked->update(['status' => VoucherStatus::Rejected->value]);
            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'rejected',
                'comments' => $reason,
            ]);

            $locked->load('user');
            try {
                $locked->user?->notify(new VoucherStatusNotification($locked, 'rejected', $reason));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Voucher Reject Notification Failed: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Mark an approved voucher as paid.
     * Returns null on success, or an error string if the state is invalid.
     */
    public function markPaid(Voucher $voucher, User $actor, array $paymentData = [], array $denominationData = []): ?string
    {
        // Capture all data needed for notifications BEFORE the transaction
        // so notification sends happen after the transaction commits (non-blocking).
        $notifyData = null;

        $error = DB::transaction(function () use ($voucher, $actor, $paymentData, $denominationData, &$notifyData) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            $payableStatuses = [
                VoucherStatus::PendingChecker->value,
                VoucherStatus::PendingApprover->value,
                VoucherStatus::Approved->value,
            ];

            if (!in_array($locked->status, $payableStatuses)) {
                return 'Voucher status changed by another user. Please refresh.';
            }

            if (!$actor->can('voucher.pay')) {
                return 'Unauthorized: You lack the required payment permission.';
            }

            // 1. Validate Payments (for Bank/Payment types)
            if (in_array($locked->type, ['payment', 'bank_encashment'])) {
                if (!empty($paymentData)) {
                    $total = collect($paymentData)->sum(fn($p) => (float)($p['amount'] ?? 0));
                    if (abs($total - (float)$locked->amount) > 0.01) {
                        return 'Payment validation failed: The sum of multiple payments (AED ' . number_format($total, 2) . ') must equal the voucher total (AED ' . number_format($locked->amount, 2) . ').';
                    }
                }
            } else {
                // 2. Validate Denominations (for Petty Cash/Receipt types)
                if (!empty($denominationData)) {
                    $tendered = ((int) ($denominationData['bill_1000'] ?? 0) * 1000) 
                        + ((int) ($denominationData['bill_500'] ?? 0) * 500)
                        + ((int) ($denominationData['bill_200'] ?? 0) * 200)
                        + ((int) ($denominationData['bill_100'] ?? 0) * 100)
                        + ((int) ($denominationData['bill_50'] ?? 0) * 50)
                        + ((int) ($denominationData['bill_20'] ?? 0) * 20)
                        + ((int) ($denominationData['bill_10'] ?? 0) * 10)
                        + ((int) ($denominationData['bill_5'] ?? 0) * 5)
                        + ((int) ($denominationData['coin_1'] ?? 0) * 1)
                        + ((int) ($denominationData['coin_0_50'] ?? 0) * 0.50)
                        + ((int) ($denominationData['coin_0_25'] ?? 0) * 0.25);

                    $changeGiven = round((float) ($denominationData['change_given'] ?? 0), 2);
                    $deduction   = round((float) ($denominationData['prior_deduction'] ?? 0), 2);
                    $netPhysical = round($tendered - $changeGiven, 2);
                    $targetWithDeduction = round((float)$locked->amount - $deduction, 2);

                    if (abs($netPhysical - $targetWithDeduction) > 0.01) {
                        return 'Denomination validation failed: Net Physical Cash (AED ' . number_format($netPhysical, 2) . ') must equal the Net target (AED ' . number_format($targetWithDeduction, 2) . ').';
                    }
                }
            }

            $previousStatus = $locked->status;
            
            // Prepare update array
            $updateData = [
                'status'                => VoucherStatus::Paid->value,
                'current_approval_step' => null,
            ];

            // 3. Sync legacy cheque fields and save multiple payments
            if (in_array($locked->type, ['payment', 'bank_encashment']) && !empty($paymentData)) {
                $first = $paymentData[0] ?? null;
                $updateData['multiple_payments'] = $paymentData;
                $updateData['cheque_no'] = $first['cheque_no'] ?? null;
                $updateData['cheque_date'] = $first['cheque_date'] ?? null;
                $updateData['bank'] = $first['bank'] ?? null;
            }

            $locked->update($updateData);

            // 4. Create Denomination Record if present
            if (!in_array($locked->type, ['payment', 'bank_encashment']) && !empty($denominationData)) {
                $tendered = ((int) ($denominationData['bill_1000'] ?? 0) * 1000) 
                        + ((int) ($denominationData['bill_500'] ?? 0) * 500)
                        + ((int) ($denominationData['bill_200'] ?? 0) * 200)
                        + ((int) ($denominationData['bill_100'] ?? 0) * 100)
                        + ((int) ($denominationData['bill_50'] ?? 0) * 50)
                        + ((int) ($denominationData['bill_20'] ?? 0) * 20)
                        + ((int) ($denominationData['bill_10'] ?? 0) * 10)
                        + ((int) ($denominationData['bill_5'] ?? 0) * 5)
                        + ((int) ($denominationData['coin_1'] ?? 0) * 1)
                        + ((int) ($denominationData['coin_0_50'] ?? 0) * 0.50)
                        + ((int) ($denominationData['coin_0_25'] ?? 0) * 0.25);
                $changeGiven = round((float) ($denominationData['change_given'] ?? 0), 2);
                $deduction   = round((float) ($denominationData['prior_deduction'] ?? 0), 2);

                $locked->denominations()->create([
                    'bill_1000'    => $denominationData['bill_1000'] ?: 0,
                    'bill_500'     => $denominationData['bill_500'] ?: 0,
                    'bill_200'     => $denominationData['bill_200'] ?: 0,
                    'bill_100'     => $denominationData['bill_100'] ?: 0,
                    'bill_50'      => $denominationData['bill_50'] ?: 0,
                    'bill_20'      => $denominationData['bill_20'] ?: 0,
                    'bill_10'      => $denominationData['bill_10'] ?: 0,
                    'bill_5'       => $denominationData['bill_5'] ?: 0,
                    'coin_1'       => $denominationData['coin_1'] ?: 0,
                    'coin_0_50'    => $denominationData['coin_0_50'] ?: 0,
                    'coin_0_25'    => $denominationData['coin_0_25'] ?: 0,
                    'total_amount' => $tendered,
                    'change_given' => $changeGiven,
                    'prior_deduction' => $deduction,
                    'is_change_received' => $denominationData['is_change_received'] ?? true,
                    'remarks'      => $denominationData['remarks'] ?? null,
                ]);
            }

            // ── Smart AP Payment Distribution ─────────────────────────────────
            // Distribute the voucher payment across linked purchase entries.
            // Bills are paid in full (oldest first by date). If the voucher
            // amount runs short, the last bill gets a partial payment and
            // the rest retain their unpaid balance.
            $linkedEntries = $locked->purchaseEntries()->orderBy('date')->orderBy('id')->get();

            if ($linkedEntries->isNotEmpty()) {
                $remaining = (float) $locked->amount;
                $totalLinked = $linkedEntries->sum(fn ($pe) => (float) $pe->grand_total);

                foreach ($linkedEntries as $pe) {
                    $billTotal    = (float) $pe->grand_total;
                    $alreadyPaid  = (float) $pe->amount_paid;
                    $stillOwed    = max(0, $billTotal - $alreadyPaid);

                    if ($remaining <= 0) {
                        // No payment left — leave this bill untouched
                        break;
                    }

                    if ($remaining >= $stillOwed) {
                        // Voucher covers this bill fully
                        $pe->update([
                            'amount_paid'    => $billTotal,
                            'balance_due'    => 0,
                            'payment_status' => \App\Models\PurchaseEntry::STATUS_PAID,
                        ]);
                        $remaining -= $stillOwed;
                    } else {
                        // Voucher only partially covers this bill
                        $newPaid = $alreadyPaid + $remaining;
                        $pe->update([
                            'amount_paid'    => round($newPaid, 2),
                            'balance_due'    => round($billTotal - $newPaid, 2),
                            'payment_status' => \App\Models\PurchaseEntry::STATUS_PARTIAL,
                        ]);
                        $remaining = 0;
                    }
                }
            }

            // Record in approval trail
            $comment = $previousStatus !== VoucherStatus::Approved->value
                ? 'Paid early (was ' . ucwords(str_replace('_', ' ', $previousStatus)) . ')'
                : null;

            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'paid',
                'comments' => $comment,
            ]);

            $locked->load('user');

            // Store data for post-transaction notifications (avoids blocking the transaction)
            $notifyData = [
                'voucher'        => $locked,
                'actor'          => $actor,
                'previousStatus' => $previousStatus,
            ];

            return null;
        });

        // ── Notifications sent AFTER transaction commits ──────────────────────
        // This ensures the DB commit completes before any slow email/push sends begin,
        // keeping the HTTP response fast.
        if ($error === null && $notifyData) {
            ['voucher' => $locked, 'actor' => $actor, 'previousStatus' => $previousStatus] = $notifyData;

            try {
                $locked->user?->notify(new VoucherStatusNotification($locked, 'paid'));

                if ($previousStatus !== VoucherStatus::Approved->value) {
                    $approvers = User::role(['Approver', 'Admin', 'Super Admin'])->get();
                    if ($approvers->isNotEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Early Disbursement Alert')
                            ->body("Voucher {$locked->voucher_number} was disbursed early by {$actor->name} before full approval. Please review.")
                            ->warning()
                            ->sendToDatabase($approvers);

                        foreach ($approvers as $approver) {
                            try {
                                $approver->notify(new VoucherStatusNotification($locked, 'early_disbursement'));
                            } catch (\Throwable $notifyEx) {
                                \Illuminate\Support\Facades\Log::warning("Early disbursement notify failed for {$approver->email}: " . $notifyEx->getMessage());
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Voucher Mark Paid Notification Failed: ' . $e->getMessage());
            }
        }

        return $error;
    }

    /**
     * Voids a disbursed (paid) voucher.
     * If $reissue is true, prepares and returns a re-issued draft clone.
     * Returns true on pure void success, the newly created clone Voucher if reissued, or an error string if invalid.
     */
    public function voidVoucher(Voucher $voucher, User $actor, string $reason, bool $reissue = false): Voucher|string|true
    {
        return DB::transaction(function () use ($voucher, $actor, $reason, $reissue) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            if ($locked->status !== VoucherStatus::Paid->value) {
                return 'Only paid/disbursed vouchers can be voided.';
            }

            // The actor needs the specific void permissions
            if ($reissue && !$actor->can('voucher.void_and_reissue')) {
                return 'Unauthorized: You lack the required permission to void and reissue a voucher.';
            }

            if (!$reissue && !$actor->can('voucher.void_only')) {
                return 'Unauthorized: You lack the required permission to void a voucher.';
            }

            // 1. Mark Liquidation as Voided if exists
            if ($locked->liquidation) {
                $locked->liquidation->update(['status' => 'voided']);
            }

            // 2. Revert Purchase Entries (un-pay them so they show full balance again)
            $locked->purchaseEntries()->each(function ($pe) {
                $pe->update([
                    'amount_paid'    => 0,
                    'balance_due'    => $pe->grand_total,   // restore full outstanding balance
                    'payment_status' => \App\Models\PurchaseEntry::STATUS_UNPAID,
                ]);
            });

            // 3. Handle physical denominations if any exist
            // By changing the voucher status to Voided, the dashboard/float calculators 
            // will automatically ignore this voucher's denomination records.

            // 4. Update the current Voucher to Voided
            $locked->update([
                'status' => VoucherStatus::Voided->value,
                'liquidation_status' => 'not_required', 
            ]);

            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'voided',
                'comments' => 'Reason: ' . $reason,
            ]);

            if (!$reissue) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->log("Voucher voided. Reason: {$reason}");
                    
                return true;
            }

            // 5. Clone and Reissue
            // NOTE: Do NOT set voucher_number here.
            // VoucherObserver::creating() fires on ->save() and will correctly generate
            // the number using the proper prefix and padding for the voucher type.
            $newVoucher = $locked->replicate(['voucher_number', 'status', 'liquidation_status', 'parent_voucher_id', 'created_at', 'updated_at', 'deleted_at', 'attachment_paths']);
            $newVoucher->status = VoucherStatus::Draft->value;
            $newVoucher->liquidation_status = 'not_required';
            $newVoucher->parent_voucher_id = $locked->id;
            $newVoucher->save(); // Observer generates voucher_number here

            // 6. Replicate line items
            foreach ($locked->items as $item) {
                $newItem = $item->replicate(['voucher_id']);
                $newVoucher->items()->save($newItem);
            }

            // Log AFTER save so we record the observer-generated number
            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->log("Voucher voided and re-issued as {$newVoucher->voucher_number}. Reason: {$reason}");

            return $newVoucher;
        });
    }
}
