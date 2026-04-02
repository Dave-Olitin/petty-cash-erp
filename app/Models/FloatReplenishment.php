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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
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
}
