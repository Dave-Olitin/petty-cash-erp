<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankPaymentLine extends Model
{
    protected $table = 'bank_payment_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_split' => 'boolean',
        'total_splits' => 'integer',
        'payment_index' => 'integer',
        'cheque_date' => 'date',
        'voucher_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function floatReplenishment(): HasOne
    {
        return $this->hasOne(FloatReplenishment::class, 'voucher_id', 'voucher_id')->latestOfMany();
    }

    public function getSplitStatusAttribute(): string
    {
        if ($this->is_split) {
            $curr = $this->payment_index + 1;
            $total = $this->total_splits;
            return "Split ({$curr} of {$total})";
        }
        return 'Single (1 of 1)';
    }
}
