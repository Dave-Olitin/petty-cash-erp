<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\PurchaseEntryObserver;

#[ObservedBy(PurchaseEntryObserver::class)]
class PurchaseEntry extends Model
{
    use HasFactory;

    // ── Entry Type Constants ──────────────────────────────────────────────
    const TYPE_PURCHASE = 'purchase';
    const TYPE_RETURN   = 'return';

    // ── Payment Status Constants ──────────────────────────────────────────
    const STATUS_UNPAID  = 'unpaid';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID    = 'paid';

    protected $fillable = [
        'entry_no',
        'entry_type',
        'entity',
        'branch',
        'tax_registration_id',
        'user_id',
        'date',
        'due_date',
        'price_type',
        'supplier_name',
        'supplier_trn',
        'po_number',
        'bill_no',
        'invoice_no',
        'currency',
        'total_amount',
        'total_vat',
        'grand_total',
        'total_debit',
        'total_credit',
        'payment_status',
        'amount_paid',
        'balance_due',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'due_date'      => 'date',
            'total_amount'  => 'decimal:2',
            'total_vat'     => 'decimal:2',
            'grand_total'   => 'decimal:2',
            'total_debit'   => 'decimal:2',
            'total_credit'  => 'decimal:2',
            'amount_paid'   => 'decimal:2',
            'balance_due'   => 'decimal:2',
            'is_locked'     => 'boolean',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }

            if (empty($model->entry_no)) {
                // Use type-specific prefix: PE- for purchases, PR- for returns
                $prefix = ($model->entry_type === self::TYPE_RETURN ? 'PR-' : 'PE-') . date('Y') . '-';
                $lock = Cache::lock('purchase_entry_number_generation', 5);

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

            // Initialise balance_due on creation
            $model->balance_due = $model->grand_total ?? 0;
        });

        static::saving(function ($model) {
            // Always sync balance_due with current totals
            $grand = (float) ($model->grand_total ?? 0);
            $paid  = (float) ($model->amount_paid  ?? 0);
            $model->balance_due = max(0, $grand - $paid);
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function taxRegistration(): BelongsTo
    {
        return $this->belongsTo(TaxRegistration::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseEntryLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * How many calendar days past the due_date (positive = overdue).
     */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->due_date || $this->payment_status === self::STATUS_PAID) {
            return 0;
        }
        return max(0, (int) Carbon::today()->diffInDays($this->due_date, false) * -1);
    }

    /**
     * Returns a human-readable aging bucket label.
     */
    public function getAgingBucketAttribute(): string
    {
        $days = $this->days_overdue;

        if ($days <= 0)  return 'Current';
        if ($days <= 30) return '1–30 Days';
        if ($days <= 60) return '31–60 Days';
        if ($days <= 90) return '61–90 Days';
        return '90+ Days';
    }

    /**
     * Returns a Filament badge color for the aging bucket.
     */
    public function getAgingColorAttribute(): string
    {
        return match ($this->aging_bucket) {
            'Current'    => 'success',
            '1–30 Days'  => 'info',
            '31–60 Days' => 'warning',
            '61–90 Days' => 'danger',
            default      => 'gray',    // 90+
        };
    }

    /** Is this entry a Purchase Return / Debit Note? */
    public function isReturn(): bool
    {
        return $this->entry_type === self::TYPE_RETURN;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePurchases($query)
    {
        return $query->where('entry_type', self::TYPE_PURCHASE);
    }

    public function scopeReturns($query)
    {
        return $query->where('entry_type', self::TYPE_RETURN);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('payment_status', [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
                     ->whereIn('payment_status', [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class);
    }
}
