<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflow extends Model
{
    protected $fillable = ['step_order', 'user_id', 'label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the next approver after the given step, or null if this is the last step.
     */
    public static function getApproverAtStep(int $step): ?self
    {
        return static::where('step_order', $step)->with('user')->first();
    }

    /**
     * How many steps in the chain.
     */
    public static function totalSteps(): int
    {
        return static::max('step_order') ?? 0;
    }

    /**
     * True if the workflow table is configured (at least 1 step).
     */
    public static function isConfigured(): bool
    {
        return static::exists();
    }
}
