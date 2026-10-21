<?php

namespace App\Policies;

use App\Models\PudRequest;
use App\Models\User;

class PudRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PudRequest $pudRequest): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PudRequest $pudRequest): bool
    {
        return true;
    }

    public function delete(User $user, PudRequest $pudRequest): bool
    {
        return $user->isAdmin();
    }
}
