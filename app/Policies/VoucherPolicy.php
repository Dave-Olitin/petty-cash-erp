<?php

namespace App\Policies;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('voucher.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Voucher $voucher): bool
    {
        if ($user->isHeadOffice() || $user->hasAnyRole(['Accountant', 'Approver', 'Admin', 'Super Admin'])) {
            return true;
        }

        // Branch-based users can view their own vouchers
        if ($voucher->user_id === $user->id) {
            return true;
        }
        
        // Users can view vouchers created by their branch if business logic requires it,
        // but typically they only see their own requests unless they are higher level.
        // Assuming user can only view their own for now unless they are an approver/accountant.
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('voucher.create');
    }

    public function update(User $user, Voucher $voucher): bool
    {
        // Paid vouchers can NEVER be edited, they are finalized.
        if ($voucher->status === 'paid') {
            return false;
        }

        // Draft or rejected vouchers can be edited by the original creator
        if (in_array($voucher->status, ['draft', 'rejected']) && $voucher->user_id === $user->id) {
            return true;
        }

        // Pending or Approved vouchers can be edited by Accountants, Head Office, Admins
        if (in_array($voucher->status, ['pending_checker', 'pending_approver', 'approved'])) {
            if ($user->hasAnyRole(['Accountant', 'Admin', 'Super Admin', 'Head Office'])) {
                return true;
            }
            // Creator can also edit their own pending_checker vouchers before it reaches approver
            if ($voucher->status === 'pending_checker' && $voucher->user_id === $user->id) {
                return true;
            }
            // Explicit permission to edit own vouchers regardless of approval step (as long as it's not paid)
            if ($user->can('voucher.edit_own_undisbursed') && $voucher->user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        // Paid vouchers can NEVER be deleted — the audit trail is immutable.
        // Use a Liquidation settlement / Receipt Voucher to correct the float instead.
        if ($voucher->status === 'paid') {
            return false;
        }

        if (!$user->can('voucher.delete')) {
            return false;
        }

        if ($user->isHeadOffice() || $user->hasAnyRole(['Admin', 'Super Admin'])) {
            return true;
        }

        // Only allowed if draft and belongs to user
        if ($voucher->status === 'draft' && $voucher->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Voucher $voucher): bool
    {
        return $user->isHeadOffice() || $user->hasAnyRole(['Admin', 'Super Admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Voucher $voucher): bool
    {
        return false;
    }
}
