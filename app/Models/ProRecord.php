<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProRecord extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'pro_records';

    protected $with = ['ledgerBranch', 'proCategory'];

    protected $fillable = [
        'ledger_branch_id',
        'branch_id',
        'license_expiry',
        'employee_name',
        'nationality',
        'job_description',
        'user_id',
        'process_type',
        'pro_category_id',
        'company_category',
        'status',
        'person_code',
        'unified_id',
        'passport_number',
        'passport_expiry',
        'emirates_id_number',
        'emirates_id_expiry',
        'labour_card_number',
        'labour_card_expiry',
        'labour_contract_expiry',
        'residence_visa_number',
        'residence_visa_expiry',
        'grace_period_days',
        'fines_start_date',
        'fine_eid_20',
        'fine_pr_50',
        'manual_fine_override',
        'offer_letter_status',
        'offer_letter_date',
        'offer_letter_cost',
        'dubins_status',
        'dubins_date',
        'dubins_cost',
        'wp_submission_status',
        'wp_submission_date',
        'wp_submission_cost',
        'evisa_status',
        'evisa_date',
        'evisa_cost',
        'change_status_status',
        'change_status_date',
        'change_status_cost',
        'iloe_status',
        'iloe_date',
        'iloe_cost',
        'contract_submission_status',
        'contract_submission_date',
        'contract_submission_cost',
        'emirates_id_stage_status',
        'emirates_id_stage_date',
        'emirates_id_stage_cost',
        'residence_visa_stage_status',
        'residence_visa_stage_date',
        'residence_visa_stage_cost',
        'total_cost',
        'attachments',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry'             => 'date',
            'passport_expiry'            => 'date',
            'emirates_id_expiry'         => 'date',
            'labour_card_expiry'         => 'date',
            'labour_contract_expiry'     => 'date',
            'residence_visa_expiry'      => 'date',
            'fines_start_date'           => 'date',
            'offer_letter_date'          => 'date',
            'dubins_date'                => 'date',
            'wp_submission_date'         => 'date',
            'evisa_date'                 => 'date',
            'change_status_date'         => 'date',
            'iloe_date'                  => 'date',
            'contract_submission_date'   => 'date',
            'emirates_id_stage_date'     => 'date',
            'residence_visa_stage_date'  => 'date',
            'grace_period_days'          => 'integer',
            'manual_fine_override'       => 'boolean',
            'fine_eid_20'                => 'decimal:2',
            'fine_pr_50'                 => 'decimal:2',
            'offer_letter_cost'          => 'decimal:2',
            'dubins_cost'                => 'decimal:2',
            'wp_submission_cost'         => 'decimal:2',
            'evisa_cost'                 => 'decimal:2',
            'change_status_cost'         => 'decimal:2',
            'iloe_cost'                  => 'decimal:2',
            'contract_submission_cost'   => 'decimal:2',
            'emirates_id_stage_cost'     => 'decimal:2',
            'residence_visa_stage_cost'  => 'decimal:2',
            'total_cost'                 => 'decimal:2',
            'attachments'                => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function ledgerBranch(): BelongsTo
    {
        return $this->belongsTo(LedgerBranch::class, 'ledger_branch_id');
    }

    public function proCategory(): BelongsTo
    {
        return $this->belongsTo(ProCategory::class, 'pro_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Dynamic Overstay & Fine Calculations ────────────────────────────────

    /**
     * Number of days past the fines_start_date.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->fines_start_date) {
            return 0;
        }

        $today = Carbon::today();
        if ($today->gt($this->fines_start_date)) {
            return (int) $this->fines_start_date->diffInDays($today);
        }

        return 0;
    }

    /**
     * Dynamic PR-50 Overstay Fine (AED 50/day).
     */
    public function getCalculatedFinePr50Attribute(): float
    {
        if ($this->manual_fine_override) {
            return (float) $this->fine_pr_50;
        }

        return (float) ($this->days_overdue * 50);
    }

    /**
     * Dynamic EID-20 Delay Fine (AED 20/day, max AED 1,000).
     */
    public function getCalculatedFineEid20Attribute(): float
    {
        if ($this->manual_fine_override) {
            return (float) $this->fine_eid_20;
        }

        return (float) min(1000, $this->days_overdue * 20);
    }

    /**
     * Combined live fines (PR-50 + EID-20).
     */
    public function getCalculatedTotalFinesAttribute(): float
    {
        return $this->calculated_fine_pr_50 + $this->calculated_fine_eid_20;
    }

    /**
     * Grace period countdown or fines status array.
     */
    public function getFinesStatusSummaryAttribute(): array
    {
        $today = Carbon::today();

        if (! $this->fines_start_date) {
            if ($this->residence_visa_expiry && $today->gt($this->residence_visa_expiry)) {
                $days = (int) $this->residence_visa_expiry->diffInDays($today);
                return [
                    'status' => 'expired',
                    'label'  => "Visa Expired ({$days}d ago)",
                    'color'  => 'danger',
                ];
            }

            return [
                'status' => 'no_date',
                'label'  => 'No Fines',
                'color'  => 'gray',
            ];
        }

        if ($today->lte($this->fines_start_date)) {
            $daysLeft = (int) $today->diffInDays($this->fines_start_date);
            return [
                'status' => 'grace',
                'label'  => $daysLeft === 0 ? 'Last Day of Grace' : "Grace: {$daysLeft}d left",
                'color'  => $daysLeft <= 5 ? 'warning' : 'info',
            ];
        }

        $overdue = $this->days_overdue;
        $totalFines = number_format($this->calculated_total_fines, 2);

        return [
            'status' => 'fining',
            'label'  => "Overdue {$overdue}d (AED {$totalFines})",
            'color'  => 'danger',
        ];
    }

    /**
     * Single string label for table badge display (prevents duplicate array badge pills).
     */
    public function getComplianceBadgeAttribute(): string
    {
        return $this->fines_status_summary['label'] ?? 'Compliant';
    }

    /**
     * Color for compliance badge.
     */
    public function getComplianceColorAttribute(): string
    {
        return $this->fines_status_summary['color'] ?? 'gray';
    }

    /**
     * Secondary description for compliance column.
     */
    public function getComplianceDescriptionAttribute(): ?string
    {
        $parts = [];

        if ($this->fines_start_date) {
            $today = Carbon::today();
            if ($today->gt($this->fines_start_date)) {
                $parts[] = 'Fines started: ' . $this->fines_start_date->format('d M Y');
            } else {
                $parts[] = 'Grace until: ' . $this->fines_start_date->format('d M Y');
            }
        } elseif ($this->residence_visa_expiry) {
            $parts[] = 'Visa exp: ' . $this->residence_visa_expiry->format('d M Y');
        }

        if ($this->passport_expiry) {
            $today = Carbon::today();
            if ($today->gt($this->passport_expiry)) {
                $parts[] = '⚠️ Passport Expired';
            } elseif ($today->diffInDays($this->passport_expiry) <= 180) {
                $days = (int) $today->diffInDays($this->passport_expiry);
                $parts[] = "⚠️ Passport < 6mo ({$days}d)";
            }
        }

        return ! empty($parts) ? implode(' • ', $parts) : null;
    }

    // ── Dynamic Expiry Statuses ─────────────────────────────────────────────

    public function getPassportStatusAttribute(): array
    {
        if (! $this->passport_expiry) {
            return ['label' => 'No Date', 'color' => 'gray'];
        }

        $today = Carbon::today();
        if ($today->gt($this->passport_expiry)) {
            return ['label' => 'Expired', 'color' => 'danger'];
        }

        $days = (int) $today->diffInDays($this->passport_expiry);
        if ($days <= 180) { // < 6 months
            return ['label' => "< 6 Months ({$days}d)", 'color' => 'warning'];
        }

        return ['label' => "Valid ({$days}d)", 'color' => 'success'];
    }

    public function getVisaStatusAttribute(): array
    {
        if (! $this->residence_visa_expiry) {
            return ['label' => 'No Visa Date', 'color' => 'gray'];
        }

        $today = Carbon::today();
        if ($today->gt($this->residence_visa_expiry)) {
            return ['label' => 'Expired', 'color' => 'danger'];
        }

        $days = (int) $today->diffInDays($this->residence_visa_expiry);
        if ($days <= 30) {
            return ['label' => "Expiring ({$days}d)", 'color' => 'danger'];
        }
        if ($days <= 60) {
            return ['label' => "Expiring Soon ({$days}d)", 'color' => 'warning'];
        }

        return ['label' => "Valid ({$days}d)", 'color' => 'success'];
    }

    // ── Automatic Total Cost Recalculation ───────────────────────────────────

    public function calculateTotalCost(): float
    {
        $costs = [
            (float) $this->dubins_cost,
            (float) $this->wp_submission_cost,
            (float) $this->iloe_cost,
            (float) $this->contract_submission_cost,
            (float) $this->emirates_id_stage_cost,
            (float) $this->residence_visa_stage_cost,
        ];

        // Only include Offer Letter for New Joiners
        if (in_array($this->process_type, ['new_inside', 'new_outside'])) {
            $costs[] = (float) $this->offer_letter_cost;
            $costs[] = (float) $this->evisa_cost;
        }

        // Only include Change Status for New Joiners inside UAE
        if ($this->process_type === 'new_inside') {
            $costs[] = (float) $this->change_status_cost;
        }

        return (float) array_sum($costs);
    }

    protected static function booted(): void
    {
        static::saving(function (ProRecord $record) {
            // Automatically keep total_cost synchronized
            $record->total_cost = $record->calculateTotalCost();

            // Auto-calculate fines_start_date from visa expiry + grace period
            if ($record->residence_visa_expiry && empty($record->fines_start_date)) {
                $record->fines_start_date = $record->residence_visa_expiry->copy()->addDays($record->grace_period_days ?: 30);
            }

            // If residence visa stage was completed and has a date, auto-set visa expiry to +2 years if empty
            if ($record->residence_visa_stage_status === 'Completed' && $record->residence_visa_stage_date && empty($record->residence_visa_expiry)) {
                $record->residence_visa_expiry = $record->residence_visa_stage_date->copy()->addYears(2);
                $record->fines_start_date = $record->residence_visa_expiry->copy()->addDays($record->grace_period_days ?: 30);
            }

            // Auto-complete application if residence visa and contracts are done
            if ($record->residence_visa_stage_status === 'Completed' && $record->contract_submission_status === 'Completed' && $record->status === 'in_progress') {
                $record->status = 'completed';
            }

            // Sync dynamic fines if not manually overridden
            if (! $record->manual_fine_override) {
                $record->fine_pr_50 = $record->calculated_fine_pr_50;
                $record->fine_eid_20 = $record->calculated_fine_eid_20;
            }
        });
    }
}
