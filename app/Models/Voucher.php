<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\VoucherObserver;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

#[ObservedBy(VoucherObserver::class)]
class Voucher extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'type',
        'voucher_number',
        'amount',
        'payee',
        'description',
        'status',
        'current_approval_step',
        'user_id',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(VoucherApproval::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'type',
                'amount',
                'payee',
                'description',
                'status',
                'category.name',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This voucher has been {$eventName}");
    }

    public function scopeActionRequired($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            if ($user->hasRole('Accountant')) {
                $q->orWhere('status', 'pending_checker');
            }

            if ($user->hasRole('Approver') || $user->hasRole('Admin') || $user->hasRole('Super Admin')) {
                if (\App\Models\ApprovalWorkflow::isConfigured()) {
                    $q->orWhere(function ($subQ) use ($user) {
                        $subQ->where('status', 'pending_approver')
                             ->whereExists(function ($exists) use ($user) {
                                 $exists->select(\Illuminate\Support\Facades\DB::raw(1))
                                        ->from('approval_workflows')
                                        ->whereColumn('approval_workflows.step_order', 'vouchers.current_approval_step')
                                        ->where('approval_workflows.user_id', $user->id);
                             });
                    });
                } else {
                    $q->orWhere('status', 'pending_approver');
                }
            }

            // Always require action from the original creator if it's draft or rejected
            $q->orWhere(function ($subQ) use ($user) {
                $subQ->whereIn('status', ['draft', 'rejected'])
                     ->where('user_id', $user->id);
            });
        });
    }
}
