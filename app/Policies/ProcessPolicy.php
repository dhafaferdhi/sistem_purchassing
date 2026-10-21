<?php

namespace App\Policies;

use App\Models\Process;
use App\Models\User;

class ProcessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Process $process): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Process $process): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Process $process): bool
    {
        return $user->isAdmin();
    }
}
