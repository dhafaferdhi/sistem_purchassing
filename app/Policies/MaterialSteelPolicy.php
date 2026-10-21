<?php

namespace App\Policies;

use App\Models\MaterialSteel;
use App\Models\User;

class MaterialSteelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaterialSteel $materialSteel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Admin dan Staff Purchasing (Estimator) dapat membuat kontrak harga baja kuartalan
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, MaterialSteel $materialSteel): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, MaterialSteel $materialSteel): bool
    {
        // Hanya Admin yang dapat menghapus master kontrak harga baja
        return $user->isAdmin();
    }
}
