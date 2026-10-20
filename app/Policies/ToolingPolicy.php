<?php

namespace App\Policies;

use App\Models\Tooling;
use App\Models\User;

class ToolingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tooling $tooling): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Hanya Admin yang dapat mendaftarkan aset cetakan / tooling baru
        return $user->isAdmin();
    }

    public function update(User $user, Tooling $tooling): bool
    {
        // Admin dan Estimator dapat memperbarui data / input stroke tooling
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, Tooling $tooling): bool
    {
        // Hanya Admin yang dapat menghapus data aset tooling
        return $user->isAdmin();
    }
}
