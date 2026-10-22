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
        'manual_fine_override',
        'fines_settled_at',
        'fine_settlement_type',
        'fine_amount_paid',
        'fine_receipt_number',
        'fine_receipt_attachment',
        'fine_settlement_notes',
        'fine_settled_by',
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
            'fines_settled_at'           => 'datetime',
            'fine_amount_paid'           => 'decimal:2',
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

    public function fineSettledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fine_settled_by');
    }

    // ── Dynamic Overstay & Fine Calculations ────────────────────────────────

    /**
     * Compute the authoritative date when overstay penalties begin.
     * Overstay fines can NEVER start before the residence visa has expired + grace period.
     */
    public function getEffectiveFinesStartDateAttribute(): ?Carbon
    {
        if (! $this->residence_visa_expiry) {
            return $this->fines_start_date ? Carbon::parse($this->fines_start_date) : null;
        }

        $graceDays = (int) ($this->grace_period_days ?: 30);
        $minFinesStart = Carbon::parse($this->residence_visa_expiry)->addDays($graceDays + 1);

        if ($this->fines_start_date) {
            $fDate = Carbon::parse($this->fines_start_date);
            if ($fDate->gte(Carbon::parse($this->residence_visa_expiry))) {
                return $fDate;
            }
        }

        return $minFinesStart;
    }

    /**
     * Number of days past the fines_start_date.
     * Returns 0 if the visa is still valid or within grace period.
     */
    public function getDaysOverdueAttribute(): int
    {
        // Settled fines have no active overdue days
        if ($this->fines_settled_at) {
            return 0;
        }

        $today = Carbon::today();

        // Rule 1: An active, unexpired visa is NEVER overdue!
        if ($this->residence_visa_expiry && $today->lte(Carbon::parse($this->residence_visa_expiry))) {
            return 0;
        }

        $finesStart = $this->effective_fines_start_date;
        if (! $finesStart || $today->lte($finesStart)) {
            return 0;
        }

        return (int) $finesStart->diffInDays($today);
    }

    /**
     * Dynamic PR-50 Overstay Fine (AED 50/day).
     */
    public function getCalculatedFinePr50Attribute(): float
    {
        if ($this->fines_settled_at) {
            return 0.0;
        }

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
        if ($this->fines_settled_at) {
            return 0.0;
        }

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
     * Total cost of all stages that have been marked completed.
     */
    public function getCompletedStageCostAttribute(): float
    {
        return (float) ($this->pipeline_stages['completed_cost'] ?? 0.00);
    }

    /**
     * Remaining processing fee after deducting completed stages.
     */
    public function getRemainingProcessingFeeAttribute(): float
    {
        return (float) ($this->pipeline_stages['remaining_fee'] ?? 0.00);
    }

    /**
     * Combined total of remaining processing fee + active overstay fines (deducted as stages complete).
     */
    public function getGrandTotalWithFinesAttribute(): float
    {
        return (float) ($this->remaining_processing_fee + $this->calculated_total_fines);
    }

    /**
     * Gross total of budgeted processing fee + active overstay fines (before stage deductions).
     */
    public function getGrossTotalWithFinesAttribute(): float
    {
        return (float) ($this->total_cost + $this->calculated_total_fines);
    }

    /**
     * Accurate UAE visa status, grace period, and fines summary.
     */
    public function getFinesStatusSummaryAttribute(): array
    {
        // 0. If fines have been settled (paid or waived)
        if ($this->fines_settled_at) {
            $settledDate = $this->fines_settled_at->format('d M Y');
            $typeLabel = $this->fine_settlement_type === 'waived' ? 'Waived (GDRFA)' : 'Fines Settled';
            $paid = (float) $this->fine_amount_paid;
            $amtText = $paid > 0 ? ' • AED ' . number_format($paid, 2) : '';
            return [
                'status'      => 'settled',
                'label'       => $typeLabel,
                'color'       => 'success',
                'description' => "Settled {$settledDate}{$amtText}" . ($this->fine_receipt_number ? " (Rcpt: {$this->fine_receipt_number})" : ''),
            ];
        }

        $today = Carbon::today();

        // 1. If residence visa expiry exists
        if ($this->residence_visa_expiry) {
            // Case A: Visa is still active / valid
            if ($today->lt($this->residence_visa_expiry)) {
                $daysLeft = (int) $today->diffInDays($this->residence_visa_expiry);

                if ($daysLeft <= 30) {
                    return [
                        'status'      => 'expiring_soon',
                        'label'       => "Expiring ({$daysLeft}d)",
                        'color'       => 'warning',
                        'description' => 'Visa expires ' . $this->residence_visa_expiry->format('d M Y'),
                    ];
                }

                return [
                    'status'      => 'valid',
                    'label'       => 'Valid',
                    'color'       => 'success',
                    'description' => 'Exp: ' . $this->residence_visa_expiry->format('d M Y'),
                ];
            }

            // Case B: Visa has expired
            // B1: Within the Grace Period (between visa expiry and fines_start_date)
            if ($this->fines_start_date && $today->lte($this->fines_start_date)) {
                $graceDays = (int) $today->diffInDays($this->fines_start_date);
                return [
                    'status'      => 'grace',
                    'label'       => $graceDays === 0 ? 'Grace Ends Today' : "Grace: {$graceDays}d left",
                    'color'       => $graceDays <= 5 ? 'danger' : 'warning',
                    'description' => 'Grace ends ' . $this->fines_start_date->format('d M Y'),
                ];
            }

            // B2: Overstay fines are actively accruing (past fines_start_date)
            if ($this->fines_start_date && $today->gt($this->fines_start_date)) {
                $overdue = $this->days_overdue;
                $fines = number_format($this->calculated_total_fines, 2);
                return [
                    'status'      => 'fining',
                    'label'       => "Overdue {$overdue}d (AED {$fines})",
                    'color'       => 'danger',
                    'description' => 'Fines started ' . $this->fines_start_date->format('d M Y'),
                ];
            }

            // B3: Expired with no grace date set
            $expiredDays = (int) $this->residence_visa_expiry->diffInDays($today);
            return [
                'status'      => 'expired',
                'label'       => "Expired ({$expiredDays}d ago)",
                'color'       => 'danger',
                'description' => 'Visa expired ' . $this->residence_visa_expiry->format('d M Y'),
            ];
        }

        // 2. Cancellation Process
        if ($this->process_type === 'cancellation') {
            if ($this->fines_start_date && $today->lte($this->fines_start_date)) {
                $exitDays = (int) $today->diffInDays($this->fines_start_date);
                return [
                    'status'      => 'grace',
                    'label'       => "Exit Grace: {$exitDays}d",
                    'color'       => 'info',
                    'description' => 'Exit by ' . $this->fines_start_date->format('d M Y'),
                ];
            }

            if ($this->fines_start_date && $today->gt($this->fines_start_date)) {
                $overdue = $this->days_overdue;
                $fines = number_format($this->calculated_total_fines, 2);
                return [
                    'status'      => 'fining',
                    'label'       => "Overstay {$overdue}d (AED {$fines})",
                    'color'       => 'danger',
                    'description' => 'Cancelled visa overstay',
                ];
            }

            return [
                'status'      => 'cancelled',
                'label'       => 'Cancelled',
                'color'       => 'gray',
                'description' => null,
            ];
        }

        // 3. New Joiner / Application without Visa Expiry yet
        if ($this->status === 'completed') {
            return [
                'status'      => 'completed',
                'label'       => 'Completed',
                'color'       => 'success',
                'description' => null,
            ];
        }

        if ($this->status === 'draft') {
            return [
                'status'      => 'draft',
                'label'       => 'Draft',
                'color'       => 'gray',
                'description' => null,
            ];
        }

        return [
            'status'      => 'in_pipeline',
            'label'       => 'In Pipeline',
            'color'       => 'gray',
            'description' => null,
        ];
    }

    /**
     * Breakdown of stages and overall pipeline completion progress.
     */
    public function getPipelineStagesAttribute(): array
    {
        $allStages = [
            ['key' => 'offer_letter', 'label' => 'Offer Letter', 'status' => $this->offer_letter_status, 'cost' => $this->offer_letter_cost, 'date' => $this->offer_letter_date],
            ['key' => 'dubins', 'label' => 'Dubins Insurance', 'status' => $this->dubins_status, 'cost' => $this->dubins_cost, 'date' => $this->dubins_date],
            ['key' => 'wp_submission', 'label' => 'Work Permit', 'status' => $this->wp_submission_status, 'cost' => $this->wp_submission_cost, 'date' => $this->wp_submission_date],
            ['key' => 'evisa', 'label' => 'Entry Visa (eVisa)', 'status' => $this->evisa_status, 'cost' => $this->evisa_cost, 'date' => $this->evisa_date],
            ['key' => 'change_status', 'label' => 'Change Status', 'status' => $this->change_status_status, 'cost' => $this->change_status_cost, 'date' => $this->change_status_date],
            ['key' => 'iloe', 'label' => 'ILOE Insurance', 'status' => $this->iloe_status, 'cost' => $this->iloe_cost, 'date' => $this->iloe_date],
            ['key' => 'contract_submission', 'label' => 'Labour Contract', 'status' => $this->contract_submission_status, 'cost' => $this->contract_submission_cost, 'date' => $this->contract_submission_date],
            ['key' => 'emirates_id', 'label' => 'Emirates ID', 'status' => $this->emirates_id_stage_status, 'cost' => $this->emirates_id_stage_cost, 'date' => $this->emirates_id_stage_date],
            ['key' => 'residence_visa', 'label' => 'Residence Visa', 'status' => $this->residence_visa_stage_status, 'cost' => $this->residence_visa_stage_cost, 'date' => $this->residence_visa_stage_date],
        ];

        if ($this->process_type === 'renewal') {
            $applicable = ['dubins', 'wp_submission', 'iloe', 'contract_submission', 'emirates_id', 'residence_visa'];
            $stages = array_values(array_filter($allStages, fn ($s) => in_array($s['key'], $applicable)));
        } elseif ($this->process_type === 'new_outside') {
            $applicable = ['offer_letter', 'dubins', 'wp_submission', 'evisa', 'iloe', 'contract_submission', 'emirates_id', 'residence_visa'];
            $stages = array_values(array_filter($allStages, fn ($s) => in_array($s['key'], $applicable)));
        } elseif ($this->process_type === 'cancellation') {
            $stages = [
                ['key' => 'cancellation', 'label' => 'Visa Cancellation', 'status' => $this->status === 'completed' ? 'Completed' : 'In Progress', 'cost' => $this->total_cost, 'date' => null],
            ];
        } else {
            $stages = $allStages;
        }

        $completed = 0;
        $completedCost = 0.0;
        $totalStageCost = 0.0;
        $applicableCount = 0;

        foreach ($stages as &$stg) {
            $cost = (float) ($stg['cost'] ?? 0);
            $totalStageCost += $cost;

            $s = strtolower(str_replace([' ', '-', '/'], '_', (string) ($stg['status'] ?? 'pending')));

            if ($this->status === 'completed' || in_array($s, ['completed', 'done', 'approved'])) {
                $stg['state'] = 'completed';
                $completed++;
                $completedCost += $cost;
                $applicableCount++;
            } elseif (in_array($s, ['in_progress', 'processing', 'submitted'])) {
                $stg['state'] = 'in_progress';
                $applicableCount++;
            } elseif ($cost <= 0 || in_array($s, ['n_a', 'na', 'not_applicable', 'none', 'exempt'])) {
                $stg['state'] = 'na';
                // Amount 0: no fee required, not pending => marked as N/A
            } else {
                $stg['state'] = 'pending';
                $applicableCount++;
            }
        }

        $totalStages = count($stages);
        $applicableTotal = $applicableCount > 0 ? $applicableCount : $totalStages;

        if ($this->status === 'completed') {
            $completed = $applicableTotal;
            $completedCost = (float) $this->total_cost;
        }

        $percent = $applicableTotal > 0 ? (int) round(($completed / $applicableTotal) * 100) : 0;
        $remainingFee = (float) max(0.00, (float) $this->total_cost - $completedCost);

        return [
            'stages'         => $stages,
            'completed'      => $completed,
            'total'          => $applicableTotal,
            'raw_total'      => $totalStages,
            'percentage'     => $percent,
            'completed_cost' => $completedCost,
            'remaining_fee'  => $remainingFee,
            'total_fee'      => (float) $this->total_cost,
        ];
    }

    /**
     * Single string label for table badge display.
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
        $desc = $this->fines_status_summary['description'] ?? null;

        // Only append passport warning if it's genuinely critical (< 6 months or expired)
        if ($this->passport_expiry) {
            $today = Carbon::today();
            if ($today->gt($this->passport_expiry)) {
                $desc = ($desc ? $desc . ' • ' : '') . '⚠️ Passport Expired';
            } elseif ($today->diffInDays($this->passport_expiry) <= 180) {
                $days = (int) $today->diffInDays($this->passport_expiry);
                $desc = ($desc ? $desc . ' • ' : '') . "⚠️ Passport < 6mo ({$days}d)";
            }
        }

        return $desc;
    }

    /**
     * Dynamic Operational Action & Urgency Status.
     * Computes whether the record "Needs Action" and its urgency level, badge tag, color, and description.
     */
    public function getActionUrgencyAttribute(): array
    {
        $today = Carbon::today();
        $overdue = $this->days_overdue;
        $totalFines = $this->calculated_total_fines;

        // 1. CRITICAL: Fines actively accruing (overstay penalties) - only if not settled
        if (! $this->fines_settled_at && ($overdue > 0 || $totalFines > 0)) {
            $fineFormatted = number_format($totalFines, 0);
            return [
                'level'       => 'critical',
                'tag'         => 'Needs Action',
                'color'       => 'danger',
                'description' => "Overdue ({$overdue}d) • AED {$fineFormatted}",
            ];
        }

        // 2. HIGH URGENCY: Expired visa within grace period (only if fines not settled)
        if (! $this->fines_settled_at && $this->residence_visa_expiry && $today->gte($this->residence_visa_expiry)) {
            if ($this->fines_start_date && $today->lte($this->fines_start_date)) {
                $graceDays = (int) $today->diffInDays($this->fines_start_date);
                return [
                    'level'       => $graceDays <= 7 ? 'critical' : 'urgent',
                    'tag'         => 'Needs Action',
                    'color'       => $graceDays <= 7 ? 'danger' : 'warning',
                    'description' => $graceDays === 0 ? 'Grace Ends Today!' : "Grace: {$graceDays}d left",
                ];
            }
        }

        // 3. URGENT: Active visa expiring within 30 days
        if ($this->residence_visa_expiry && $today->lt($this->residence_visa_expiry)) {
            $daysToExpiry = (int) $today->diffInDays($this->residence_visa_expiry);
            if ($daysToExpiry <= 30 && $this->status !== 'completed') {
                return [
                    'level'       => 'urgent',
                    'tag'         => 'Needs Action',
                    'color'       => 'warning',
                    'description' => "Visa expires in {$daysToExpiry}d",
                ];
            }
        }

        // 4. On Hold / Cancelled
        if ($this->status === 'on_hold') {
            return [
                'level'       => 'on_hold',
                'tag'         => 'On Hold',
                'color'       => 'danger',
                'description' => 'Action Paused',
            ];
        }

        if ($this->status === 'cancelled') {
            return [
                'level'       => 'cancelled',
                'tag'         => 'Cancelled',
                'color'       => 'gray',
                'description' => null,
            ];
        }

        // 5. Completed (Residency valid or fines settled, and pipeline finished)
        $stages = $this->pipeline_stages;
        $isPipelineDone = ($stages['percentage'] ?? 0) >= 100 || strtolower((string) $this->residence_visa_stage_status) === 'completed';

        if ($this->status === 'completed' || ($isPipelineDone && ($this->fines_settled_at || $totalFines == 0))) {
            return [
                'level'       => 'completed',
                'tag'         => 'Completed',
                'color'       => 'success',
                'description' => $this->fines_settled_at ? 'Fines Settled • Compliant' : null,
            ];
        }

        if ($this->status === 'draft') {
            return [
                'level'       => 'draft',
                'tag'         => 'Draft',
                'color'       => 'gray',
                'description' => null,
            ];
        }

        // 6. Normal In-Progress Pipeline
        return [
            'level'       => 'normal',
            'tag'         => 'In Progress',
            'color'       => 'warning',
            'description' => $this->fines_settled_at ? 'Fines Settled (AED ' . number_format((float) $this->fine_amount_paid, 0) . ')' : null,
        ];
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
            // Guarantee non-nullable numeric columns have valid defaults so MySQL never throws 1048 Cannot be null
            $costFields = [
                'offer_letter_cost',
                'dubins_cost',
                'wp_submission_cost',
                'evisa_cost',
                'change_status_cost',
                'iloe_cost',
                'contract_submission_cost',
                'emirates_id_stage_cost',
                'residence_visa_stage_cost',
                'fine_eid_20',
                'fine_pr_50',
                'total_cost',
            ];
            foreach ($costFields as $field) {
                if (is_null($record->{$field})) {
                    $record->{$field} = 0.00;
                }
            }

            // Automatically keep total_cost synchronized if stage fees exist
            $calculatedTotal = $record->calculateTotalCost();
            if ($calculatedTotal > 0 || empty($record->total_cost)) {
                $record->total_cost = $calculatedTotal;
            }

            // For amount 0, stages are not pending — auto-set to 'N/A' if currently 'Pending' or empty
            $stageStatusMap = [
                'offer_letter_status'        => 'offer_letter_cost',
                'dubins_status'              => 'dubins_cost',
                'wp_submission_status'       => 'wp_submission_cost',
                'evisa_status'               => 'evisa_cost',
                'change_status_status'       => 'change_status_cost',
                'iloe_status'                => 'iloe_cost',
                'contract_submission_status' => 'contract_submission_cost',
                'emirates_id_stage_status'   => 'emirates_id_stage_cost',
                'residence_visa_stage_status' => 'residence_visa_stage_cost',
            ];
            foreach ($stageStatusMap as $statusField => $costField) {
                if ((float) ($record->{$costField} ?? 0) <= 0 && in_array(strtolower((string) $record->{$statusField}), ['pending', ''])) {
                    $record->{$statusField} = 'N/A';
                }
            }

            $graceDays = (int) ($record->grace_period_days ?: 30);
            $record->grace_period_days = $graceDays;

            // Auto-calculate / sanitize fines_start_date from visa expiry + grace period
            if ($record->residence_visa_expiry) {
                $minStart = $record->residence_visa_expiry->copy()->addDays($graceDays + 1);
                if (empty($record->fines_start_date) || $record->fines_start_date->lt($record->residence_visa_expiry)) {
                    $record->fines_start_date = $minStart;
                }
            }

            // If residence visa stage was completed and has a date, auto-set visa expiry to +2 years if empty
            if ($record->residence_visa_stage_status === 'Completed' && $record->residence_visa_stage_date && empty($record->residence_visa_expiry)) {
                $record->residence_visa_expiry = $record->residence_visa_stage_date->copy()->addYears(2);
                $record->fines_start_date = $record->residence_visa_expiry->copy()->addDays($graceDays + 1);
            }

            // Auto-complete application if residence visa and contracts are done
            if ($record->residence_visa_stage_status === 'Completed' && $record->contract_submission_status === 'Completed' && $record->status === 'in_progress') {
                $record->status = 'completed';
            }

            // Sync dynamic fines if not manually overridden and not settled
            if (! $record->manual_fine_override && ! $record->fines_settled_at) {
                $record->fine_pr_50 = $record->calculated_fine_pr_50;
                $record->fine_eid_20 = $record->calculated_fine_eid_20;
            }
        });
    }

    /**
     * Settle overstay fines (payment or waiver) and close outstanding penalties.
     */
    public function settleFines(
        string $type,
        float $amountPaid,
        ?string $receiptNumber = null,
        ?string $attachment = null,
        ?string $notes = null,
        ?Carbon $settledAt = null,
        ?int $userId = null
    ): void {
        $settledAt = $settledAt ?: Carbon::now();

        $this->forceFill([
            'fines_settled_at'        => $settledAt,
            'fine_settlement_type'    => $type,
            'fine_amount_paid'        => $amountPaid,
            'fine_receipt_number'     => $receiptNumber,
            'fine_receipt_attachment' => $attachment,
            'fine_settlement_notes'   => $notes,
            'fine_settled_by'         => $userId ?: auth()->id(),
            'fine_pr_50'              => 0.00,
            'fine_eid_20'             => 0.00,
            'manual_fine_override'    => true,
        ]);

        // If all stages are done or 100% complete, close the pipeline as completed
        $stages = $this->pipeline_stages;
        if (($stages['percentage'] ?? 0) >= 100 || strtolower((string) $this->residence_visa_stage_status) === 'completed') {
            $this->status = 'completed';
        }

        $this->save();
    }

    /**
     * Reopen or cancel a fine settlement.
     */
    public function reopenFines(): void
    {
        $this->forceFill([
            'fines_settled_at'        => null,
            'fine_settlement_type'    => null,
            'fine_amount_paid'        => 0.00,
            'fine_receipt_number'     => null,
            'fine_receipt_attachment' => null,
            'fine_settlement_notes'   => null,
            'fine_settled_by'         => null,
            'manual_fine_override'    => false,
        ]);

        if ($this->status === 'completed' && ($this->pipeline_stages['percentage'] ?? 0) < 100) {
            $this->status = 'in_progress';
        }

        $this->save();
    }
}
