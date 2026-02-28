<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherTemplate extends Model
{
    protected $fillable = [
        'company_name',
        'tel_no',
        'address',
        'trn',
        'prefix',
        'branch_code',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
