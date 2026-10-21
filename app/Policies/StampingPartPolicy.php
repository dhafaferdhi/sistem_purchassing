<?php

namespace App\Policies;

use App\Models\StampingPart;
use App\Models\User;

class StampingPartPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StampingPart $stampingPart): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, StampingPart $stampingPart): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, StampingPart $stampingPart): bool
    {
        return $user->isAdmin();
    }
}
