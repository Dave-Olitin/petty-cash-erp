<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Denomination extends Model
{
    protected $fillable = [
        'denominatable_type',
        'denominatable_id',
        'bill_1000',
        'bill_500',
        'bill_200',
        'bill_100',
        'bill_50',
        'bill_20',
        'bill_10',
        'bill_5',
        'coin_1',
        'coin_0_50',
        'coin_0_25',
        'total_amount',
        'change_given',
        'is_change_received',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'total_amount'       => 'decimal:2',
            'change_given'       => 'decimal:2',
            'is_change_received' => 'boolean',
            'bill_1000'          => 'integer',
            'bill_500'           => 'integer',
            'bill_200'           => 'integer',
            'bill_100'           => 'integer',
            'bill_50'            => 'integer',
            'bill_20'            => 'integer',
            'bill_10'            => 'integer',
            'bill_5'             => 'integer',
            'coin_1'             => 'integer',
            'coin_0_50'          => 'integer',
            'coin_0_25'          => 'integer',
        ];
    }

    public function denominatable(): MorphTo
    {
        return $this->morphTo();
    }
}
