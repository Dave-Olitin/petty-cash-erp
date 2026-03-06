<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerBranch extends Model
{
    protected $fillable = ['name'];

    public function voucherItems()
    {
        return $this->hasMany(\App\Models\VoucherItem::class, 'branch_code', 'name');
    }
}
