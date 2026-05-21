<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'voucher_id',
        'float_replenishment_id',
        'file_path',
        'file_name',
        'file_type',
        'uploaded_by',
    ];

    /**
     * Get the voucher associated with this document.
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the float replenishment associated with this document.
     */
    public function floatReplenishment(): BelongsTo
    {
        return $this->belongsTo(FloatReplenishment::class, 'float_replenishment_id');
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
