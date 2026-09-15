<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    protected $fillable = [
        'bank_reconciliation_id',
        'line_date',
        'description',
        'reference',
        'debit',
        'credit',
        'match_status',
        'voucher_id',
        'matched_payment_index',
        'notes',
    ];

    protected $casts = [
        'line_date'              => 'date',
        'debit'                  => 'decimal:2',
        'credit'                 => 'decimal:2',
        'matched_payment_index'  => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public function getIsMatchedAttribute(): bool
    {
        return $this->match_status !== 'unmatched';
    }

    /** Net effect on account: credits in, debits out */
    public function getNetAttribute(): float
    {
        return (float) $this->credit - (float) $this->debit;
    }

    /** Human-readable match status badge. */
    public function getMatchBadgeAttribute(): string
    {
        return match($this->match_status) {
            'matched' => '✅ Matched',
            'manual'  => '🔧 Manual',
            default   => '⚠️ Unmatched',
        };
    }
}
