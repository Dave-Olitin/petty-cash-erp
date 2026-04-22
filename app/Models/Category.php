<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'type', 'is_active', 'account_code_id'];



    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function accountCode()
    {
        return $this->belongsTo(AccountCode::class);
    }
}
