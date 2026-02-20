<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
protected $fillable = ['name', 'code', 'gl_code', 'location', 'max_limit', 'transaction_limit', 'current_balance', 'is_active'];

public function transactions()
{
    return $this->hasMany(Transaction::class);
}
}
