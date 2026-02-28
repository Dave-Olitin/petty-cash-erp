<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'category_id',
        'name',
        'quantity',
        'unit_price',
        'vat',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'integer',
            'unit_price'  => 'decimal:2',
            'vat'         => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }


    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
