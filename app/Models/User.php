<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // All internal users (admin, estimator, approver) can access the Filament panel
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEstimator(): bool
    {
        return $this->role === 'estimator';
    }

    public function isApprover(): bool
    {
        return $this->role === 'approver';
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
