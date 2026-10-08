<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'category_id',
        'account_code_id',
        'item_master_id',
        'name',
        'quantity',
        'unit_price',
        'vat',
        'total_price',
    ];

    protected static function booted(): void
    {
        static::saving(function (TransactionItem $item) {
            // 1. Auto-link account_code_id from ItemMaster if not already assigned
            if ($item->item_master_id && empty($item->account_code_id)) {
                $master = ItemMaster::find($item->item_master_id);
                if ($master && $master->account_code_id) {
                    $item->account_code_id = $master->account_code_id;
                }
            } elseif (!empty($item->name) && empty($item->account_code_id)) {
                $master = ItemMaster::where('name', trim($item->name))->first();
                if ($master && $master->account_code_id) {
                    $item->item_master_id = $item->item_master_id ?: $master->id;
                    $item->account_code_id = $master->account_code_id;
                }
            }

            // 2. Auto-register into ItemMaster if item has both description and account code
            if (!empty($item->name) && !empty($item->account_code_id)) {
                $cleanName = trim($item->name);
                if (strlen($cleanName) >= 2 && !is_numeric($cleanName)) {
                    $master = ItemMaster::firstOrCreate(
                        ['name' => $cleanName],
                        [
                            'account_code_id' => $item->account_code_id,
                            'is_active'       => true,
                        ]
                    );
                    if (empty($item->item_master_id)) {
                        $item->item_master_id = $master->id;
                    }
                }
            }
        });
    }

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

    public function accountCode()
    {
        return $this->belongsTo(AccountCode::class);
    }

    public function itemMaster()
    {
        return $this->belongsTo(ItemMaster::class);
    }
}
