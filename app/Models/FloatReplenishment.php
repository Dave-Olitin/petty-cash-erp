<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\FloatReplenishmentObserver;

#[ObservedBy(FloatReplenishmentObserver::class)]
class FloatReplenishment extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'date',
        'reference',
        'remarks',
        'created_by',
        'attachment_paths',
        'voucher_id',
        'partial_amount',
        'account_code',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'attachment_paths' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function denominations(): MorphOne
    {
        return $this->morphOne(Denomination::class, 'denominatable');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
