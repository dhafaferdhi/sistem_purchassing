<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return 'Login | PUD PORTAL';
    }

    public function getHeading(): string|Htmlable
    {
        return 'PUD PORTAL';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Purchasing Division Monitoring System';
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
