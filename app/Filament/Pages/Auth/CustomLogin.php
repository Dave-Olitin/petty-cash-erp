<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login;

class CustomLogin extends Login
{
    /**
     * @return array<int | string, string | int>
     */
    protected function getLayoutColumns(): array
    {
        return [
            'sm' => 2,
        ];
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Petty Cash Control Management System'; 
    }

    public function getSubHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null; // Remove "Or sign up" etc if unwanted, or keep default
    }
}
