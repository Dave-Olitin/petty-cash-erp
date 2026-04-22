<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Liquidation extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'voucher_id',
        'liquidated_by',
        'amount_spent',
        'amount_returned',
        'amount_short',
        'prior_deduction',
        'status',
        'remarks',
        'due_date',
        'liquidated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_spent'     => 'decimal:2',
            'amount_returned'  => 'decimal:2',
            'amount_short'     => 'decimal:2',
            'prior_deduction'  => 'decimal:2',
            'due_date'         => 'date',
            'liquidated_at'    => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount_spent', 'amount_returned', 'remarks'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquidated_by');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Total accounted = spent + returned + prior_deduction (advance already settled) */
    public function getTotalAccountedAttribute(): float
    {
        return (float) $this->amount_spent + (float) $this->amount_returned + (float) $this->prior_deduction;
    }

    /** 
     * Net receipts target = voucher amount minus any pre-settled deductions.
     * Variance = total_accounted vs original voucher amount.
     */
    public function getVarianceAttribute(): float
    {
        return $this->total_accounted - (float) ($this->voucher?->amount ?? 0);
    }

    /** Amount of receipts still needed from the employee */
    public function getReceiptsNeededAttribute(): float
    {
        $net = (float) ($this->voucher?->amount ?? 0) - (float) $this->prior_deduction;
        return max(0, $net - (float) $this->amount_spent - (float) $this->amount_returned);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_date
            && \Carbon\Carbon::parse($this->due_date)->isPast();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->whereNotNull('due_date')
                     ->where('due_date', '<', now()->toDateString());
    }

    public function scopeComplete($query)
    {
        return $query->where('status', 'complete');
    }
}
