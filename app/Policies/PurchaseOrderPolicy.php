<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Admin dan Staff Purchasing (Estimator) dapat membuat PO baru
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Estimator hanya boleh mengubah PO yang masih berstatus Draft
        if ($user->isEstimator() && $purchaseOrder->status === 'Draft') {
            return true;
        }

        return false;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Estimator hanya dapat menghapus PO buatannya sendiri yang masih berstatus Draft
        if ($user->isEstimator() && $purchaseOrder->status === 'Draft' && $purchaseOrder->user_id === $user->id) {
            return true;
        }

        return false;
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Hanya Admin dan Management / Approver yang berhak menyetujui PO
        return ($user->isAdmin() || $user->isApprover()) && $purchaseOrder->status === 'Draft';
    }
}
