<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'location', 'max_limit', 'transaction_limit', 'is_active', 'allow_overdraft'];
    // NOTE: 'current_balance' is intentionally NOT in $fillable.
    // It must only be modified via TransactionObserver's increment()/decrement() calls.
    // This prevents accidental or malicious overwriting via mass-assignment.

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'allow_overdraft'   => 'boolean',
            'current_balance'   => 'decimal:2',
            'max_limit'         => 'decimal:2',
            'transaction_limit' => 'decimal:2',
        ];
    }

public function transactions()
{
    return $this->hasMany(Transaction::class);
}
}
