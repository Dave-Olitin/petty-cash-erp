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
    public function markPaid(Voucher $voucher, User $actor): ?string
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);

            $payableStatuses = [
                VoucherStatus::PendingChecker->value,
                VoucherStatus::PendingApprover->value,
                VoucherStatus::Approved->value,
            ];

            if (!in_array($locked->status, $payableStatuses)) {
                return 'Voucher status changed by another user. Please refresh.';
            }

            // The actor needs the voucher.pay permission
            if (!$actor->can('voucher.pay')) {
                return 'Unauthorized: You lack the required payment permission.';
            }

            $previousStatus = $locked->status;
            $locked->update([
                'status'                => VoucherStatus::Paid->value,
                'current_approval_step' => null,
            ]);

            // Auto-settle linked purchase entries (Option 1 behavior)
            $locked->purchaseEntries()->each(function ($pe) {
                $pe->update([
                    'amount_paid'    => $pe->grand_total,
                    'payment_status' => \App\Models\PurchaseEntry::STATUS_PAID,
                ]);
            });

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
            try {
                $locked->user?->notify(new VoucherStatusNotification($locked, 'paid'));

                // Send early disbursement notification to Approvers if paying before final approval
                if ($previousStatus !== VoucherStatus::Approved->value) {
                    $approvers = User::role(['Approver', 'Admin', 'Super Admin'])->get();
                    if ($approvers->isNotEmpty()) {
                        // In-app Filament notification (for users currently on the platform)
                        \Filament\Notifications\Notification::make()
                            ->title('Early Disbursement Alert')
                            ->body("Voucher {$locked->voucher_number} was disbursed early by {$actor->name} before full approval. Please review.")
                            ->warning()
                            ->sendToDatabase($approvers);

                        // Email/push notification (for users NOT currently on the platform)
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

            return null;
        });
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

            // 2. Revert Purchase Entries (un-pay them so they show balance again)
            $locked->purchaseEntries()->each(function ($pe) {
                $pe->update([
                    'amount_paid'    => 0,
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
