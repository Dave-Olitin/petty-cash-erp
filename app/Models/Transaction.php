<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'user_id',
        'type',
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
            'amount'     => 'decimal:2',
            'vat'        => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
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

    // THIS is the missing piece causing your error:
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function histories()
    {
        return $this->hasMany(TransactionHistory::class);
    }
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
