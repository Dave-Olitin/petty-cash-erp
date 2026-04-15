<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherItem extends Model
{
    protected $fillable = [
        'voucher_id',
        'entry_type',
        'account_code',
        'trn',
        'description',
        'category_id',
        'branch_code',
        'amount',
        'debit',
        'credit',
        'sort_order',
        'po_number',
        'invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'debit'      => 'decimal:2',
            'credit'     => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Auto-derive entry_type and legacy amount from debit/credit before every save.
     * This ensures backward-compatibility with all existing reports, exports, and
     * aggregate methods that still read entry_type + amount.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $item) {
            $debit  = (float) ($item->debit  ?? 0);
            $credit = (float) ($item->credit ?? 0);

            if ($credit > 0) {
                $item->entry_type = 'credit';
                $item->amount     = $credit;
            } else {
                $item->entry_type = 'debit';
                $item->amount     = $debit;
            }
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
