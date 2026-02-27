<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Draft           = 'draft';
    case PendingChecker  = 'pending_checker';
    case PendingApprover = 'pending_approver';
    case Approved        = 'approved';
    case Rejected        = 'rejected';
    case Paid            = 'paid';

    /** Human-readable label (replaces scattered ucwords/str_replace calls). */
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

    /** Filament badge color. */
    public function color(): string
    {
        return match ($this) {
            self::Draft           => 'gray',
            self::PendingChecker  => 'warning',
            self::PendingApprover => 'warning',
            self::Approved        => 'success',
            self::Rejected        => 'danger',
            self::Paid            => 'success',
        };
    }

    /** Filament heroicon name. */
    public function icon(): string
    {
        return match ($this) {
            self::Draft           => 'heroicon-m-pencil-square',
            self::PendingChecker  => 'heroicon-m-clock',
            self::PendingApprover => 'heroicon-m-clock',
            self::Approved        => 'heroicon-m-check-circle',
            self::Rejected        => 'heroicon-m-x-circle',
            self::Paid            => 'heroicon-m-banknotes',
        };
    }

    /** Statuses that are "active" (i.e. not yet resolved). */
    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::PendingChecker, self::PendingApprover]);
    }

    /** Statuses that allow checker/approver rejection. */
    public function isRejectable(): bool
    {
        return in_array($this, [self::PendingChecker, self::PendingApprover]);
    }
}
