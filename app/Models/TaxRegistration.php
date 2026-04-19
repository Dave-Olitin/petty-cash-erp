<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRegistration extends Model
{
    protected $fillable = [
        'trn', 
        'name',
        'supplier_code',
        'payment_terms',
        'contact_name',
        'phone',
        'email',
        'entity',
        'started_date',
        'is_active',
    ];

    protected $casts = [
        'started_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function getSelectLabelAttribute()
    {
        return "{$this->trn} — {$this->name}";
    }

    public function purchaseEntries()
    {
        return $this->hasMany(\App\Models\PurchaseEntry::class, 'tax_registration_id');
    }
}
