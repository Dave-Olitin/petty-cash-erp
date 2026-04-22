<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherView extends Model
{
    protected $fillable = ['voucher_id', 'user_id', 'updated_at'];

    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
