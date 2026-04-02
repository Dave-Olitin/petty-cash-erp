<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRegistration extends Model
{
    protected $fillable = ['trn', 'name'];

    public function getSelectLabelAttribute()
    {
        return "{$this->trn} — {$this->name}";
    }
}
