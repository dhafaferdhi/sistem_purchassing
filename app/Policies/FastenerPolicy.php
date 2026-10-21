<?php

namespace App\Policies;

use App\Models\Fastener;
use App\Models\User;

class FastenerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Fastener $fastener): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, Fastener $fastener): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, Fastener $fastener): bool
    {
        return $user->isAdmin();
    }
}
