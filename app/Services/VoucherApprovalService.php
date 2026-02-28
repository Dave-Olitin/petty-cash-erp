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

            User::role('Accountant')->get()->each->notify(
                new VoucherStatusNotification($locked, 'submitted')
            );

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

            $firstStep = ApprovalWorkflow::getApproverAtStep(1);
            if ($firstStep) {
                $firstStep->user->notify(new VoucherStatusNotification($locked, 'checked'));
            } else {
                User::role('Approver')->get()->each->notify(
                    new VoucherStatusNotification($locked, 'checked')
                );
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

                $next = ApprovalWorkflow::getApproverAtStep($nextStep);
                if ($next) {
                    $next->user->notify(new VoucherStatusNotification($locked, 'checked'));
                }

                return null; // caller can show "forwarded to next approver" message
            }

            // All steps completed — fully approve
            $locked->update([
                'status'                => VoucherStatus::Approved->value,
                'current_approval_step' => null,
            ]);

            $locked->user->notify(new VoucherStatusNotification($locked, 'approved'));
            User::role('Accountant')->get()->each->notify(
                new VoucherStatusNotification($locked, 'approved')
            );

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
            $locked->user->notify(new VoucherStatusNotification($locked, 'rejected', $reason));

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
            $locked->user->notify(new VoucherStatusNotification($locked, 'paid'));

            return null;
        });
    }
}
