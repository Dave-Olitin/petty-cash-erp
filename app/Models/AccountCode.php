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
                $isNew = !$accountCode->exists;
                $codeChanged = $accountCode->isDirty('code');
                $typeChanged = $accountCode->isDirty('type');
                $balanceChanged = $accountCode->isDirty('normal_balance');

                if ($isNew || ($codeChanged && !$typeChanged && !$balanceChanged)) {
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
     * Aggregates paid Voucher Item lines.
     */
    public function scopeWithGLBalances($query, ?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null, ?string $branch = null)
    {
        return $query
            ->withSum(['voucherItems as total_debit' => function ($q) use ($from, $to, $branch) {
                $q->whereHas('voucher', fn($v) => $v->where('status', 'paid'))
                  ->when($from, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch_code', $branch));
            }], 'debit')
            ->withSum(['voucherItems as total_credit' => function ($q) use ($from, $to, $branch) {
                $q->whereHas('voucher', fn($v) => $v->where('status', 'paid'))
                  ->when($from, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '>=', $from)))
                  ->when($to, fn($query) => $query->whereHas('voucher', fn($v) => $v->whereDate('created_at', '<=', $to)))
                  ->when($branch, fn($query) => $query->where('branch_code', $branch));
            }], 'credit');
    }

    /**
     * Clean and normalize supplier or company name for matching.
     */
    public static function normalizeSupplierName(?string $name): string
    {
        if (!$name) return '';
        $n = mb_strtoupper($name, 'UTF-8');
        $n = preg_replace('/[\.\,\-\_\/\(\)\'\"]+/', ' ', $n);
        $suffixes = [
            'SOLE PROPRIETORSHIP',
            'L L C',
            'LLC',
            'LTD',
            'LIMITED',
            'FZE',
            'FZCO',
            'F Z E',
            'ESTABLISHMENT',
            'EST',
            'CO',
            'COMPANY',
            'TRADING',
            'TRAD',
            'TR',
            'MAT',
            'MATERIALS',
        ];
        foreach ($suffixes as $s) {
            $n = preg_replace('/\b' . preg_quote($s, '/') . '\b/u', ' ', $n);
        }
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /**
     * Smart match a supplier name to its corresponding Chart of Accounts liability/AP code (e.g. 2000-01.xx).
     */
    public static function findMatchingSupplierAccount(?string $supplierName): ?self
    {
        if (empty($supplierName)) {
            return null;
        }

        $apAccounts = static::where('code', 'like', '2000-01.%')
            ->orWhere('type', 'liability')
            ->get();

        $cleanSupp = static::normalizeSupplierName($supplierName);

        // 1. Exact match (case-insensitive)
        foreach ($apAccounts as $acct) {
            if (strcasecmp(trim($acct->name), trim($supplierName)) === 0) {
                return $acct;
            }
        }

        // 2. Normalized match (without legal suffixes & punctuation)
        foreach ($apAccounts as $acct) {
            $cleanAcct = static::normalizeSupplierName($acct->name);
            if ($cleanAcct !== '' && $cleanSupp !== '' && $cleanAcct === $cleanSupp) {
                return $acct;
            }
        }

        // 3. Substring containment (prioritizing specific 2000-01.xx accounts)
        $sortedAccts = $apAccounts->sortByDesc(fn($a) => str_starts_with($a->code, '2000-01.') ? 1 : 0);

        foreach ($sortedAccts as $acct) {
            $cleanAcct = static::normalizeSupplierName($acct->name);
            if (strlen($cleanAcct) >= 4) {
                if (str_contains($cleanSupp, $cleanAcct) || str_contains($cleanAcct, $cleanSupp)) {
                    return $acct;
                }
            }
        }

        // 4. Prefix words matching (at least 2 words of length >= 3)
        $suppWords = array_values(array_filter(explode(' ', $cleanSupp), fn($w) => strlen($w) >= 3));
        if (count($suppWords) >= 2) {
            $prefixTwo = $suppWords[0] . ' ' . $suppWords[1];
            foreach ($sortedAccts as $acct) {
                $cleanAcct = static::normalizeSupplierName($acct->name);
                if (str_starts_with($cleanAcct, $prefixTwo)) {
                    return $acct;
                }
            }
        }

        return null;
    }

    /**
     * Smart match an account name to its corresponding TaxRegistration supplier.
     */
    public static function findMatchingTaxRegistration(?string $accountName): ?\App\Models\TaxRegistration
    {
        if (empty($accountName)) {
            return null;
        }

        $taxRegistrations = \App\Models\TaxRegistration::all();
        $cleanAcct = static::normalizeSupplierName($accountName);

        // 1. Exact match
        foreach ($taxRegistrations as $tax) {
            if (strcasecmp(trim($tax->name), trim($accountName)) === 0) {
                return $tax;
            }
        }

        // 2. Normalized match
        foreach ($taxRegistrations as $tax) {
            $cleanTax = static::normalizeSupplierName($tax->name);
            if ($cleanTax !== '' && $cleanAcct !== '' && $cleanTax === $cleanAcct) {
                return $tax;
            }
        }

        // 3. Substring containment
        foreach ($taxRegistrations as $tax) {
            $cleanTax = static::normalizeSupplierName($tax->name);
            if (strlen($cleanAcct) >= 4 && (str_contains($cleanTax, $cleanAcct) || str_contains($cleanAcct, $cleanTax))) {
                return $tax;
            }
        }

        return null;
    }
}
