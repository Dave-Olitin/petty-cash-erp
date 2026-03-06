<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountCode extends Model
{
    // Shared with the Voucher panel — columns: id, code, name
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Categories that use this account code.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Transaction items that use this account code.
     */
    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
