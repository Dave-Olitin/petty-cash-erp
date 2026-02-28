<?php

namespace App\Enums;

/**
 * All possible states a Transaction can be in.
 *
 * NOTE: Model casts are intentionally NOT set to this enum so that
 * Filament's existing string-based match() expressions remain unchanged.
 */
enum TransactionStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * States that are editable by branch users.
     */
    public static function editableStates(): array
    {
        return [self::Pending];
    }

    /**
     * States from which a rejection can be performed (by HQ).
     */
    public static function rejectableStates(): array
    {
        return [self::Pending, self::Approved];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
