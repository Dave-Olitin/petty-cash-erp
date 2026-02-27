<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Notifications\VoucherStatusNotification;
use Illuminate\Support\Facades\DB;

/**
 * Handles all voucher approval workflow transitions.
 *
 * Extracted from inline Filament table action closures to make the
 * business logic testable, reusable, and easy to follow.
 *
 * Each method is responsible for one state transition:
 *   submit()    → draft → pending_checker
 *   check()     → pending_checker → pending_approver
 *   approve()   → pending_approver → approved (or next step)
 *   reject()    → pending_checker|pending_approver → rejected
 *   markPaid()  → approved → paid
 */
class VoucherApprovalService
{
    /**
     * Submit a draft voucher for checking.
     *
     * @throws \RuntimeException if the voucher is no longer in draft status.
     */
    public function submit(Voucher $voucher, User $actor): void
    {
        DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->findOrFail($voucher->id);

            if ($locked->status !== VoucherStatus::Draft) {
                throw new \RuntimeException('Voucher is no longer in draft status.');
            }

            $locked->update(['status' => VoucherStatus::PendingChecker->value]);
            $locked->load('user');

            // Notify all Accountant-role users (checkers)
            User::role('Accountant')->get()
                ->each->notify(new VoucherStatusNotification($locked, 'submitted'));
        });
    }

    /**
     * Verify and forward a voucher from checker to approver.
     *
     * @throws \RuntimeException if the voucher is not pending checking.
     */
    public function check(Voucher $voucher, User $actor): void
    {
        DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->findOrFail($voucher->id);

            if ($locked->status !== VoucherStatus::PendingChecker) {
                throw new \RuntimeException('Voucher status changed by another user.');
            }

            $locked->update([
                'status'               => VoucherStatus::PendingApprover->value,
                'current_approval_step' => 1,
            ]);

            $locked->approvals()->create([
                'user_id' => $actor->id,
                'action'  => 'checked',
            ]);

            $locked->load('user');

            // Notify the first approver in the workflow chain, or all Approvers as fallback
            $firstStep = ApprovalWorkflow::getApproverAtStep(1);
            if ($firstStep) {
                $firstStep->user->notify(new VoucherStatusNotification($locked, 'checked'));
            } else {
                User::role('Approver')->get()->each->notify(new VoucherStatusNotification($locked, 'checked'));
            }
        });
    }

    /**
     * Approve the current step of a voucher's workflow.
     *
     * Advances to the next step if more remain, or fully approves if this is the last step.
     *
     * @throws \RuntimeException on stale state or unauthorized actor.
     */
    public function approve(Voucher $voucher, User $actor): void
    {
        DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->findOrFail($voucher->id);

            if ($locked->status !== VoucherStatus::PendingApprover
                || $locked->current_approval_step !== $voucher->current_approval_step) {
                throw new \RuntimeException('Voucher was modified by another user. Please refresh.');
            }

            // Verify the actor is the correct approver for this step
            if (ApprovalWorkflow::isConfigured()) {
                $step = ApprovalWorkflow::getApproverAtStep((int) ($locked->current_approval_step ?? 1));
                if (!$step || $step->user_id != $actor->id) {
                    throw new \RuntimeException('Unauthorized: You are not the correct approver for this step.');
                }
            } else {
                if (!$actor->hasRole('Approver')) {
                    throw new \RuntimeException('Unauthorized: You lack Approver privileges.');
                }
            }

            $locked->load('user');
            $currentStep = (int) ($locked->current_approval_step ?? 1);
            $totalSteps  = ApprovalWorkflow::totalSteps();

            // Record this approval step
            $stepLabel = ApprovalWorkflow::getApproverAtStep($currentStep)?->label;
            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'approved',
                'comments' => $stepLabel ? "Approved as {$stepLabel}" : null,
            ]);

            $nextStep = $currentStep + 1;

            if ($totalSteps > 0 && $nextStep <= $totalSteps) {
                // More steps remaining — advance to next approver
                $locked->update(['current_approval_step' => $nextStep]);

                $next = ApprovalWorkflow::getApproverAtStep($nextStep);
                if ($next) {
                    $next->user->notify(new VoucherStatusNotification($locked, 'checked'));
                }
            } else {
                // All steps done — fully approved
                $locked->update([
                    'status'               => VoucherStatus::Approved->value,
                    'current_approval_step' => null,
                ]);

                $locked->user->notify(new VoucherStatusNotification($locked, 'approved'));
                User::role('Accountant')->get()->each->notify(new VoucherStatusNotification($locked, 'approved'));
            }
        });
    }

    /**
     * Reject a voucher at any point in the checking/approval flow.
     *
     * @throws \RuntimeException if the voucher is not in a rejectable state.
     */
    public function reject(Voucher $voucher, User $actor, string $reason): void
    {
        DB::transaction(function () use ($voucher, $actor, $reason) {
            $locked = Voucher::lockForUpdate()->findOrFail($voucher->id);

            if (!$locked->status->isRejectable()) {
                throw new \RuntimeException('Voucher status changed by another user.');
            }

            $locked->update(['status' => VoucherStatus::Rejected->value]);
            $locked->approvals()->create([
                'user_id'  => $actor->id,
                'action'   => 'rejected',
                'comments' => $reason,
            ]);

            $locked->load('user');
            $locked->user->notify(new VoucherStatusNotification($locked, 'rejected', $reason));
        });
    }

    /**
     * Mark an approved voucher as paid.
     *
     * @throws \RuntimeException if the voucher is not in approved status.
     */
    public function markPaid(Voucher $voucher, User $actor): void
    {
        DB::transaction(function () use ($voucher, $actor) {
            $locked = Voucher::lockForUpdate()->findOrFail($voucher->id);

            if ($locked->status !== VoucherStatus::Approved) {
                throw new \RuntimeException('Voucher status changed by another user.');
            }

            $locked->update(['status' => VoucherStatus::Paid->value]);
            $locked->load('user');
            $locked->user->notify(new VoucherStatusNotification($locked, 'paid'));
        });
    }
}
