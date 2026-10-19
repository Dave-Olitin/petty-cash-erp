<?php

namespace App\Filament\Vouchers\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\View\View;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Accounting Software';
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'Sign in to access your accounting system.';
    }

    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        // If user is a PRO user without financial voucher access, land directly on PRO Tracking
        if ($user && $user->can('pro_record.view') && !$user->can('voucher.view') && !$user->hasAnyRole(['Admin', 'Super Admin', 'Accountant'])) {
            return \App\Filament\Vouchers\Resources\ProRecordResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }
}
