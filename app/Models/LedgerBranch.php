<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerBranch extends Model
{
    protected $fillable = ['name', 'license_expiry'];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
        ];
    }

    public function voucherItems()
    {
        return $this->hasMany(\App\Models\VoucherItem::class, 'branch_code', 'name');
    }

    public function proRecords()
    {
        return $this->hasMany(\App\Models\ProRecord::class, 'ledger_branch_id');
    }
}
