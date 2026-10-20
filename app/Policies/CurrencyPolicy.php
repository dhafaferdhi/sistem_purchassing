<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Currency $currency): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Currency $currency): bool
    {
        // Hanya Admin / Master Controller yang berhak mengubah kurs mata uang
        return $user->isAdmin();
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->isAdmin();
    }
}
