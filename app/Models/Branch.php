<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'location', 'max_limit', 'transaction_limit', 'current_balance', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
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
