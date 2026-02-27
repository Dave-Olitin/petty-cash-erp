<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TransactionStatus;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'user_id',
        'type',
        'transaction_date', // User-selected business date. Separate from created_at (system audit timestamp).
        'amount',
        'payee',
        'supplier',
        'trn',
        'reference_number',
        'description',
        'receipt_path',
        'status',
        'rejection_reason',
        'accounting_remarks',
        'category_id',
        'vat',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'vat'              => 'decimal:2',
            'status'           => TransactionStatus::class, // Enum cast — eliminates raw string comparisons
            'transaction_date' => 'datetime',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
            'deleted_at'       => 'datetime',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionHistory::class);
    }
    // Note: categories are per-item, not per-transaction. See TransactionItem::category()
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
