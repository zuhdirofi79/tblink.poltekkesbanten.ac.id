<?php

namespace App\Policies;

use App\Models\Pasien;
use App\Models\User;

class PasienPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManagePatients();
    }

    public function view(User $user, Pasien $pasien): bool
    {
        return $this->manages($user, $pasien);
    }

    public function create(User $user): bool
    {
        return $user->canManagePatients();
    }

    public function update(User $user, Pasien $pasien): bool
    {
        return $this->manages($user, $pasien);
    }

    public function delete(User $user, Pasien $pasien): bool
    {
        return $this->manages($user, $pasien);
    }

    private function manages(User $user, Pasien $pasien): bool
    {
        if ($user->canManageAllPatients()) {
            return true;
        }

        if ($user->can('Admin')) {
            $puskesmasId = $user->puskesmas?->id;

            return $puskesmasId !== null && (int) $puskesmasId === (int) $pasien->puskesmas_id;
        }

        return false;
    }
}
