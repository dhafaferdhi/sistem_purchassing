<?php

namespace App\Policies;

use App\Models\Part;
use App\Models\User;

class PartPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Part $part): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Admin dan Estimator dapat mendaftarkan part number / komponen baru
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, Part $part): bool
    {
        // Admin dan Estimator dapat memperbarui spesifikasi part
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, Part $part): bool
    {
        // Hanya Admin yang dapat menghapus item master
        return $user->isAdmin();
    }
}
