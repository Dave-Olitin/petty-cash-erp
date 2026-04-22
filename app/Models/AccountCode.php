<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountCode extends Model
{
    protected $fillable = ['code', 'name'];

    public function voucherItems()
    {
        return $this->hasMany(\App\Models\VoucherItem::class, 'account_code', 'code');
    }
}
