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
        'voucher_template_id',
        'attachment_paths',
        'cheque_no',
        'cheque_date',
        'bank',
        'transaction_summary',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'attachment_paths' => 'array',
            'cheque_date' => 'date',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(VoucherTemplate::class, 'voucher_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VoucherItem::class)->orderBy('sort_order');
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->items()->where('entry_type', 'debit')->sum('amount');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->items()->where('entry_type', 'credit')->sum('amount');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            // Enforce allowed MIME types — only images and PDFs
            ->acceptsFile(function (\Spatie\MediaLibrary\Support\File $file) {
                return in_array($file->mimeType, [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                ]);
            });
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
                'category_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This voucher has been {$eventName}");
    }

    public function scopeActionRequired($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Accountant: needs to check pending vouchers
            if ($user->hasRole('Accountant')) {
                $q->orWhere('status', 'pending_checker');
            }

            // Approver: needs to approve pending vouchers
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

            // Pay-capable users: approved and pending vouchers need action
            if ($user->can('voucher.pay')) {
                $q->orWhereIn('status', ['pending_checker', 'pending_approver', 'approved']);
            }

            // Always require action from the original creator if it's draft or rejected
            $q->orWhere(function ($subQ) use ($user) {
                $subQ->whereIn('status', ['draft', 'rejected'])
                     ->where('user_id', $user->id);
            });
        });
    }
}
