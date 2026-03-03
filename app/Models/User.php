<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, \NotificationChannels\WebPush\HasPushSubscriptions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id',
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
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isHeadOffice(): bool
    {
        return is_null($this->branch_id);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; 
    }
}