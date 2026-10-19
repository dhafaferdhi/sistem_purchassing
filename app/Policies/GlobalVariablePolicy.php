<?php

namespace App\Policies;

use App\Models\GlobalVariable;
use App\Models\User;

class GlobalVariablePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GlobalVariable $globalVariable): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GlobalVariable $globalVariable): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GlobalVariable $globalVariable): bool
    {
        return $user->isAdmin();
    }
}

