<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorItem;

class VendorItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VendorItem $vendorItem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, VendorItem $vendorItem): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function delete(User $user, VendorItem $vendorItem): bool
    {
        return $user->isAdmin();
    }
}

