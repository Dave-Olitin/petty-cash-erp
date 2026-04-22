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
        'branch',
        'tax_percentage',
        'tax_amount',
        'debit_account_id',
        'credit_account_id',
        'debit',
        'credit',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount'     => 'decimal:2',
            'debit'          => 'decimal:2',
            'credit'         => 'decimal:2',
            'total'          => 'decimal:2',
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
        // Before saving: ensure `total` column is always populated from DR or CR.
        // This is the single source of truth the parent sums against.
        static::saving(function (self $model) {
            $debit  = (float) ($model->debit  ?? 0);
            $credit = (float) ($model->credit ?? 0);

            // If the user only filled DR, mirror it to total (and vice-versa for CR).
            if ($debit > 0 && (float) ($model->total ?? 0) === 0.0) {
                $model->total  = $debit;
                $model->amount = $debit;
            } elseif ($credit > 0 && (float) ($model->total ?? 0) === 0.0) {
                $model->total  = $credit;
                $model->amount = $credit;
            }

            // Always keep `amount` in sync with `total`.
            if ((float) ($model->total ?? 0) > 0) {
                $model->amount = $model->total;
            }
        });

        $updateParent = function (self $model) {
            $purchaseEntryId = $model->purchase_entry_id;
            if (! $purchaseEntryId) return;

            $parent = PurchaseEntry::find($purchaseEntryId);
            if (! $parent) return;

            // Use the `total` column as the authoritative line amount.
            // As a safety net, fall back to max(debit, credit) if total is still 0.
            $lines       = $parent->lines()->get(['total', 'debit', 'credit', 'tax_amount']);
            $grandTotal  = $lines->sum(fn ($l) => max((float)$l->total, (float)$l->debit, (float)$l->credit));
            $totalVat    = $lines->sum(fn ($l) => (float)$l->tax_amount);
            $totalDebit  = $lines->sum(fn ($l) => (float)$l->debit);
            $totalCredit = $lines->sum(fn ($l) => (float)$l->credit);

            $balanceDue = max(0, $grandTotal - (float) $parent->amount_paid);

            $paymentStatus = PurchaseEntry::STATUS_UNPAID;
            if ($grandTotal > 0) {
                $amountPaid = (float) $parent->amount_paid;
                if ($amountPaid >= $grandTotal) {
                    $paymentStatus = PurchaseEntry::STATUS_PAID;
                } elseif ($amountPaid > 0) {
                    $paymentStatus = PurchaseEntry::STATUS_PARTIAL;
                }
            }

            $parent->update([
                'grand_total'    => $grandTotal,
                'total_vat'      => $totalVat,
                'total_amount'   => $grandTotal - $totalVat,
                'total_debit'    => $totalDebit,
                'total_credit'   => $totalCredit,
                'balance_due'    => $balanceDue,
                'payment_status' => $paymentStatus,
            ]);
        };

        static::saved($updateParent);
        static::deleted($updateParent);
    }
}
