<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Admin dan Purchasing / Receiving Staff dapat menginput penerimaan barang
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Estimator hanya boleh mengubah GRN yang masih Draft
        if ($user->isEstimator() && $goodsReceipt->status === 'Draft') {
            return true;
        }

        return false;
    }

    public function delete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEstimator() && $goodsReceipt->status === 'Draft') {
            return true;
        }

        return false;
    }
}
