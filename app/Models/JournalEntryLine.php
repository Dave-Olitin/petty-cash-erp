<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $with = ['accountCode'];

    protected $fillable = [
        'journal_entry_id',
        'account_code_id',
        'branch',
        'supplier_name',
        'trn',
        'date',
        'invoice_no',
        'remarks',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function accountCode(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class);
    }

    protected static function booted()
    {
        $updateParent = function (self $model) {
            // Use the FK directly to avoid lazy-loading violation.
            $journalEntryId = $model->journal_entry_id;
            if (! $journalEntryId) return;

            $parent = JournalEntry::find($journalEntryId);
            if (! $parent) return;

            $parent->update([
                'total_debit'  => $parent->lines()->sum('debit'),
                'total_credit' => $parent->lines()->sum('credit'),
            ]);
        };

        static::saved($updateParent);
        static::deleted($updateParent);
    }
}
