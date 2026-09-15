<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BankReconciliation extends Model
{
    protected $fillable = [
        'account_code',
        'account_name',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance_per_bank',
        'status',
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'period_from'               => 'date',
        'period_to'                 => 'date',
        'opening_balance'           => 'decimal:2',
        'closing_balance_per_bank'  => 'decimal:2',
        'completed_at'              => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class)->orderBy('line_date');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ─── Computed helpers ─────────────────────────────────────────────────

    /** Total debits (cash out) from statement lines. */
    public function getTotalDebitsAttribute(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    /** Total credits (cash in) from statement lines. */
    public function getTotalCreditsAttribute(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    /** Book closing balance: opening + credits - debits. */
    public function getBookClosingBalanceAttribute(): float
    {
        return round(
            (float) $this->opening_balance
            + $this->total_credits
            - $this->total_debits,
            2
        );
    }

    /** Difference between bank statement closing and book closing. */
    public function getDifferenceAttribute(): float
    {
        return round((float) $this->closing_balance_per_bank - $this->book_closing_balance, 2);
    }

    /** Whether the reconciliation is balanced (difference < 0.01). */
    public function getIsBalancedAttribute(): bool
    {
        return abs($this->difference) < 0.01;
    }

    /** Count of matched lines. */
    public function getMatchedCountAttribute(): int
    {
        return $this->lines()->where('match_status', '!=', 'unmatched')->count();
    }

    /** Count of unmatched lines. */
    public function getUnmatchedCountAttribute(): int
    {
        return $this->lines()->where('match_status', 'unmatched')->count();
    }

    // ─── Scopes ────────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ─── Business logic ───────────────────────────────────────────────────

    /**
     * Attempt to auto-match unmatched statement lines against paid payment
     * vouchers for the same bank account and period.
     *
     * Matching priority:
     * 1. Exact reference/cheque number match
     * 2. Amount + date match (within ±3 days)
     *
     * Returns the number of new matches made.
     */
    public function autoMatch(): int
    {
        $matched = 0;

        $unmatchedLines = $this->lines()->where('match_status', 'unmatched')->get();

        // Load paid payment vouchers for this account in an expanded window
        $vouchers = Voucher::whereIn('type', ['payment', 'bank_encashment'])
            ->where('status', 'paid')
            ->where(function ($q) {
                // Support both multiple_payments JSON and legacy bank field
                $q->whereRaw("JSON_SEARCH(multiple_payments, 'one', ?) IS NOT NULL", [$this->account_code])
                  ->orWhere('bank', $this->account_code);
            })
            ->whereBetween(DB::raw('DATE(updated_at)'), [
                $this->period_from->copy()->subDays(7),
                $this->period_to->copy()->addDays(7),
            ])
            ->get();

        // Build a lookup of already-matched voucher IDs to avoid double-matching
        $alreadyMatchedIds = $this->lines()
            ->where('match_status', '!=', 'unmatched')
            ->whereNotNull('voucher_id')
            ->pluck('voucher_id')
            ->toArray();

        foreach ($unmatchedLines as $line) {
            $amount = (float) $line->debit; // payments are debits from bank

            foreach ($vouchers as $voucher) {
                if (in_array($voucher->id, $alreadyMatchedIds)) {
                    continue;
                }

                $payments = $voucher->multiple_payments ?? [['cheque_no' => $voucher->cheque_no, 'amount' => $voucher->amount, 'bank' => $voucher->bank]];

                foreach ($payments as $idx => $p) {
                    if (($p['bank'] ?? '') !== $this->account_code) {
                        continue;
                    }

                    $payAmount = (float) ($p['amount'] ?? 0);
                    $chequeNo  = trim($p['cheque_no'] ?? '');
                    $lineRef   = trim($line->reference ?? '');

                    // Priority 1: exact cheque/reference match
                    if ($chequeNo && $lineRef && strcasecmp($chequeNo, $lineRef) === 0) {
                        $line->update([
                            'match_status'          => 'matched',
                            'voucher_id'            => $voucher->id,
                            'matched_payment_index' => $idx,
                        ]);
                        $alreadyMatchedIds[] = $voucher->id;
                        $matched++;
                        break 2;
                    }

                    // Priority 2: amount match within ±3 days
                    if (abs($payAmount - $amount) < 0.01) {
                        $payDate = $p['cheque_date'] ?? null;
                        if ($payDate) {
                            $daysDiff = abs($line->line_date->diffInDays(\Carbon\Carbon::parse($payDate)));
                            if ($daysDiff <= 3) {
                                $line->update([
                                    'match_status'          => 'matched',
                                    'voucher_id'            => $voucher->id,
                                    'matched_payment_index' => $idx,
                                ]);
                                $alreadyMatchedIds[] = $voucher->id;
                                $matched++;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return $matched;
    }
}
