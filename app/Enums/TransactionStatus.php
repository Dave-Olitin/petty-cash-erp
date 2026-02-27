<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Human-readable label. */
    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /** Filament badge color. */
    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /** Filament heroicon name. */
    public function icon(): string
    {
        return match ($this) {
            self::Pending  => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }
}
