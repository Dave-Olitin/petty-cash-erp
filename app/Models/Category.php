<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'type', 'is_active'];

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
