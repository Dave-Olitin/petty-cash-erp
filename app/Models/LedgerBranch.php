<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerBranch extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'license_number',
        'issuing_authority',
        'license_expiry',
        'mohre_est_card_number',
        'mohre_est_card_expiry',
        'immigration_est_card_number',
        'immigration_est_card_expiry',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                   => 'boolean',
            'license_expiry'              => 'date',
            'mohre_est_card_expiry'       => 'date',
            'immigration_est_card_expiry' => 'date',
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
