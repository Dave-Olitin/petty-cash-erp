<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Observers\JournalEntryObserver;

#[ObservedBy(JournalEntryObserver::class)]
class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_no',
        'date',
        'po_number',
        'invoice_no',
        'reference',
        'currency',
        'total_debit',
        'total_credit',
        'voucher_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->entry_no)) {
                $prefix = 'JE-' . date('Y') . '-';
                $lock = Cache::lock('journal_entry_number_generation', 5);

                try {
                    $lock->block(5, function () use ($model, $prefix) {
                        $latest = static::where('entry_no', 'like', $prefix . '%')
                            ->orderBy('id', 'desc')
                            ->first();

                        $number = $latest ? intval(substr($latest->entry_no, strlen($prefix))) + 1 : 1;
                        $model->entry_no = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
                    });
                } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                    throw ValidationException::withMessages([
                        'entry_no' => 'Could not generate entry number due to high system load.',
                    ]);
                }
            }
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Scopes & Accessors
    // ──────────────────────────────────────────────────────────────────────

    public function scopeInPeriod($query, ?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null)
    {
        return $query->when($from, fn($q) => $q->whereDate('date', '>=', $from))
                     ->when($to, fn($q) => $q->whereDate('date', '<=', $to));
    }

    public function getIsBalancedAttribute(): bool
    {
        $debit = (float) $this->total_debit;
        $credit = (float) $this->total_credit;
        return abs($debit - $credit) < 0.001;
    }
}
