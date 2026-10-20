<?php

namespace App\Policies;

use App\Models\Machine;
use App\Models\User;

class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Machine $machine): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Hanya Admin / Master Controller yang berhak menambah mesin
        return $user->isAdmin();
    }

    public function update(User $user, Machine $machine): bool
    {
        // Hanya Admin yang berhak mengubah tarif Machine Rate per jam
        return $user->isAdmin();
    }

    public function delete(User $user, Machine $machine): bool
    {
        return $user->isAdmin();
    }
}
