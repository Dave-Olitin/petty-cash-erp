<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProCategory extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'pro_categories';

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        // New Joiner Default Fees
        'offer_letter_cost',
        'dubins_cost',
        'wp_submission_cost',
        'evisa_cost',
        'change_status_cost',
        'iloe_cost',
        'contract_submission_cost',
        'emirates_id_cost',
        'residence_visa_cost',
        // Renewal Default Fees
        'renewal_wp_cost',
        'renewal_dubins_cost',
        'renewal_iloe_cost',
        'renewal_contract_cost',
        'renewal_emirates_id_cost',
        'renewal_residence_visa_cost',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                    => 'boolean',
            'offer_letter_cost'            => 'decimal:2',
            'dubins_cost'                  => 'decimal:2',
            'wp_submission_cost'           => 'decimal:2',
            'evisa_cost'                   => 'decimal:2',
            'change_status_cost'           => 'decimal:2',
            'iloe_cost'                    => 'decimal:2',
            'contract_submission_cost'     => 'decimal:2',
            'emirates_id_cost'             => 'decimal:2',
            'residence_visa_cost'          => 'decimal:2',
            'renewal_wp_cost'              => 'decimal:2',
            'renewal_dubins_cost'          => 'decimal:2',
            'renewal_iloe_cost'            => 'decimal:2',
            'renewal_contract_cost'        => 'decimal:2',
            'renewal_emirates_id_cost'     => 'decimal:2',
            'renewal_residence_visa_cost'  => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function proRecords(): HasMany
    {
        return $this->hasMany(ProRecord::class, 'pro_category_id');
    }

    /**
     * Get the total default cost for New Joiner (Inside UAE)
     */
    public function getNewJoinerInsideTotalAttribute(): float
    {
        return (float) (
            $this->offer_letter_cost +
            $this->dubins_cost +
            $this->wp_submission_cost +
            $this->evisa_cost +
            $this->change_status_cost +
            $this->iloe_cost +
            $this->contract_submission_cost +
            $this->emirates_id_cost +
            $this->residence_visa_cost
        );
    }

    /**
     * Get the total default cost for Renewal
     */
    public function getRenewalTotalAttribute(): float
    {
        return (float) (
            $this->renewal_wp_cost +
            $this->renewal_dubins_cost +
            $this->renewal_iloe_cost +
            $this->renewal_contract_cost +
            $this->renewal_emirates_id_cost +
            $this->renewal_residence_visa_cost
        );
    }
}
