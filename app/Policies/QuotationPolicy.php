<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEstimator();
    }

    public function update(User $user, Quotation $quotation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Estimator hanya bisa mengedit dokumen berstatus draft atau rejected
        if ($user->isEstimator() && in_array($quotation->status, ['draft', 'rejected'])) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEstimator() && $quotation->status === 'draft' && $quotation->user_id === $user->id) {
            return true;
        }

        return false;
    }
}

