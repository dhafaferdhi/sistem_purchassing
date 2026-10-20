<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Admin dan Staff Purchasing dapat mendaftarkan supplier baru
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        // Hanya Admin yang dapat menghapus data vendor
        return $user->isAdmin();
    }
}
