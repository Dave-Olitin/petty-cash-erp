<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PurchaseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_no',
        'entity',
        'branch',
        'tax_registration_id',
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
    ];

    protected function casts(): array
    {
        return [
            'date'     => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'total_vat'    => 'decimal:2',
            'grand_total'  => 'decimal:2',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->entry_no)) {
                $prefix = 'PE-' . date('Y') . '-';
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
        });
    }



    public function taxRegistration(): BelongsTo
    {
        return $this->belongsTo(TaxRegistration::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseEntryLine::class);
    }
}
