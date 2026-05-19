<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use NotificationChannels\WebPush\HasPushSubscriptions;
use App\Models\VoucherView;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, HasPushSubscriptions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id', // Required for branch scoping. Must be fillable for UserResource to save it.
        'receive_email_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'branch_id'         => 'integer',
            'receive_email_notifications' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Helper to check if they are HQ
    public function isHeadOffice(): bool
    {
        return is_null($this->branch_id);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'vouchers') {
            return $this->can('access_vouchers_panel');
        }

        if ($panel->getId() === 'admin') {
            // Voucher-only roles are never allowed into the admin panel
            $isVoucherOnlyUser = $this->hasAnyRole(['Accountant', 'Approver', 'Requester'])
                && !$this->hasRole('Admin');

            if ($isVoucherOnlyUser) {
                return false;
            }

            return $this->can('access_petty_cash_panel');
        }

        // Deny access to any unrecognised panel by default
        return false;
    }

    public function voucherViews()
    {
        return $this->hasMany(VoucherView::class);
    }
}