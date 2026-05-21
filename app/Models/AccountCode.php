<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;

class AccountCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'description',
        'entity',
        'is_active',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'entity' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($accountCode) {
            if ($accountCode->code) {
                $firstDigit = substr($accountCode->code, 0, 1);
                $type = match($firstDigit) {
                    '1' => AccountType::Asset,
                    '2' => AccountType::Liability,
                    '3' => AccountType::Equity,
                    '4' => AccountType::Revenue,
                    '5' => AccountType::Expense,
                    default => null,
                };

                if ($type) {
                    $accountCode->type = $type;
                    $accountCode->normal_balance = $type->normalBalance();
                }
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────

    /** All voucher line items posted to this account code. */
    public function voucherItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code');
    }

    /** All journal entry lines posted to this account code. */
    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /** Debit lines only — used for withSum aggregate in the table. Excludes rejected/voided vouchers. */
    public function debitItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code')
                    ->where('debit', '>', 0)
                    ->whereHas('voucher', fn ($q) => $q->whereNotIn('status', ['rejected', 'voided']));
    }

    /** Credit lines only — used for withSum aggregate in the table. Excludes rejected/voided vouchers. */
    public function creditItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code')
                    ->where('credit', '>', 0)
                    ->whereHas('voucher', fn ($q) => $q->whereNotIn('status', ['rejected', 'voided']));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper Methods
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Whether this account's normal (healthy) balance is on the Debit side.
     * Assets and Expenses are debit-normal.
     */
    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Whether this account's normal (healthy) balance is on the Credit side.
     * Liabilities, Equity, and Revenue are credit-normal.
     */
    public function isCreditNormal(): bool
    {
        return $this->normal_balance === 'credit';
    }

    /**
     * Auto-derive and set the normal_balance from the type whenever type is set.
     * Call this before saving when type is changed.
     */
    public function syncNormalBalance(): void
    {
        if ($this->type instanceof AccountType) {
            $this->normal_balance = $this->type->normalBalance();
        }
    }

    /**
     * Scope for Trial Balance and GL reports.
     * Aggregates official Journal Entry lines.
     */
    public function scopeWithGLBalances($query, ?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null, ?string $branch = null)
    {
        return $query
            ->withSum(['journalEntryLines as total_debit' => function ($q) use ($from, $to, $branch) {
                $q->when($from, fn($query) => $query->whereHas('journalEntry', fn($je) => $je->whereDate('date', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('journalEntry', fn($je) => $je->whereDate('date', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch', $branch));
            }], 'debit')
            ->withSum(['journalEntryLines as total_credit' => function ($q) use ($from, $to, $branch) {
                $q->when($from, fn($query) => $query->whereHas('journalEntry', fn($je) => $je->whereDate('date', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('journalEntry', fn($je) => $je->whereDate('date', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch', $branch));
            }], 'credit');
    }
}
