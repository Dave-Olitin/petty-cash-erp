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
        return 'PAYMENT VOUCHER';
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'Sign in to manage and process pending vouchers.';
    }
}
