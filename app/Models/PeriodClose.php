<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodClose extends Model
{
    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'status',
        'total_vouchers_paid',
        'total_petty_cash_disbursed',
        'total_ap_billed',
        'total_ap_paid',
        'total_ap_balance',
        'total_journal_dr',
        'total_journal_cr',
        'voucher_count',
        'purchase_entry_count',
        'journal_entry_count',
        'closing_notes',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'closed_at'     => 'datetime',
        'total_vouchers_paid'        => 'decimal:2',
        'total_petty_cash_disbursed' => 'decimal:2',
        'total_ap_billed'            => 'decimal:2',
        'total_ap_paid'              => 'decimal:2',
        'total_ap_balance'           => 'decimal:2',
        'total_journal_dr'           => 'decimal:2',
        'total_journal_cr'           => 'decimal:2',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}
