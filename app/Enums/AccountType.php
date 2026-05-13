<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset     = 'asset';
    case Liability = 'liability';
    case Equity    = 'equity';
    case Revenue   = 'revenue';
    case Expense   = 'expense';

    /**
     * Human-readable label shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Asset     => 'Asset',
            self::Liability => 'Liability',
            self::Equity    => 'Equity',
            self::Revenue   => 'Revenue / Income',
            self::Expense   => 'Expense',
        };
    }

    /**
     * The normal balance side for this account type.
     * - Debit-normal: Asset, Expense
     * - Credit-normal: Liability, Equity, Revenue
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset,
            self::Expense  => 'debit',

            self::Liability,
            self::Equity,
            self::Revenue  => 'credit',
        };
    }

    /**
     * Badge color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Asset     => 'info',
            self::Liability => 'warning',
            self::Equity    => 'primary',
            self::Revenue   => 'success',
            self::Expense   => 'danger',
        };
    }

    /**
     * Navigation/grouping order in the Trial Balance (standard accounting order).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Asset     => 1,
            self::Liability => 2,
            self::Equity    => 3,
            self::Revenue   => 4,
            self::Expense   => 5,
        };
    }

    /**
     * Returns all cases as a [value => label] array for Filament select options.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
