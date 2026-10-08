<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_code_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The GL account code associated with this master item.
     */
    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class);
    }

    /**
     * Line items referencing this master item.
     */
    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
