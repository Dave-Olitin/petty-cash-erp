<?php

namespace App\Enums;

/**
 * All possible states a Voucher can be in.
 *
 * These values MUST match the string values stored in the database.
 * Using an Enum prevents silent typos in status comparisons across
 * Observers, Policies, Services, and Notifications.
 *
 * NOTE: Model casts are intentionally NOT set to this enum so that
 * Filament's existing string-based match() expressions remain unchanged.
 * Use ::from() / ->value when bridging between business logic and Filament.
 */
enum VoucherStatus: string
{
    case Draft           = 'draft';
    case PendingChecker  = 'pending_checker';
    case PendingApprover = 'pending_approver';
    case Approved        = 'approved';
    case Rejected        = 'rejected';
    case Paid            = 'paid';

    /**
     * States that are considered "in-flight" (awaiting action from someone).
     *
     * @return self[]
     */
    public static function pendingStates(): array
    {
        return [self::PendingChecker, self::PendingApprover];
    }

    /**
     * States from which a rejection is possible.
     */
    public static function rejectableStates(): array
    {
        return [self::PendingChecker, self::PendingApprover];
    }

    /**
     * Returns true if this status is a terminal / immutable state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Paid]);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft           => 'Draft',
            self::PendingChecker  => 'Pending Checker',
            self::PendingApprover => 'Pending Approver',
            self::Approved        => 'Approved',
            self::Rejected        => 'Rejected',
            self::Paid            => 'Paid',
        };
    }
}
