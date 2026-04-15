<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_entry_id',
        'description',
        'amount',
        'cost_center',
        'tax_percentage',
        'tax_amount',
        'debit_account_id',
        'credit_account_id',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseEntry(): BelongsTo
    {
        return $this->belongsTo(PurchaseEntry::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'credit_account_id');
    }

    protected static function booted()
    {
        $updateParent = function ($model) {
            $parent = $model->purchaseEntry;
            if ($parent) {
                $grandTotal = $parent->lines()->sum('total');
                $totalVat = $parent->lines()->sum('tax_amount');
                $parent->update([
                    'grand_total' => $grandTotal,
                    'total_vat' => $totalVat,
                    'total_amount' => $grandTotal - $totalVat,
                ]);
            }
        };

        static::saved($updateParent);
        static::deleted($updateParent);
    }
}
