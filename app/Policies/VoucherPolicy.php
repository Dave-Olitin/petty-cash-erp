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

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Voucher $voucher): bool
    {
        // Only draft vouchers can be edited by the creator
        if ($voucher->status !== 'draft') {
            return false;
        }

        if ($user->can('voucher.edit') && $voucher->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
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
